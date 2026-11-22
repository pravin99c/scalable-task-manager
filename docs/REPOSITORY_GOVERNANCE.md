# TaskForge Repository Governance

**Status:** Proposed repository operating policy, awaiting repository-owner approval and GitHub settings changes.

## Current verified state (2026-11-22)

- `main` and `development` were aligned with a **non-forced fast-forward**, both pointing to commit `c46560e45442f1fc221b92c7f74d1ea5f0097302`.
- GitHub Actions on that SHA passed for both `main` and `development`:
  - `main`: https://github.com/pravin99c/scalable-task-manager/actions/runs/45002466890
  - `development`: https://github.com/pravin99c/scalable-task-manager/actions/runs/45002754426
- The public README references `docs/images/taskforge-dashboard-concept.webp`. The image exists in the repository, and the README explicitly labels it as a **concept**, not a working UI screenshot.
- Pull request **#2** (membership foundation) is superseded by the integrated implementation in `main`. Comparison against `main` showed no missing source changes; its membership test blob was identical to `main`. Retain the branch and PR history. Do not remerge this stale PR.
- No configured repository rulesets were visible, and both branches were reported unprotected. The connected GitHub integration received HTTP 403 on branch-protection reads, so it **could not create or change protection rules**.

## Branch strategy

| Branch | Responsibility | Change policy |
| --- | --- | --- |
| `main` | Public portfolio and candidate release branch | PR-based reviewed, tested changes only; no force pushes |
| `development` | Integration branch for application changes | Feature PRs, passing CI, explicit review |
| `feat/*`, `fix/*`, `chore/*` | One focused piece of work | Branch from `development`, open a PR, preserve history |
| Historical branches | Traceable prior work | Keep until the maintainer explicitly approves archival or deletion |

If `main` moves ahead of `development` and `development` is an ancestor, synchronize with a **normal fast-forward only**, after checking current refs, CI and changes. If histories diverge, prepare a separate merge/reconciliation PR; **never force-update either protected branch**.

## Configure GitHub Rulesets (owner action required)

This repository connection does not expose settings mutation or branch-protection administration. The owner must open:

**Repository → Settings → Rules → Rulesets → New branch ruleset**

1. Create an **active** ruleset with targets for `main` (repeat for `development` or use one ruleset targeting both). Configure appropriate bypass actors before enabling.
2. Enable **Require a pull request before merging**.
3. Enable **Require status checks to pass** and select the exact existing check name **`PHP lint and Laravel tests`**. Require up-to-date checks when practical.
4. **Block force pushes** and **branch deletion** for those branches.
5. Require **one independent approval** where a second reviewer is available. For a solo-maintainer repository, requiring an outside reviewer may block all merges; choose review requirements based on actual collaborators.
6. Consider requiring conversation resolution and including administrators only after confirming that you will not lock yourself out.
7. Save the active ruleset and confirm a test PR cannot merge with a failing CI check or unreviewed change.

An alternative location is **Repository → Settings → Branches → Add branch protection rule**, if Rulesets is unavailable. Current connected permissions cannot verify those settings; mark branch protection **unconfigured** until the owner confirms success.

> **Do not enable protections assuming the GitHub connector has configured them.** Configuration requires the repository settings UI or an authorized administration interface.

## CI baseline and limitations

The workflow named **Laravel security validation** runs the check **PHP lint and Laravel tests** with PHP 8.4 and MySQL 8.4 services. It installs Composer dependencies, lints PHP files, migrates an empty disposable MySQL database, and runs the Laravel test suite against the test environment.

This does **not** verify migrations of existing production data, queues/WebSockets in production, full Docker Compose setup, end-to-end browser interaction, application security certification, or performance measurements.

The workflow accepts PRs targeting `main` or `development`, and this policy PR proposes push checks for `feat/**` and `chore/**` in addition to current `main`, `development`, and `fix/**` targets.

## Pull request checklist

- [ ] Clear goal, impact, and non-goals
- [ ] Relevant application and authorization tests
- [ ] No secrets, private data or hard-coded credentials
- [ ] No unvalidated destructive schema migration
- [ ] CI green on current head commit
- [ ] README, screenshots and feature claims reflect working code
- [ ] At least one appropriate reviewer when available
- [ ] No unapproved merge into `main`

Use [CONTRIBUTING.md](../CONTRIBUTING.md) for contribution requirements and [the PR template](../.github/PULL_REQUEST_TEMPLATE.md) for new changes.
