# Klaus: v1 Plan

Oct 25, 2026 · @Jason Evans

## Overview

Klaus is a Laravel + Livewire app (no Filament) for planning and tracking Christmas and birthday gifts, shared by me and my partner. It replaces the yearly Christmas budget spreadsheet: one row per person with gifts crammed into one cell, hand-entered Actual, and no history across years.

Goal: a usable v1 before Christmas 2026, built in small features one at a time. Desktop-first; it is used mostly while planning, not in the store. Repo and subdomain: klaus (klaus.jasonevans.xyz).

## Decisions

| Area | Decision |
| --- | --- |
| Users | One shared household: me and my partner, equal access to everything |
| Login | Email + password |
| Occasions | Christmas and birthdays only |
| Birthdays | Birthday occasions are created automatically from each person's birthday |
| Budgets | Per person, per occasion |
| Groups | Fixed: Family, Friends, Teachers, Coworkers |
| Profiles | Name, group, birthday, interests, past gifts from earlier years |
| Joint gifts | A shared entry (e.g. "The Smiths") is its own recipient with its own budget; cost counts once there |
| Ideas | Year-round idea list per person, assigned to an occasion later |
| Gift status | Idea → Bought → Wrapped → Given |
| Gift details | Title, price, store, product link, who bought it |
| Gift cards | Ordinary gifts with a price |
| Private gifts | Flag hides a gift from my partner; excluded from shared totals entirely; shown only to its owner as a separate "My private gifts" line |
| Reminders | In-app banners for upcoming birthdays and people with no gift yet |
| New Christmas | Copies last year's people and budgets, ready to adjust |
| Screens | Occasion overview, shopping list, wrapping checklist |

## Development workflow

Every feature is planned and built with HCF, guarded by locked acceptance scenarios, and kept healthy afterward by laravel-factory.

