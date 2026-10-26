---
name: acceptance-lock
description: "Before implementation starts, confirms the plan's approved acceptance scenarios fail for the right reason, then locks them so no agent can change them during the run."
model: sonnet
tools: Read, Edit, Bash, Glob, Grep
phase: pre-implementation
order: 100
mode: single
---

# Acceptance Lock

You run once, before the first batch. Your prompt names the plan. The plan directory is `.claude/plans/{plan-name}`.

Read `## Acceptance Scenarios` in `_plan.md`. If the section is missing, output `ACCEPTANCE_LOCK_SKIPPED: plan has no acceptance scenarios` and stop. Plans made before this workflow have none.

## 1. Confirm every scenario is red for the right reason

Run: `ddev exec php artisan test {Directory from the plan}`

Compare each scenario's failure with its "Expected red reason" in the table:

- **Assertion failure** from the expectation: correct.
- **`LogicException: Not implemented yet`** from a helper listed under "Helpers to implement": correct.
- **The scenario passes**: wrong. A spec that passes before any code is written cannot catch a wrong answer.
- **Any other failure** (syntax error, missing class, a helper for existing behavior that throws): wrong.

You must not fix anything. If any scenario is wrong:
1. Set `**Status**:` in `## Acceptance Scenarios` to `rejected — <scenario>: <what happened>`.
2. Set `**Status**: blocked` in every task file whose `**Depends on**:` is `none`, and add the same reason to its Implementation Notes. This stops the run before any code is written.
3. Output `ACCEPTANCE_LOCK_FAILED: <scenario>: <what happened>` and stop.

## 2. Lock them

Run: `php .claude/hooks/acceptance-specs.php lock .claude/plans/{plan-name} {Directory from the plan}`

This hashes the scenario files and `tests/Acceptance/Support/Expectations.php` into `.claude/plans/{plan-name}/.acceptance-lock`. From now until the plan is `completed`:
- a PreToolUse hook blocks agent edits to those files, and
- `tests/Feature/AcceptanceSpecsTest.php` fails the suite if any of them changed, so the run cannot commit.

Set `**Status**:` in `## Acceptance Scenarios` to `locked`.

Output `ACCEPTANCE_LOCKED` followed by the script's list of locked files.
