# Contributing to TaskForge

Thanks for your interest in this Laravel task-management project.

## Before opening a pull request

1. Read [README.md](README.md) and [docs/ROADMAP.md](docs/ROADMAP.md).
2. Check existing issues and open a focused discussion before large changes.
3. Branch from `development` for application code; use a short, descriptive branch name.
4. Keep changes small and explain the problem, solution, tests and any migration impacts.
5. Do not include secrets, real user data, tokens or private company code.

## Current development caveat

The `development` branch has an existing Laravel 11 codebase, but clean setup and the full test suite have **not** yet been independently verified. The README on `main` is an honest project landing page. Please avoid labeling unfinished features as working.

## Quality checklist

- [ ] All changed endpoints are covered by appropriate authorization checks.
- [ ] User/workspace boundaries are tested (including denial cases).
- [ ] New logic has feature/unit tests; no unrelated tests are disabled.
- [ ] No credentials or hard-coded demo admin passwords are introduced.
- [ ] Documentation matches implemented behavior.
- [ ] Database changes are backward-compatible or have an explicit migration plan.
- [ ] Changes to queues, broadcast channels and cache are scoped and tested for isolation.

## Security reports

Please **do not open public issues containing security exploits, credentials or customer data**. Contact the repository owner privately via the contact information on the [GitHub profile](https://github.com/pravin99c).

## Code of conduct

Be constructive, respectful and precise. Welcome beginner questions and help reviewers reproduce issues. All changes remain subject to maintainer review.

## License

Contributions are made under the repository's [MIT License](LICENSE).