| Tool | Role in this project |
| --- | --- |
| [laravel-factory](https://github.com/jasonevans1/laravel-factory) | Adopted at project start with `adopt/adopt.sh`. Runs the control loops (security, Larastan, Dependabot, mutation, prod-error) that open small PRs, plus Pest, Larastan, Rector and Pint gates. Deploys to Laravel Cloud via deploy hook. |
| [HCF](https://github.com/markshust/hcf/blob/main/README.md) | `/hcf:project-setup` once. Each feature below = one plan: `plan-create` writes tasks to `.claude/plans/{name}/` on a `feature/{name}` branch, then `/plan-orchestrate` builds it with TDD workers. |
| [Acceptance specs](https://github.com/jasonevans1/project-yeschef/commit/aed510bd119e8005606da9100c2037b0dddbabed) (from YesChef) | Ported from YesChef. At plan review I approve 1–3 scenarios; they are locked by hash and agents cannot edit them. Each lives in `tests/Acceptance/{Plan}/`, written in gift-planning language and driven through `Livewire::test()` or HTTP. |

Per feature:

1. Describe the feature to HCF using its entry in Build order below.
2. Review the tasks and approve the acceptance scenarios; its "Done when" line is the starting point for them.
3. Scenarios lock; `/plan-orchestrate` builds until they pass.
4. Review the PR, merge, and Laravel Cloud deploys.

The acceptance-spec files to port: `.claude/agents/acceptance-spec-writer.md`, `.claude/agents/acceptance-lock.md`, the `tdd-worker.md` changes, `.claude/hooks/acceptance-specs.php`, the hook entry in `.claude/settings.json`, the Acceptance suite in `phpunit.xml`, and `tests/Acceptance/Support/` (Dsl and Expectations).

## Data model

Everything belongs to a household, so both users see the same data. Money is stored as integer cents; spent and remaining are always calculated from gifts, never typed in.

| Table | Key columns | Notes |
| --- | --- | --- |
| households | name | One per couple |
| users | household\_id, name, email, password | Added to Laravel's default users table |
| (no groups table) | | Groups are a fixed `RecipientGroup` enum, stored as a string on `recipients.group` |
| recipients | household\_id, group, name, is\_shared, birthday, interests, archived | `is_shared` marks entries like "The Smiths" |
| occasions | household\_id, name, type (christmas/birthday), date, birthday\_recipient\_id | `birthday_recipient_id` prevents duplicate auto-created birthdays |
| occasion\_recipient | occasion\_id, recipient\_id, budget\_cents | Who is on an occasion and their budget |
| gifts | household\_id, recipient\_id, occasion\_id (nullable), title, status, price\_cents, store, url, purchased\_by\_user\_id, is\_private, created\_by\_user\_id | Null occasion = a year-round idea |

**Private gift rule:** a gift with `is_private` is visible only to `created_by_user_id`. Every query, total and list excludes it for other users. The owner's overview adds a separate "My private gifts" spent line; private gifts never feed the shared Budget/Spent/Remaining numbers.

**Past gifts:** a person's history is their gifts on earlier occasions, so no extra table is needed.

## Build order

Each feature is one HCF plan, small enough to build, test and deploy on its own. Features 1–8 make Christmas 2026 trackable; aim to finish them by early December, then add 9–15 as time allows.

- [x] **1. Project and login** — Run laravel new with Livewire and Pest, adopt laravel-factory, run /hcf:project-setup, port the acceptance-spec workflow from YesChef, then email + password auth.
  - Done when: factory checks pass in CI, a locked acceptance scenario blocks agent edits, and I can register, log in and log out.
- [x] **2. Household and partner invite** — Create a household on registration; invite my partner by email to join it.
  - Done when: both of us log in and land in the same household.
- [x] **3. Groups and people** — Use the fixed `RecipientGroup` enum (nothing seeded); add, edit and archive people, including shared entries like "The Smiths".
  - Done when: I can enter everyone from last year's spreadsheet.
- [x] **4. Occasions and budgets** — Create a Christmas occasion, add people to it, set each person's budget.
  - Done when: Christmas 2026 lists everyone with a budget.
- [x] **5. Gifts on an occasion** — Add, edit and delete gifts per person: title, price, store, product link.
  - Done when: Pluto's eight gifts are eight separate rows.
- [x] **6. Gift status and buyer** — Move a gift through Idea → Bought → Wrapped → Given; record who bought it.
  - Done when: one click advances a gift and shows who bought it.
- [x] **7. Occasion overview** — People grouped by group, budget vs. spent vs. remaining per person, season totals, people with no gift.
  - Done when: the overview replaces the spreadsheet for Christmas 2026.
- [x] **8. Shopping list** — Every gift still at Idea on the occasion, across all people.
  - Done when: I can shop from one screen.
- [x] **9. Wrapping checklist** — Every gift at Bought, checked off as Wrapped.
  - Done when: wrapping night is one list.
- [x] **10. Private gifts** — Private flag on a gift; hidden from my partner and excluded from shared totals; a "My private gifts" line for the owner.
  - Done when: my partner's view shows nothing about gifts I'm buying for them.
- [x] **11. Idea list** — Add ideas to a person anytime with no occasion; assign an idea to an occasion later.
  - Done when: an idea added in March shows up when planning Christmas.
- [ ] **12. Person page with history** — Profile with birthday and interests, ideas, and gifts from past occasions by year.
  - Done when: I can see what someone got last Christmas.
- [ ] **13. Automatic birthdays** — Scheduled command creates each person's birthday occasion ahead of time, carrying last year's budget.
  - Done when: birthdays appear without me creating them.
- [ ] **14. Start a new Christmas** — Create next Christmas from last year's, copying people and budgets.
  - Done when: Christmas 2027 takes one click to set up.
- [ ] **15. Reminder banners** — In-app banners for upcoming birthdays and people with no gift yet.
  - Done when: the banner tells me what needs attention when I log in.

## After v1

Deferred until after Christmas 2026:

- Anniversary and custom occasions
- Year-over-year spending view
- Email reminders or a weekly digest
- Shipped and Arrived statuses with order tracking
- Hiding spot and clothing sizes on profiles
- Pulling title, image and price from a pasted product link
- Wishlists family members can add to
- Mobile-first PWA for use while shopping
