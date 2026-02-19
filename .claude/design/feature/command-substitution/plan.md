# Feature: Command Substitution (Slash Commands → Inline Instructions)

## Probleem

Claude CLI herkent `/command-name` als slash command en voert het bijbehorende `.claude/commands/command-name.md` bestand uit. Codex CLI (en mogelijk toekomstige providers) herkennen deze syntax **niet**. Wanneer een gebruiker `/onboard` in de prompt typt en Codex als provider selecteert, wordt `/onboard` letterlijk als tekst naar Codex gestuurd — het command wordt niet uitgevoerd.

## Oplossing

Een generieke preprocessing-stap die slash commands in de prompt-tekst vervangt door een natural-language instructie om het command-bestand te lezen en uit te voeren, **alleen** voor providers die geen native slash command support hebben.

### Voorbeeld

```
Input:  "Kijk naar de code en /onboard daarna /review-changes"
Output: "Kijk naar de code en [Read and follow the instructions in .claude/commands/onboard.md] daarna [Read and follow the instructions in .claude/commands/review-changes.md]"
```

## Analyse

### Huidige flow

1. Gebruiker selecteert command uit dropdown → Quill insert `/command-name ` in editor (`views/ai-chat/index.php`, commandDropdown change handler)
2. `prepareRunRequest()` converteert Quill Delta → markdown string (`AiChatController`)
3. Markdown wordt opgeslagen als `prompt_markdown` in AiRun (`AiChatController`)
4. `RunAiJob::execute()` stuurt `prompt_markdown` **ongewijzigd** naar de provider (`RunAiJob`, streaming/sync branch)
5. ClaudeCliProvider: Claude CLI parsed `/command-name` native → werkt
6. CodexCliProvider: Codex ontvangt `/command-name` als platte tekst → wordt genegeerd

### Ingreeppunt

De substitutie moet plaatsvinden in `RunAiJob::execute()`, **na** `claimForProcessing()` maar **voor** het aanroepen van `executeStreaming()`/`execute()`. Dit is het laatste punt waar we provider-specifiek gedrag kunnen toepassen, en het voorkomt dat de DB-opslag afwijkt van wat de gebruiker typte.

**Waarom niet in `prepareRunRequest()` (controller)?** Daar weten we al de provider, maar:
- De originele prompt (met `/command`) moet ongewijzigd in DB blijven (audit trail, replay)
- Het is een provider-specifieke concern, niet een request-parsing concern

**Waarom na `claimForProcessing()`?** Als de claim faalt (race condition, al door andere worker geclaimd), is er geen onnodige processing gedaan.

### Welke providers substitueren?

Een provider die native slash commands ondersteunt (Claude CLI) heeft geen substitutie nodig. De beslissing is eenvoudig: als `loadCommands()` een lege array retourneert, is de provider incapable en moet er gesubstitueerd worden.

Maar dit is niet helemaal juist: `loadCommands()` retourneert leeg als er geen commands directory is. Beter: voeg een expliciete methode toe aan de interface, of laat de provider zelf de substitutie beslissing maken.

**Gekozen aanpak:** Voeg `supportsSlashCommands(): bool` toe aan `AiConfigProviderInterface`. Dit is expliciet en toekomstbestendig.

## Technisch Plan

### 1. Nieuwe service: `PromptCommandSubstituter`

**Locatie:** `yii/services/ai/PromptCommandSubstituter.php`

**Verantwoordelijkheid:** Vervangt `/command-name` patronen in een prompt-string door een natural-language instructie om het command-bestand te lezen, beperkt tot bekende commands.

```php
namespace app\services\ai;

class PromptCommandSubstituter
{
    private const INSTRUCTION_TEMPLATE = '[Read and follow the instructions in .claude/commands/%s.md]';

    /**
     * Vervangt bekende slash commands in de prompt door instructies.
     *
     * @param string $prompt De originele prompt-tekst
     * @param array<string, string> $knownCommands Command name => description (uit loadCommands)
     * @return string De prompt met vervangen commands
     */
    public function substitute(string $prompt, array $knownCommands): string
    {
        if ($knownCommands === [] || trim($prompt) === '') {
            return $prompt;
        }

        $escaped = array_map('preg_quote', array_keys($knownCommands), array_fill(0, count($knownCommands), '/'));
        $names = implode('|', $escaped);
        $pattern = '/(^|\s)\/(' . $names . ')(?=\s|$)/m';

        return preg_replace_callback($pattern, function (array $matches) {
            return $matches[1] . sprintf(self::INSTRUCTION_TEMPLATE, $matches[2]);
        }, $prompt);
    }
}
```

**Regex-patroon detail:**
- Dynamisch opgebouwd uit keys van `$knownCommands`: namen worden ge-escaped met `preg_quote`, samengevoegd met `|`
- Match: `/(^|\s)\/({escaped-command-names})(?=\s|$)/m` — lookahead zodat trailing whitespace niet geconsumeerd wordt (voorkomt dat twee opeenvolgende commands de spatie ertussen opeten)
- Replace via callback: `$1` + instructie-tekst
- Alleen bekende command-namen matchen (geen wildcards) om false positives te voorkomen (bijv. `/path/to/file`)

