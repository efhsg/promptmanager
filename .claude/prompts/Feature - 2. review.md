## Review een ontwerp

**Document**: GEN:{{File}}

GEN:{{Review action}}

## Proces

1. **Lees** het plan document
2. **Vergelijk** met bestaande patronen in de codebase
3. **Onderzoek** vergelijkbare bestaande implementaties om alignment te verifiëren
4. **Check** kwaliteitscriteria
5. **Rapporteer** bevindingen en verbeter indien gevraagd

## Kwaliteitscriteria

### Structuur

- [ ] Doel/samenvatting duidelijk beschreven
- [ ] Stappen logisch geordend en genummerd
- [ ] Bestanden overzicht aanwezig (nieuw/gewijzigd)
- [ ] Verificatie sectie met concrete commands
- [ ] Referentie bestanden/bronnen sectie

### Volledigheid

- [ ] Alle requirements uit specificatie zijn afgedekt
- [ ] Geen ontbrekende stappen tussen input en gewenst resultaat
- [ ] Dependencies en volgorde zijn duidelijk
- [ ] Edge cases en error scenarios overwogen
- [ ] Het het plan is conform de stijl en code standaards van de code base
- [ ] Er is getoetst dat de oplossing zo goed mogelijk aansluit op bestaande oplossingen

### Implementeerbaarheid

- [ ] Stappen zijn concreet (geen "implementeer X" zonder details)
- [ ] Method signatures zijn compleet waar relevant
- [ ] Algoritmes zijn beschreven, niet alleen benoemd
- [ ] Benodigde data/input is gespecificeerd

### Consistentie met codebase

- [ ] Volgt bestaande architectuur en patronen
- [ ] Namespace/folder paden zijn correct
- [ ] Naming conventions worden gevolgd
- [ ] Hergebruikt bestaande componenten waar mogelijk

### Tests

- [ ] Test scenarios dekken happy path
- [ ] Test scenarios dekken edge cases
- [ ] Test bestandspaden volgen project structuur

## Output format

```markdown
# Review: [document naam]

## Score
[X/10] - [korte samenvatting]

## Bevindingen

### Goed
- [wat goed is]

### Verbeterpunten
| Issue | Locatie | Suggestie |
|-------|---------|-----------|
| [probleem] | [stap/sectie] | [hoe te verbeteren] |

### Open vragen
- [vragen die beantwoord moeten worden voordat implementatie kan starten]

## Verbeterd document

[Alleen indien "Review en verbeter" geselecteerd: het volledige verbeterde document]
```

Presenteer de review en sluit af met:

Geen verbeterpunten — door naar implementatie / Aanpassen?

**Wacht op gebruikersinput. Ga NIET door totdat de gebruiker reageert.**