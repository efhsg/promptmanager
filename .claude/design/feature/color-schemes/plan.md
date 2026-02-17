# Color Schemes — Technisch Plan

## Overzicht

We gebruiken CSS custom properties (variabelen) op de `<body>` tag om kleuren dynamisch te schakelen. Dit is de lichtste aanpak: geen extra CSS-bestanden, geen build-stap, en werkt met het bestaande Spacelab-thema.

## Architectuur

```
Request → Layout (_base.php)
  ├── ColorSchemeService::getEffectiveScheme(userId, projectId)
  │     ├── Project.color_scheme (als niet null)
  │     └── UserPreferenceService.getValue(userId, 'color_scheme')
  └── <body class="color-scheme-{id}"> + inline CSS variables
```

## Implementatie-stappen

### Stap 1: ColorScheme enum

**Bestand**: `yii/common/enums/ColorScheme.php`

```php
enum ColorScheme: string
{
    case DEFAULT = 'default';
    case GREEN = 'green';
    case RED = 'red';
    case PURPLE = 'purple';
    case ORANGE = 'orange';
    case DARK = 'dark';
    case TEAL = 'teal';

    public function label(): string { ... }
    public function navbarColor(): string { ... }
    public function primaryColor(): string { ... }
    public static function labels(): array { ... }
}
```

Elke case definieert:
- `label()` — display naam
- `navbarColor()` — hex kleur voor navbar background
- `primaryColor()` — hex kleur voor Bootstrap `--bs-primary` override

### Stap 2: Migratie — project.color_scheme kolom

**Bestand**: `yii/migrations/m260217_000001_add_color_scheme_to_project.php`

```sql
ALTER TABLE project ADD COLUMN color_scheme VARCHAR(32) NULL DEFAULT NULL AFTER label;
```

- Nullable: `null` = gebruik user-default
- Validatie: `in` rule met `ColorScheme::values()`
- Runnen op zowel `yii` als `yii_test` schema's

### Stap 3: ColorSchemeService

**Bestand**: `yii/services/ColorSchemeService.php`

```php
class ColorSchemeService
{
    private UserPreferenceService $userPreference;
    private const PREF_KEY = 'color_scheme';

    public function getEffectiveScheme(int $userId, ?Project $project = null): ColorScheme
    {
        // 1. Project override (als niet null)
        if ($project !== null && $project->color_scheme !== null) {
            return ColorScheme::tryFrom($project->color_scheme) ?? ColorScheme::DEFAULT;
        }

        // 2. User preference
        $userScheme = $this->userPreference->getValue($userId, self::PREF_KEY);
        return ColorScheme::tryFrom($userScheme ?? '') ?? ColorScheme::DEFAULT;
    }

    public function setUserScheme(int $userId, ColorScheme $scheme): void
    {
        $this->userPreference->setValue($userId, self::PREF_KEY, $scheme->value);
    }

    public function getUserScheme(int $userId): ColorScheme
    {
        $value = $this->userPreference->getValue($userId, self::PREF_KEY);
        return ColorScheme::tryFrom($value ?? '') ?? ColorScheme::DEFAULT;
    }
}
```

Registreren in DI container (`config/main.php`).

### Stap 4: Project model aanpassen

**Bestand**: `yii/models/Project.php`

Toevoegen:
- Validation rule: `[['color_scheme'], 'in', 'range' => ColorScheme::values()]`
- Attribute label: `'color_scheme' => 'Color Scheme'`

### Stap 5: CSS variabelen en theming

**Bestand**: `yii/web/css/color-schemes.css` (nieuw, ~60 regels)

```css
/* Default scheme (Spacelab blauw) - geen overrides nodig */

/* Green scheme */
body.color-scheme-green .navbar.bg-primary { background-color: #2e8b57 !important; }
body.color-scheme-green .btn-primary { background-color: #2e8b57; border-color: #2e8b57; }
body.color-scheme-green .btn-outline-primary { color: #2e8b57; border-color: #2e8b57; }
body.color-scheme-green .btn-outline-primary:hover { background-color: #2e8b57; border-color: #2e8b57; }
body.color-scheme-green a:not(.btn) { color: #2e8b57; }
/* ... etc voor elke kleur */
```

Toevoegen aan `AppAsset::$css`.

**Alternatief met CSS custom properties** (compacter):

```css
body.color-scheme-green {
    --pm-primary: #2e8b57;
    --pm-primary-hover: #267347;
    --pm-primary-rgb: 46, 139, 87;
}

.navbar.bg-primary { background-color: var(--pm-primary, #3b6dbc) !important; }
.btn-primary { background-color: var(--pm-primary, #3b6dbc); border-color: var(--pm-primary, #3b6dbc); }
/* etc. */
```