**Edge cases:**
- Meerdere commands in één prompt → alle vervangen (lookahead behoudt whitespace)
- Command aan begin/einde van prompt → correct via `^` in multiline mode en `$` anchors
- Command in code block → voor v1 negeren (eenvoud boven perfectie)
- Onbekende `/foo` → niet vervangen (beschermt paden en andere slash-syntax)

### 2. Interface-uitbreiding: `AiConfigProviderInterface`

Voeg toe:

```php
/**
 * Indicates whether the provider's CLI natively handles slash commands.
 * When false, slash commands in prompts will be substituted with
 * explicit instructions before execution.
 */
public function supportsSlashCommands(): bool;
```

**Let op:** Dit is een breaking change op interface-niveau. Alle bestaande implementaties van `AiConfigProviderInterface` moeten deze methode implementeren. Op dit moment zijn dat `ClaudeCliProvider` en `CodexCliProvider` — beide worden in dezelfde PR bijgewerkt.

**Implementaties:**
- `ClaudeCliProvider::supportsSlashCommands()` → `true`
- `CodexCliProvider::supportsSlashCommands()` → `false`

### 3. Integratie in `RunAiJob`

**Benodigde imports bovenaan `RunAiJob.php`:**

```php
use app\services\ai\AiConfigProviderInterface;
use app\services\ai\PromptCommandSubstituter;
```

In `RunAiJob::execute()`, **na** `claimForProcessing()` maar **vóór** de streaming/sync branch:

```php
// Na claimForProcessing(), vóór de streaming/sync branch
$prompt = $run->prompt_markdown;

if ($provider instanceof AiConfigProviderInterface && !$provider->supportsSlashCommands()) {
    $commands = $this->loadAvailableCommands($run);
    if ($commands !== []) {
        $substituter = Yii::$container->get(PromptCommandSubstituter::class);
        $prompt = $substituter->substitute($prompt, $commands);
    }
}
```

**Vervolgens moet `$run->prompt_markdown` in BEIDE branches vervangen worden door `$prompt`:**

Streaming branch (huidige regel 104):
```php
// Was: $run->prompt_markdown
$result = $provider->executeStreaming(
    $prompt,  // ← gewijzigd
    $run->working_directory ?? '',
    ...
);
```

Sync branch (huidige regel 139):
```php
// Was: $run->prompt_markdown
$syncResult = $provider->execute(
    $prompt,  // ← gewijzigd
    $run->working_directory ?? '',
    ...
);
```

De DB-waarde (`$run->prompt_markdown`) blijft ongewijzigd — alleen de lokale `$prompt` variabele bevat de gesubstitueerde tekst.

**Waarom `Yii::$container->get()` in plaats van `new`?** RunAiJob is een queue job (geserialiseerd voor de queue), dus constructor-injectie is niet mogelijk. Dit is consistent met hoe `resolveProvider()` en `createQuickHandler()` al werken in dezelfde class.

**Commands laden:** De bekende commands moeten geladen worden. Ze komen niet van de actieve provider (die retourneert `[]`), maar van een provider die `supportsSlashCommands()` retourneert. De commands directory is provider-onafhankelijk: `.claude/commands/` in het project.

**Aanpak:** Zoek in de registry naar een provider die slash commands ondersteunt en gebruik diens `loadCommands()`. Dit is robuuster dan `getDefault()` gebruiken, want de registratievolgorde kan wijzigen.

**Let op:** `$run->working_directory` bevat het host-path (bijv. `/home/user/project`). `ClaudeCliProvider::loadCommands()` verwacht dit host-path en doet intern `translatePath()` voor container-toegang.

```php
private function loadAvailableCommands(AiRun $run): array
{
    $workDir = $run->working_directory ?? '';
    if ($workDir === '') {
        return [];
    }

    $registry = Yii::$container->get(AiProviderRegistry::class);

    foreach ($registry->all() as $candidate) {
        if ($candidate instanceof AiConfigProviderInterface && $candidate->supportsSlashCommands()) {
            return $candidate->loadCommands($workDir);
        }
    }

    return [];
}
```

### 4. Eenheid: Bestanden

| Bestand | Wijziging |
|---------|-----------|
| `yii/services/ai/AiConfigProviderInterface.php` | + `supportsSlashCommands(): bool` |
| `yii/services/ai/providers/ClaudeCliProvider.php` | + `supportsSlashCommands()` → `true` |
| `yii/services/ai/providers/CodexCliProvider.php` | + `supportsSlashCommands()` → `false` |
| `yii/services/ai/PromptCommandSubstituter.php` | **Nieuw** — substitutie-logica |
| `yii/jobs/RunAiJob.php` | + prompt preprocessing na claim, vóór provider-aanroep |
| `yii/tests/unit/services/ai/PromptCommandSubstituterTest.php` | **Nieuw** — unit tests |
| `yii/tests/unit/services/ai/providers/CodexCliProviderTest.php` | + `testSupportsSlashCommandsReturnsFalse` |
| `yii/tests/unit/services/ai/providers/ClaudeCliProviderTest.php` | + `testSupportsSlashCommandsReturnsTrue` (als bestaand) |

