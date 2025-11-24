# Project Context & AI Guidelines

## 1. Tech Stack & Architecture
- **Backend:** PHP 8.x (Yii2 Framework). strict types enabled.
- **Frontend:** Server-side rendered PHP Views (`.php`). **NO Vue/React/Angular.**
- **Rich Text:** Quill.js (Custom build in `npm/`).
- **Database:** MySQL (Volume: `data/db`).
- **Infrastructure:** Docker & Docker Compose.

## 2. Directory Map (Strict)
- `yii/`: Core PHP application.
    - `controllers/`, `models/`, `views/`: Standard Yii2 structure.
    - `modules/identity/`: Module-specific logic.
    - `services/`: Business logic & orchestration (Lean controllers delegating here).
    - `tests/`: Unit/Functional/Acceptance tests.
- `yii/web/`: **READ ONLY.** Compiled assets target this folder. Do not edit CSS/JS here directly.
- `npm/`: Source for frontend assets.
    - `src/js`: Custom editor logic.
    - Edit source files here, then run build tools.
- `docker/`: Container configurations (Nginx, PHP-FPM).

## 3. Coding Standards
- **PHP:** PSR-12. Use `declare(strict_types=1);`.
- **Naming:**
    - Classes: `StudlyCase` (e.g., `PromptTemplateService`).
    - Views: `snake-case` (e.g., `yii/views/site/index.php`).
- **JavaScript:** ES2019+, 2-space indent.
- **Patterns:** Keep Controllers thin. Move logic to `yii/services`.

## 4. Execution & Testing (CRITICAL)
**Always run commands via Docker Compose.** Do not run `php` or `npm` natively on the host.

- **Run Tests:**
  `docker compose exec php vendor/bin/codecept run`
- **Run Migrations:**
  `docker compose exec php yii migrate`
- **Build Frontend:**
  `docker compose run --rm npm run build`
- **Restart Backend:**
  `docker compose restart php`

## 5. Commit Conventions
Prefix commits strictly:
- `Add: ...` (New features)
- `Change: ...` (Refactors/modification)
- `Fix: ...` (Bug patches)
