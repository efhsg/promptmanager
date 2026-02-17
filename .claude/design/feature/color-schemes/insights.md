# Color Schemes — Inzichten & Beslissingen

## Codebase-analyse

### Bestaande infrastructuur die we hergebruiken

1. **UserPreferenceService** (`yii/services/UserPreferenceService.php`)
   - Key-value store per user (`user_preference` tabel)
   - Al in gebruik voor `default_project_id`
   - Perfecte fit voor `color_scheme` preference
   - Geen schema-wijziging nodig — gewoon een nieuwe pref_key

2. **ProjectContext** (`yii/components/ProjectContext.php`)
   - Geeft ons het actieve project in de layout
   - Prioriteitslogica (URL → Session → Preference) al opgelost
   - We hoeven alleen `getCurrentProject()` aan te roepen voor het kleurenschema

3. **Project model** — heeft al JSON-opslag (`ai_options`), maar voor een simpele string-waarde is een dedicated kolom schoner

### Huidige styling-architectuur

- **Spacelab** Bootswatch thema als base (`spacelab.min.css`, 232KB)
- **Geen CSS preprocessor** — directe CSS-bestanden
- **Geen CSS custom properties** op dit moment
- **Hardcoded kleuren** in `site.css`, `mobile.css`, `ai-chat.css`
- **Bootstrap 5** class-gebaseerd: `navbar-dark bg-primary`

### Ontwerpbeslissingen

| Beslissing | Keuze | Reden |
|---|---|---|
| CSS-strategie | Custom properties op `<body>` | Compact, onderhoudbaar, geen extra bestanden per thema |
| Opslag user | `user_preference` tabel | Infra al aanwezig, geen migratie nodig |
| Opslag project | Dedicated `color_scheme` kolom | Schoner dan JSON, queryable, nullable = "use default" |
| UI user-keuze | Navbar dropdown submenu | Direct toegankelijk, geen extra pagina |
| Kleurbereik | Navbar + primary elementen | 80% visueel effect met minimale CSS-wijzigingen |

### Risico's en mitigatie

| Risico | Mitigatie |
|---|---|
| CSS specificity-conflicten met Spacelab | `body.color-scheme-X` selector is specifiek genoeg, `!important` alleen op navbar |
| Hardcoded kleuren in bestaande CSS | Alleen navbar en primary elementen overriden; rest blijft Spacelab |
| Performance (extra CSS) | Eén klein CSS-bestand (~60 regels), verwaarloosbaar |
| Leesbaarheid: kleur in donkere navbar | Alle schema's gebruiken `navbar-dark` (witte tekst), kleuren zijn donker genoeg |

### Niet in scope (nu)

- Volledig dark mode (vraagt veel meer CSS-wijzigingen)
- Custom hex-kleur picker per user/project
- Per-pagina kleurenschema
- Logo-kleur aanpassing per schema
