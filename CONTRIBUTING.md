# Contributing

Thanks for your interest in contributing to this project. A few simple guidelines to get started.

1. Filing issues
- Use the Issues tab to report bugs or request features. Provide steps to reproduce, expected vs actual behavior, and environment details.

2. Working on changes
- Fork the repo and create a feature branch: `git checkout -b feat/my-change`
- Run tests before opening a PR:

```bash
composer install
composer test
```

- Keep changes small and focused. Add unit tests where appropriate.

3. Pull requests
- Open a PR with a clear title and description. Reference any related issues.
- PRs should pass CI (static analysis and tests) before merge.

4. Code style
- Aim for simple, readable PHP. Add tests for new behavior.

5. Security
- For security issues, follow `SECURITY.md` and report privately.

6. Questions
- If you're unsure about scope or design, open an issue to discuss before implementing.
