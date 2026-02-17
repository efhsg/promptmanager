# Color Schemes — Functionele Specificatie

## Doel

De PromptManager wordt op meerdere servers en voor verschillende projecten gebruikt. Om instanties en projecten visueel te onderscheiden, willen we kleurenschema's toevoegen die de navbar-kleur en accent-kleuren aanpassen.

## Gebruikersscenario's

### 1. User-niveau kleurenschema (default)
- Een gebruiker kiest een kleurenschema in het user-dropdown menu (navbar, rechtsboven)
- Dit schema geldt als default voor alle pagina's en projecten
- Wordt opgeslagen als `user_preference` met key `color_scheme`

### 2. Project-niveau kleurenschema (override)
- Per project kan een kleurenschema ingesteld worden in de project-instellingen
- Als een project een schema heeft, overschrijft dit het user-default
- Als een project geen schema heeft (`null`), wordt het user-default gebruikt
- Wordt opgeslagen als kolom `color_scheme` op de `project`-tabel

### Prioriteitsvolgorde
```
Project color_scheme (als niet null) → User preference color_scheme → Standaard (spacelab/blauw)
```

## Beschikbare kleurenschema's

Gebaseerd op Bootswatch thema's (Bootstrap 5), die al het formaat matchen van het huidige Spacelab thema:

| ID | Naam | Navbar kleur | Omschrijving |
|---|---|---|---|
| `default` | Spacelab (Blauw) | `#3b6dbc` | Huidig thema, geen wijziging nodig |
| `green` | Groen | `#2e8b57` | Natuur/groen accent |
| `red` | Rood | `#c0392b` | Rood/warm accent |
| `purple` | Paars | `#6f42c1` | Paars accent |
| `orange` | Oranje | `#e67e22` | Oranje/warm accent |
| `dark` | Donker | `#343a40` | Donker/neutraal accent |
| `teal` | Teal | `#20c997` | Teal/cyaan accent |

## UI-ontwerp

### 1. User kleurenschema-selector
- **Locatie**: User dropdown menu (navbar rechtsboven), onder "Change password"
- **Interactie**: Dropdown-item "Color Scheme" → opent submenu met kleurkeuzes
- **Alternatief (eenvoudiger)**: Aparte pagina `/user/preferences` met kleurkiezer
- **Opslaan**: Direct via AJAX-call, pagina herlaadt voor effect

### 2. Project kleurenschema-veld
- **Locatie**: Project formulier (create/update), nieuw veld
- **Widget**: Dropdown-select met kleuropties + "Use default" optie (null-waarde)
- **Visueel**: Elke optie toont een kleurbolletje/swatch naast de naam

### 3. Visueel effect
- De navbar (`bg-primary`) verandert van kleur op basis van het actieve schema
- Accent-kleuren (links, knoppen) passen mee aan
- De rest van de layout blijft hetzelfde (Spacelab base)
