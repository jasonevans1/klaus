# Testing Configuration

## Test Framework
Pest 5 (`pestphp/pest`, `pestphp/pest-plugin-laravel`) on PHPUnit, run inside ddev (PHP 8.5).

## TDD Methodology

Each task follows strict Red → Green → Refactor:

1. Write failing test for one requirement
2. Write minimum code to pass
3. Refactor while tests stay green
4. Repeat for next requirement
5. Commit when task complete

## Commands
All commands run inside the ddev web container. Start it first with `ddev start`.

```bash
# Run all tests (parallel)
ddev exec php artisan test --parallel

# Run all tests (sequential, for debugging failures)
ddev exec php artisan test

# Run specific test file
ddev exec php artisan test tests/Feature/Path/To/SomethingTest.php

# Run one test by name
ddev exec php artisan test --filter="advances a gift to bought"

# Run with coverage (no coverage driver by default; enable Xdebug first)
ddev xdebug on && ddev exec php artisan test --coverage; ddev xdebug off

# Full local CI check: Pint, PHPStan, then tests
ddev composer test
```

## Parallel Execution
- **Default**: Always run tests in parallel unless debugging a specific failure
- Parallel command: `ddev exec php artisan test --parallel`
- Sequential fallback: `ddev exec php artisan test` (use only when parallel causes flaky failures)

## Test File Locations
- Unit tests: `tests/Unit/` (pure logic with no app boot, e.g. money formatting)
- Feature tests: `tests/Feature/`, organized by domain (e.g. `Gifts/`, `Occasions/`, `People/`, `Households/`). Starter-kit tests live in `Auth/` and `Settings/`.
- Acceptance scenarios (Pest): `tests/Acceptance/{Plan}/`, domain helpers in `tests/Acceptance/Support/`

## Acceptance Scenarios
Written by the `acceptance-spec-writer` agent at plan review, approved by the human, then locked by `acceptance-lock` before implementation (`.claude/hooks/acceptance-specs.php`). Locked files cannot be edited by agents until the plan is `completed`.

```bash
# Run one plan's scenarios
ddev exec php artisan test tests/Acceptance/{Plan}

# Task-level runs exclude scenarios that are still red by design
ddev exec php artisan test --parallel --exclude-testsuite=Acceptance
```

The full suite (`ddev exec php artisan test --parallel`) includes the scenarios and `tests/Feature/AcceptanceSpecsTest.php`, so a plan cannot commit until its scenarios pass and its locked files are unchanged.

## Database
- SQLite in-memory for tests (configured in `phpunit.xml`); MariaDB 11.8 in ddev for local dev.
- `RefreshDatabase` is applied to `Feature` and `Acceptance` in `tests/Pest.php`; don't add it per file.
- Build data with model factories. Every factory that creates household-owned data must attach it to a household.

## What to Test
- Livewire components through `Livewire::test()` (actions, validation errors, rendered values). Use HTTP tests for routes, redirects and auth.
- Household isolation: a user never sees another household's data.
- Private gifts: hidden from the other household member in every list and total, and never counted in shared Budget/Spent/Remaining.
- Money: stored and asserted as integer cents.

## Coverage Requirements
- No minimum percentage. Every requirement starts as a failing test (TDD), and laravel-factory's mutation loop measures test strength.
- New code must have tests.

## Test Naming Convention
- Test files: `{Subject}Test.php`
- Tests: Pest `it('does something')` written in user terms, e.g. `it('hides a private gift from my partner')`
