# Repository Guidelines

## Project Structure & Module Organization
- Core PHP/Yii2 backend lives in `yii/`; controllers, modules, services, and widgets follow Yii’s default PSR-4 namespaces (`app\`, `modules\identity\`, etc.).
- Vue-free UI assets and compiled Quill bundles are checked into `yii/web`, while the Quill build tooling sits in `npm/` with `src/js` for custom editors.
- Database seed files and local DB volumes live under `data/db`, and Docker resources (PHP-FPM, Nginx, MySQL) live in `docker/` alongside the orchestrating `docker-compose.yml`.
- Tests reside in `yii/tests` (unit/functional/acceptance) and in module-specific locations such as `yii/modules/<module>/tests`.

## Build, Test, and Development Overview
- The application is fully containerized; PHP, NPM, web server, and database services run through Docker for a stable development environment.
- PHP vendor dependencies are installed inside the PHP container; front-end assets are built using the NPM container.
- Application and test schemas rely on Yii’s migration system to stay aligned.
- Front-end bundles (Quill UMD, syntax highlighting, helper scripts) are built through the front-end tooling.
- The application’s development entrypoint is accessible locally once containers and schema are available.

## Coding Style & Naming Conventions
- PHP follows PSR-12 (4-space indent, `declare(strict_types=1)` when feasible). Controllers remain lean, delegating orchestration to services under `yii/services`.
- PHP class names use StudlyCase (e.g., `PromptTemplateService`), while view files follow snake-case naming in `yii/views/<controller>/`.
- JavaScript in `npm/src` follows ES2019 conventions with 2-space indentation.
- Docker and Compose files use clean 2-space YAML indentation; new environment variables must be documented in `.env.example`.

## Commit & Pull Request Guidelines
- Commit messages follow the existing convention:  
  `Add: ...` for new features  
  `Change: ...` for refactors  
  `Fix: ...` for bug patches  
  Keep commit messages concise (~70 characters).
- Pull requests should provide context, reference an issue, and clearly describe the intended behavior.
- Visual or UI-impacting changes should include screenshots or GIFs.
- Any new environment variables, migration IDs, or Docker-related adjustments must be noted in the PR so operators can apply them consistently.
