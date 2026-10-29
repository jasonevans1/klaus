# Architecture

Klaus is a Laravel 13 + Livewire 4 app for planning Christmas and birthday gifts, shared by one household (two users). Product plan and build order: `docs/v1-plan.md`.

## Directory Structure
- `app/Models/` - Eloquent models (Household, User, Recipient, Occasion, Gift)
- `app/Enums/` - Backed enums (RecipientGroup, GiftStatus, OccasionType)
- `app/Actions/` - Single-purpose classes that change data; `app/Actions/Fortify/` holds auth actions
- `app/Repositories/` - All reads of household data (queries, lists)
- `app/Services/` - Calculations that combine data, e.g. budget/spent/remaining totals
- `app/Livewire/Forms/` - Livewire Form objects
- `app/Livewire/Actions/` - Small invokable Livewire helpers (e.g. Logout)
- `resources/views/pages/` - Livewire 4 single-file page components (`⚡*.blade.php`)
- `resources/views/components/`, `resources/views/layouts/` - Blade/Flux UI
- `routes/web.php` - App routes (behind `auth` + `verified`); `routes/settings.php` - account settings
- `database/factories/`, `database/migrations/`, `database/seeders/`
- `tests/Unit/`, `tests/Feature/`, `tests/Acceptance/` - see `testing.md`

## Patterns Used
Each pattern has one job. Pick by what the code does:

- **Action classes** (`app/Actions/`): one write operation each, with a single public `handle()` method. Examples: `AddGift`, `AdvanceGiftStatus`, `StartNewChristmas`. Actions receive validated data and the acting user, and are resolved from the container.
- **Repositories** (`app/Repositories/`): the only place that queries household data. They take the acting user and always scope to their household and exclude other users' private gifts. Components and Services never build these queries themselves. Concrete classes; no interfaces.
- **Service classes** (`app/Services/`): read-only calculations over data from Repositories, e.g. `BudgetSummary` for per-person and season Budget/Spent/Remaining and the owner's "My private gifts" line.
- **Livewire Form objects** (`app/Livewire/Forms/`): form state and validation rules for forms with several fields (person, gift). The component calls `$this->form->validate()` and passes the result to an Action.
- **Livewire components** stay thin: they hold UI state, call Repositories/Services to read and Actions to write.

## Domain Rules
- Everything belongs to a household; both users see the same data.
- Money is stored as integer cents. Spent and remaining are always calculated from gifts, never stored or typed in.
- A gift with `is_private` is visible only to its `created_by_user_id`. It is excluded from every query, list and total for other users, and never feeds shared totals. The owner sees it only in a separate "My private gifts" line.
- A gift with a null `occasion_id` is a year-round idea.
- A person's history is their gifts on earlier occasions; there is no history table.
- Joint recipients (e.g. "The Smiths") are ordinary recipients with `is_shared`; their gifts count once.

## Conventions
- One class per file
- Tests mirror the domain, not the class tree
- Authorization: household membership checked in Repositories (reads) and Actions (writes); policies only if a rule goes beyond membership
- Database constraints (foreign keys, unique indexes such as one birthday occasion per recipient) back up app rules

## Key Integrations
- Auth: Laravel Fortify (email + password, email verification, 2FA, passkeys from the starter kit)
- UI: Flux (free) components with Tailwind 4 via Vite
- Local env: ddev (PHP 8.5, MariaDB 11.8, https://klaus.ddev.site)
- CI and maintenance: laravel-factory workflows (`.github/workflows/factory-*.yml`)
- Hosting: Laravel Cloud (klaus.jasonevans.xyz), deployed by the factory deploy workflow
