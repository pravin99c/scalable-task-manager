## Purpose and scope

<!-- What problem does this solve? Link to an issue if one exists. -->

## Changes made

<!-- Describe the behavior and files changed. Do not present planned features as completed. -->

## Security and data isolation

- [ ] All changed APIs, database queries, task comments and broadcasts enforce authorization
- [ ] Cross-user and cross-project denial paths are tested where applicable
- [ ] No credentials, secrets, private customer information, or default passwords are introduced
- [ ] Role changes, audit logging, queue jobs, and migrations are reviewed where applicable

## Validation

- [ ] `PHP lint and Laravel tests` GitHub Actions check is green
- [ ] New/changed behavior has tests (or explain why not)
- [ ] Fresh development database migration checked where applicable
- [ ] Existing-data migration safety assessed separately (if schema changed)
- [ ] Documentation matches observed behavior and screenshots are genuine or labeled concepts

**Commands run, results and links:**

<!-- Paste only non-sensitive results / CI links. -->

## Review and release safety

- [ ] Pull request is based on `development` unless branch reconciliation is intended
- [ ] Risks, breaking changes and rollback plan documented
- [ ] No merge into `main` without explicit maintainer approval

**Reviewer notes / screenshots:**

<!-- Add context or links. Never include tokens or user data. -->
