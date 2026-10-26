# Code Standards

## Style Guide
Laravel Pint, `laravel` preset (PSR-12 based), configured in `pint.json`. Rector (`rector.php`) and Larastan at level 7 (`phpstan.neon`) run in CI through laravel-factory.

## Linting
```bash
# Check for issues
ddev exec vendor/bin/pint --parallel --test

# Auto-fix issues
ddev exec vendor/bin/pint --parallel

# Static analysis (new errors fail CI)
ddev exec vendor/bin/phpstan analyse

# Automated refactors (review the diff)
ddev exec vendor/bin/rector process --dry-run
```

## Pre-commit Checks
- `ddev composer test` passes (Pint, PHPStan, full test suite)
- No new PHPStan errors and no new baseline entries
- Never edit locked acceptance scenarios or `tests/Acceptance/Support/Expectations.php`

## Naming Conventions
- Classes: PascalCase (`AdvanceGiftStatus`, `GiftRepository`)
- Methods: camelCase
- Variables: camelCase
- Constants and enum cases: PascalCase enum cases (`GiftStatus::Bought`); class constants in SCREAMING_SNAKE_CASE
- Database: snake_case plural tables, `*_cents` for money columns, `is_*` for booleans
- Livewire pages: single-file components named `⚡{name}.blade.php` under `resources/views/pages/{area}/`
- Tests: `{Subject}Test.php` with Pest `it('...')`

## PHP Rules
- `declare(strict_types=1);` in new PHP class files
- Typed properties, parameters and return types everywhere
- Backed enums for fixed sets (gift status, occasion type), cast on the model
- Money is an `int` of cents. Format only in views.
- Validate at the edge (Livewire Form objects or component rules), never trust request input in Actions.
- No `env()` outside `config/`.
