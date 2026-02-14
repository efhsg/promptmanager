# Custom Buttons in Prompts

Gebruik choice buttons om gebruikers expliciete keuzes te geven in workflows en prompts. PromptManager detecteert deze automatisch aan het einde van een Claude response en rendert klikbare knoppen.

## Preferred syntax: slash-separated

```
Optie1 / Optie2 / Optie3?
```

Dit is de meest natuurlijke en compacte vorm. De regel moet de **laatste non-empty line** van de response zijn.

### Voorbeelden

```
Implementatie / Review ronde / Handmatig bewerken?
```

```
Post / Bewerk / Skip?
```

```
Akkoord met de aanpak? (Ja / Nee / Aanpassen)
```

### Regels slash-syntax

| Regel | Detail |
|-------|--------|
| Separator | ` / ` (spatie-slash-spatie) |
| Aantal opties | 2-4 |
| Max lengte per optie | 30 tekens |
| Parentheses | Optioneel: `vraag? (A / B / C)` |
| Trailing `?` | Optioneel, wordt gestript |

## Alternatieve syntax: bracket-letter

```
[I] Start implementatie
[R] Nog een review ronde
[E] Handmatig bewerken
```

Gebruikt wanneer opties een letter-code nodig hebben of langer zijn dan 30 tekens. De bracket-lines moeten de **laatste opeenvolgende non-empty lines** zijn.

Inline variant op één regel wordt ook herkend:

```
[I] Start implementatie [R] Nog een review ronde [E] Handmatig bewerken
```

### Regels bracket-syntax

| Regel | Detail |
|-------|--------|
| Patroon | `[X] Beschrijving` (hoofdletter) |
| Aantal opties | 2-5 |
| Max lengte beschrijving | 40 tekens |
| Positie | Opeenvolgende regels aan eind response, of inline op de laatste regel |

## Gebruik in prompts

Plaats buttons altijd:
1. Als **laatste regels** van de response (geen tekst erna)
2. Na een samenvatting of vraag
3. Gevolgd door een expliciete wachtinstructie (in de prompt, niet in output)

### Prompt patroon

```markdown
Toon aan de gebruiker:

{Samenvatting}

Implementatie / Review ronde / Handmatig bewerken?

**Wacht op gebruikersinput. Ga NIET door totdat de gebruiker reageert.**
```

## Edit-detectie

Opties met deze woorden krijgen automatisch een "edit" actie (paarse button, opent editor):
`bewerk`, `edit`, `aanpassen`, `modify`, `adjust`

Alle andere opties sturen de label-tekst als reply.

## Best practices

| Regel | Voorbeeld |
|-------|-----------|
| Concrete acties | `Start implementatie` niet `Ga verder` |
| Slash preferred | `Post / Bewerk / Skip?` boven `[A] Post [B] Bewerk` |
| Max 4 opties (slash) | Meer past niet; gebruik bracket voor 5 |
| Altijd wacht-instructie | In de prompt, voorkomt dat agent doorgaat |

## Anti-patterns

**Fout — tekst na buttons:**
```
Implementatie / Review?
Laat me weten wat je wilt.
```
(Buttons worden niet gedetecteerd — ze zijn niet de laatste regels)

**Goed — buttons als laatste:**
```
Laat me weten wat je wilt.

Implementatie / Review?
```

**Fout — te veel opties (slash):**
```
A / B / C / D / E?
```
(Max 4 voor slash-syntax; gebruik bracket voor 5)

## Zie ook

- `.claude/prompts/Spec Review Workflow.md` — uitgebreid voorbeeld
- `.claude/skills/improve-prompt.md` — checklist item A4
