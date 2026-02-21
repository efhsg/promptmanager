# Custom Buttons Toevoegen aan Prompt

Pas een bestaande prompt template aan zodat alle stop- en keuzepunten custom buttons gebruiken. **Doe niets anders.**

## Invoer

- **PROMPT**: GEN:{{File}}

## Referentie

Lees `.claude/skills/custom-buttons.md` voor de volledige syntax-regels.

## Regels

- Wijzig ALLEEN stop- en keuzepunten — raak geen andere tekst aan
- Geen formatting, spelling, structuur of inhoudelijke wijzigingen
- Geen toevoegingen buiten het custom-buttons patroon
- Behoud de oorspronkelijke taal van de prompt (Nederlands of Engels)

## Algoritme

### Stap 1: Referentie toevoegen

Zoek de sectie `Referenties`, `Rules`, of vergelijkbare inleidende sectie. Voeg daar toe:

**Nederlands:**
```
Gebruik `.claude/skills/custom-buttons.md` slash-syntax voor alle slotvragen (laatste regel van response, geen tekst na buttons).
```

**Engels:**
```
Use `.claude/skills/custom-buttons.md` slash-syntax for all closing questions (last line of response, no text after buttons).
```

Als er geen passende sectie is, voeg de regel toe direct na de eerste heading of persona-beschrijving.

### Stap 2: Stop- en keuzepunten identificeren

Zoek alle plekken waar de prompt een van deze patronen bevat:

| Patroon | Voorbeeld |
|---------|-----------|
| Expliciet STOP | `**STOP**`, `STOP —`, `stop after this phase` |
| Wacht op gebruiker | `wacht op`, `wait for`, `do not proceed`, `ga niet door` |
| Fase-overgang | `report phase completion`, `ga door naar volgende` |
| Blokkade | `blocker`, `blokkade`, `fatal` |
| Afsluiting/terminatie | `samenvatting`, `summary`, `termination`, `afsluiting` |
| Impliciete keuze | `vraag gebruiker`, `ask the user`, `confirm` |

### Stap 3: Per gevonden punt — buttons toevoegen

Bepaal de juiste buttons op basis van de context:

| Context | Buttons |
|---------|---------|
| Na diagnose/analyse | `Start {actie} / Plan aanpassen?` |
| Fase-overgang (multi-phase) | `Volgende fase starten / Review wijzigingen / Aanpassen?` |
| Blokkade | `Blokkade oplossen / Stap overslaan / Stoppen?` |
| Afsluiting met commit | `Commit wijzigingen / Review wijzigingen / Aanpassen?` |
| Afsluiting zonder commit | `Afronden / Aanpassen?` |
| Review met verbeterpunten | `Doorvoeren / Aanpassen / Overslaan?` |
| Review zonder verbeterpunten | `Geen verbeterpunten — door naar {volgende stap} / Aanpassen?` |
| Vragen aan gebruiker | `{Actie A} / {Actie B}?` (contextafhankelijk) |
| Conflicterende opties | `{Optie A} volgen / {Optie B} volgen / Aanpassen?` |

**Formaat per punt:**

```
{bestaande instructietekst}

{Button A} / {Button B} / {Button C}?

**Wacht op gebruikersinput. Ga NIET door totdat de gebruiker reageert.**
```

### Stap 4: Bestaande buttons controleren

Als een punt al buttons heeft in het juiste format:
- **Niet wijzigen** — laat het staan
- Controleer alleen of de wacht-instructie aanwezig is; voeg toe indien ontbreekt

### Stap 5: Validatie

Controleer voor elke toegevoegde button:

- [ ] Buttons zijn de **laatste regel** voor de wacht-instructie (geen tekst erna)
- [ ] Slash-syntax: ` / ` (spatie-slash-spatie) als separator
- [ ] Maximaal 4 opties per regel
- [ ] Elke optie is een concrete actie (niet "Ga verder" of "OK")
- [ ] Wacht-instructie staat direct na de buttons

## Output

Voer de wijzigingen door in [PROMPT] en toon een samenvatting:

| Plek | Toegevoegde buttons |
|------|---------------------|
| {locatie} | `{buttons}` |

{Aantal} buttons toegevoegd, {aantal} al aanwezig.

Nog een prompt aanpassen / Klaar?
