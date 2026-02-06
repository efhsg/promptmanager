# Prompt Title Summarizer

## Probleem

Wanneer een prompt naar de Claude CLI wordt gestuurd, wordt deze als collapsed accordeon-item in de chatgeschiedenis geplaatst. De titel is nu de eerste ~80 karakters van de prompt-tekst (markdown-formattering gestript). Bij lange of technische prompts is dit onduidelijk — de gebruiker ziet het begin van de prompt, niet de *essentie*.

## Gewenst gedrag

1. Bij het aanmaken van het accordeon-item: toon de huidige truncated tekst als **tijdelijke titel**
2. Start een **achtergrond-request** naar de server die de prompt samenvat tot één regel via een snel model (Haiku)
3. Zodra het antwoord binnenkomt: **vervang de accordeon-titel** door de samenvatting

De gebruiker merkt niks van het wachten — de accordeon heeft direct een leesbare fallback, en na 2-5 seconden verschijnt een betere titel.

## Architectuur

### Backend: nieuw endpoint `actionSummarizePrompt`

**Locatie:** `ProjectController` (en `ScratchPadController`)

```
POST /project/summarize-prompt?id={projectId}
Body: { "prompt": "de volledige markdown prompt" }
Response: { "success": true, "title": "Eenregelige samenvatting" }
```

**Implementatie:**
- Hergebruikt het bestaande `ClaudeCliService::execute()` patroon (analoog aan `actionSummarizeSession`)
- Model: `haiku` (snelst en goedkoopst)
- Permission mode: `plan` (geen tools nodig)
- Timeout: 30 seconden (samenvatting is simpel)
- System prompt: "Vat de volgende prompt samen in maximaal 10 woorden. Antwoord alleen met de samenvatting, geen uitleg."

**Verschil met `actionSummarizeSession`:**
| | `summarizeSession` | `summarizePrompt` (nieuw) |
|---|---|---|
| Input | Hele conversatie (groot) | Enkele prompt |
| Model | Sonnet | Haiku |
| Output | Gestructureerde markdown | Eén regel platte tekst |
| Timeout | 120s | 30s |
| Doel | Sessie voortzetten | Accordeon-titel |

### Frontend: achtergrond-fetch na accordeon-creatie

**Locatie:** `yii/views/project/claude.php` — JavaScript `claudeChat` object

**Flow:**

```
createActiveAccordionItem(promptText, promptDelta)
   │
   ├── [1] Toon truncated promptText als tijdelijke titel (bestaand gedrag)
   │
   └── [2] Fire-and-forget: fetch('/project/summarize-prompt?id=...')
               │
               └── onSuccess: update <span class="claude-history-item__title">
                                met de AI-samenvatting
```

**Nieuwe methode:** `summarizePromptTitle(itemId, promptText)`

```javascript
summarizePromptTitle: function(itemId, promptText) {
    fetch(summarizePromptUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ prompt: promptText })
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success || !data.title) return;
        var titleEl = document.querySelector(
            '#item-' + itemId + ' .claude-history-item__title'
        );
        if (titleEl) titleEl.textContent = data.title;
    })
    .catch(() => {}); // fail silently, fallback title remains
}
```

**Aanroep in `createActiveAccordionItem`:**

```javascript
// Na regel 1304 (na accordion-item creatie)
this.summarizePromptTitle(itemId, promptText);
```

### System prompt voor titel-samenvatting

```
You are a prompt title generator. Summarize the following prompt
into a short title of at most 10 words. Respond with ONLY the title,
nothing else. No quotes, no explanation, no punctuation at the end.
```

## Scope

### In scope
- Nieuw controller-endpoint `summarizePrompt` in `ProjectController` en `ScratchPadController`
- Nieuwe private methode `buildTitleSummarizerSystemPrompt()` in beide controllers
- JavaScript-methode `summarizePromptTitle()` in `claudeChat` object
- URL-variabele `$summarizePromptUrl` in beide views
- RBAC-registratie van `summarizePrompt` action

### Buiten scope
- Caching van samenvattingen (prompt is uniek per keer)
- Retry bij falen (fallback titel is goed genoeg)
- Titels van historische/herladen sessies (die hebben al hun content)

## Risico's & mitigatie

| Risico | Impact | Mitigatie |
|--------|--------|-----------|
| Haiku geeft lang antwoord | Titel te breed | Trunceer client-side op 80 chars |
| Request faalt (netwerk/timeout) | Geen update | Fallback: truncated prompt blijft staan |
| Dubbele kosten per prompt | Hogere API-kosten | Haiku is ~20x goedkoper dan Sonnet; verwaarloosbaar |
| Race condition: item al verwijderd | querySelector vindt niks | Null-check op titleEl |

## Betrokken bestanden

| Bestand | Wijziging |
|---------|-----------|
| `yii/controllers/ProjectController.php` | +`actionSummarizePrompt()`, +`buildTitleSummarizerSystemPrompt()` |
| `yii/controllers/ScratchPadController.php` | +`actionSummarizePrompt()`, +`buildTitleSummarizerSystemPrompt()` |
| `yii/views/project/claude.php` | +URL var, +JS methode, aanroep in `createActiveAccordionItem` |
| `yii/views/scratch-pad/claude.php` | +URL var, +JS methode, aanroep in `createActiveAccordionItem` |
| `yii/config/rbac.php` | +`summarizePrompt` action mapping |
| `yii/services/EntityPermissionService.php` | +`summarize-prompt` in `MODEL_BASED_ACTIONS` |

## Alternatief overwogen: Anthropic Messages API direct

In plaats van Claude CLI → Haiku zou een directe API-call naar `https://api.anthropic.com/v1/messages` sneller zijn (geen process spawn overhead). Echter:
- Er is nog geen API-key configuratie in het project (alleen OAuth voor usage stats)
- De CLI-aanpak is consistent met alle bestaande functionaliteit
- Performance verschil is acceptabel (2-5s vs <1s) voor een achtergrond-operatie

Dit kan later geoptimaliseerd worden als direct API-toegang wordt toegevoegd.