### 5. Tests

**PromptCommandSubstituterTest:**
- `testSubstitutesSingleCommand` — `/onboard` → `[Read and follow the instructions in .claude/commands/onboard.md]`
- `testSubstitutesMultipleCommands` — twee commands in één prompt
- `testIgnoresUnknownCommands` — `/unknown` blijft staan
- `testIgnoresPathsWithSlashes` — `/path/to/file` niet matchen
- `testHandlesCommandAtStartOfPrompt`
- `testHandlesCommandAtEndOfPrompt`
- `testPreservesPromptWithNoCommands` — prompt zonder commands ongewijzigd
- `testEmptyCommandListReturnsOriginal`
- `testReturnsOriginalWhenPromptIsEmpty`
- `testHandlesAdjacentCommands` — `/onboard /review-changes` (whitespace-behoud door lookahead)

**RunAiJob integratie (bestaande tests):**

De `RunAiJob`-integratie wordt niet apart geünittest omdat de job zwaar leunt op externe processen (`proc_open`) en de Yii DI container. De substitutie-logica is volledig geïsoleerd in `PromptCommandSubstituter` (pure functie, geen dependencies) en daar uitputtend getest. De integratie in `RunAiJob` is een eenvoudige if-check + method call — de handmatige verificatie (stap 6) valideert de end-to-end flow.

De bestaande `CodexCliProviderTest` moet aangevuld worden met:
- `testSupportsSlashCommandsReturnsFalse` — verifieert dat Codex `false` retourneert
- In `ClaudeCliProviderTest` (als bestaand, anders nieuw): `testSupportsSlashCommandsReturnsTrue`

### 6. Verificatie

**Unit tests:**
```bash
cd /var/www/html/yii && vendor/bin/codecept run unit tests/unit/services/ai/PromptCommandSubstituterTest.php
```

**Volledige test suite (regressie):**
```bash
cd /var/www/html/yii && vendor/bin/codecept run unit
```

**Handmatige verificatie:**
1. Start een AI chat sessie met Codex als provider
2. Typ een prompt met een slash command (bijv. `/onboard`)
3. Verifieer in de stream output dat de instructie-tekst is verstuurd, niet `/onboard`
4. Verifieer in de DB (`ai_run.prompt_markdown`) dat de originele `/onboard` bewaard is
5. Herhaal met Claude als provider en verifieer dat `/onboard` ongewijzigd wordt doorgestuurd

## Design-beslissingen

| Beslissing | Rationale |
|------------|-----------|
| Natural-language instructie i.p.v. `run` keyword | `run` is niet gedocumenteerd als Codex-keyword. Natural-language (`Read and follow...`) werkt voor elke LLM-gebaseerde provider. |
| `supportsSlashCommands()` i.p.v. `loadCommands() === []` | Expliciet en onafhankelijk van directory-bestaan; toekomstbestendig voor providers met andere command-systemen. |
| Substitutie in RunAiJob, niet controller | Audit trail: DB bewaart originele prompt. Separation of concerns: provider-specifiek gedrag hoort niet in request parsing. |
| Na `claimForProcessing()` | Vermijdt onnodige processing als claim faalt (race condition). |
| `Yii::$container->get()` i.p.v. constructor-injectie | Queue jobs worden geserialiseerd; constructor-injectie is niet mogelijk. Consistent met `resolveProvider()` en `createQuickHandler()`. |
| Commands laden via provider die `supportsSlashCommands()` retourneert | Commands zijn project-configuratie (`.claude/commands/`), niet provider-specifiek. De registry wordt doorlopen om een capable provider te vinden — dit is robuuster dan `getDefault()` dat afhankelijk is van registratievolgorde. |
| Instructie-tekst als class constant | Eén plek om de template te wijzigen als de syntax per provider moet variëren in de toekomst. |

## Niet in scope

- Substitutie van command *inhoud* (inlining van het .md bestand) — de instructie laat de CLI het bestand zelf interpreteren
- Code block detection — v1 vervangt ook in code blocks
- Command parameters — `/command arg1 arg2` → de regex vervangt alleen het `/command`-prefix; de rest blijft staan
- UI-wijzigingen — de command dropdown en Quill plugin blijven ongewijzigd
- Effectieve prompt opslaan in DB — v1 is in-memory only; debug via `Yii::debug()` logging indien nodig
- Ontkoppelen van het `.claude/commands/`-pad — de `INSTRUCTION_TEMPLATE` constant en `ClaudeCliProvider::loadCommands()` hanteren beiden hetzelfde pad; bij toekomstige wijziging moet dit op twee plekken aangepast worden
