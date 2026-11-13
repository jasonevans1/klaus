# Klaus

A gift planner for Christmas and birthdays, shared by one household. It replaces the yearly budget spreadsheet: budgets per person, gift ideas all year, and a record of what was given in past years.

Live at [klaus.jasonevans.xyz](https://klaus.jasonevans.xyz). Product plan: [`docs/v1-plan.md`](docs/v1-plan.md).

## Features

- **Occasions**: one Christmas and one Birthdays occasion per year. A new Christmas copies last year's people and budgets.
- **People**: name, group (Family, Friends, Teachers, Coworkers), birthday, interests and past gifts. Shared entries like "The Smiths" have their own budget.
- **Gifts**: title, price, store, link and buyer, moving through Idea → Bought → Wrapped → Given. Spent and remaining are calculated from gifts.
- **Ideas**: a year-round idea list per person, assigned to an occasion later.
- **Private gifts**: hidden from the other household member and left out of shared totals.
- **Screens**: occasion overview, shopping list, wrapping checklist, and reminders for upcoming birthdays and people with no gift yet.
- **Household**: both users see the same data; the second user joins by invitation link.

## Tech stack

PHP 8.5, Laravel 13, Livewire 4, Flux, Fortify, Tailwind 4. Tests use Pest and Playwright. Linting uses Pint, Larastan (level 7) and Rector. Local development runs in [ddev](https://ddev.com) with MariaDB.

## Getting started

Requires ddev and Docker.

```bash
cp .env.example .env
ddev start                  # ddev writes its database settings into .env
ddev composer install
ddev exec php artisan key:generate
ddev exec php artisan migrate --seed
ddev npm install
ddev npm run dev
```

Open https://klaus.ddev.site. The seeder creates `test@example.com`.

## Testing and linting

```bash
ddev exec php artisan test --parallel   # Pest
ddev exec vendor/bin/pint --parallel    # format
ddev composer test                      # everything CI runs: Pint, PHPStan, tests
npm run e2e                             # Playwright smoke tests (on the host, against klaus.ddev.site)
```

### Acceptance tests

Each feature plan gets 1–3 acceptance scenarios in `tests/Acceptance/{Plan}/`. They are Pest tests written in gift-planning terms ("one click advances a gift"), using the shared helpers in `tests/Acceptance/Support/` (`Dsl.php`, `Expectations.php`).

1. During plan review, the `acceptance-spec-writer` agent drafts the scenarios and a person approves them.
2. The `acceptance-lock` agent checks that they fail for the right reason, then records a hash of each file in the plan's `.acceptance-lock`.
3. While the plan is active, the hook in `.claude/hooks/acceptance-specs.php` blocks agents from editing locked files, and `tests/Feature/AcceptanceSpecsTest.php` fails if any locked file has changed.
4. The plan is done when its scenarios pass. They stay in the suite as regression tests and are never edited.

```bash
ddev exec php artisan test tests/Acceptance/{Plan}   # one plan's scenarios
```

## Project layout

| Path | Contents |
| --- | --- |
| `app/Actions` | Writes |
| `app/Repositories` | Reads (household-scoped, private gifts filtered) |
| `app/Services` | Calculations such as totals and remaining budget |
| `app/Livewire/Forms` | Validation |
| `app/Models`, `app/Enums` | Eloquent models and backed enums |
| `resources/views/pages/` | Livewire single-file page components (`⚡*.blade.php`) |
| `tests/Feature/`, `tests/Acceptance/`, `tests/e2e/` | Feature tests, locked acceptance scenarios, Playwright specs |

Money is stored as integer cents.

## Development workflow

Features are planned and built with [HCF](https://github.com/markshust/hcf) (`hcf:plan-create`, then `hcf:plan-orchestrate`). [laravel-factory](https://github.com/jasonevans1/laravel-factory) runs CI and the maintenance loops and deploys to Laravel Cloud; it owns `.github/workflows/factory-*.yml`. See [`CLAUDE.md`](CLAUDE.md) and `.claude/` for conventions.
