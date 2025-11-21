# Generate Tests Prompt

Generate Codeception (PHPUnit style) tests for the selected code:

- Use the Arrange–Act–Assert structure with clear separation.
- Write small, direct, readable tests.
- Avoid data providers unless explicitly beneficial.
- Use existing fixtures/helpers from the project when possible.
- Mock behaviour only when external dependencies require it.
- Test observable behaviour and public APIs, not private internals.
- Preserve existing comments; do not add new comments unless necessary to clarify behaviour.
- Follow existing naming conventions (e.g. `SomethingServiceTest`).

Output only the test class code.
