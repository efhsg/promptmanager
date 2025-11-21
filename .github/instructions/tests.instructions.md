# Test Instructions

Apply these rules when generating or modifying tests in this repository, in addition to the global project and PHP instructions.

## Framework and Structure

- Use Codeception in PHPUnit style.
- Follow the existing directory structure (`tests/unit`, `tests/functional`, etc.).
- Name test classes consistently (e.g. `SomethingServiceTest`).

## Style and Readability

- Use the Arrange–Act–Assert structure with clear separation.
- Prefer direct, readable tests over clever abstractions.
- Do not introduce data providers unless they genuinely simplify repeated test cases.

## Dependencies and Fixtures

- Use existing fixtures and helpers when available.
- Use mocks/stubs only when external dependencies make tests slow or complex.
- Mock behaviour, not implementation details.

## Comments and PHPDoc

- Do not introduce comments unless necessary to clarify non-obvious business rules.
- Avoid PHPDoc on test methods unless explicitly required.