→ We kiezen de **CSS custom properties** aanpak — compacter en beter onderhoudbaar.

### Stap 6: Layout aanpassen (_base.php)

**Bestand**: `yii/views/layouts/_base.php`

```php
// In _base.php, vóór <body>:
$colorScheme = ColorScheme::DEFAULT;
if (!Yii::$app->user->isGuest) {
    $project = Yii::$app->projectContext->getCurrentProject();
    $colorScheme = Yii::$app->colorSchemeService->getEffectiveScheme(
        Yii::$app->user->id,
        $project
    );
}
$schemeClass = $colorScheme === ColorScheme::DEFAULT ? '' : 'color-scheme-' . $colorScheme->value;
```

```html
<body class="d-flex flex-column h-100 <?= $schemeClass ?>">
```

### Stap 7: User kleurenschema-selector (navbar)

**Optie A — Inline in user dropdown** (aanbevolen):
- Extra dropdown-items in het user-menu (navbar rechtsboven)
- Elke kleur als item met een gekleurd bolletje
- AJAX POST naar `/user/set-color-scheme` → pagina herlaadt

**Bestand**: `yii/views/layouts/main.php` — user dropdown uitbreiden:

```php
// Na "Change password" link:
'<hr class="dropdown-divider">',
['label' => '<i class="bi bi-palette"></i> Color Scheme', 'url' => '#', 'items' => [
    // Submenu per kleur met gekleurde indicators
]],
```

**Optie B — Aparte preferences pagina**:
- Route: `/user/preferences`
- Form met color scheme dropdown + eventuele andere preferences
- Meer uitbreidbaar, maar extra pagina

→ We kiezen **Optie A** (navbar dropdown) voor directe toegankelijkheid.

### Stap 8: Controller action voor user color scheme

**Bestand**: `yii/controllers/UserController.php` (of `IdentityController`)

```php
public function actionSetColorScheme(): Response
{
    // AJAX POST
    // Valideer color_scheme waarde via enum
    // Sla op via ColorSchemeService
    // Return JSON success
}
```

### Stap 9: Project formulier uitbreiden

**Bestanden**:
- `yii/views/project/_form.php` — color_scheme dropdown toevoegen
- Dropdown met alle `ColorScheme::labels()` + "Use default" prompt

```php
<?= $form->field($model, 'color_scheme')->dropDownList(
    ColorScheme::labels(),
    ['prompt' => 'Use default (user setting)']
) ?>
```

### Stap 10: Tests

**Tests voor ColorSchemeService**:
- `testReturnsDefaultWhenNoPreferenceSet`
- `testReturnsUserPreferenceWhenSet`
- `testProjectOverridesUserPreference`
- `testReturnsDefaultWhenProjectSchemeIsNull`
- `testSetUserScheme`

**Tests voor ColorScheme enum**:
- `testAllCasesHaveLabels`
- `testAllCasesHaveColors`

## Bestandsoverzicht

| Actie | Bestand | Omschrijving |
|---|---|---|
| **Nieuw** | `yii/common/enums/ColorScheme.php` | Enum met kleurenschema's |
| **Nieuw** | `yii/services/ColorSchemeService.php` | Service voor effectief schema |
| **Nieuw** | `yii/web/css/color-schemes.css` | CSS variabelen per schema |
| **Nieuw** | `yii/migrations/m26...add_color_scheme_to_project.php` | DB migratie |
| **Nieuw** | `yii/tests/unit/services/ColorSchemeServiceTest.php` | Unit tests |
| **Nieuw** | `yii/tests/unit/common/enums/ColorSchemeTest.php` | Enum tests |
| **Wijzig** | `yii/models/Project.php` | color_scheme veld + rules |
| **Wijzig** | `yii/views/layouts/_base.php` | Body class op basis van schema |
| **Wijzig** | `yii/views/layouts/main.php` | Kleurkiezer in user dropdown |
| **Wijzig** | `yii/views/project/_form.php` | Color scheme dropdown |
| **Wijzig** | `yii/assets/AppAsset.php` | color-schemes.css toevoegen |
| **Wijzig** | `yii/config/main.php` | ColorSchemeService in DI |
| **Wijzig** | Controller (user/identity) | AJAX action voor kleurkeuze |

## Vragen / Keuzes

1. **Welke kleuren precies?** — De 7 genoemde schema's, of andere/meer/minder?
2. **Navbar-only of ook accent-kleuren?** — Plan gaat uit van navbar + primary buttons/links
3. **User dropdown vs. aparte preferences pagina?** — Plan kiest dropdown (direct, geen extra pagina)
4. **Spacelab base behouden of volledige Bootswatch themes?** — Plan kiest Spacelab + kleur-overrides (lichter, minder CSS)
