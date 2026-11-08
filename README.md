# .github

Shared GitHub configuration of the Galette organization.

## Composite actions

- [`actions/build-galette`](actions/build-galette/README.md): install Galette core with its dependencies and built assets
- [`actions/matrix-generator`](actions/matrix-generator/): PHP and database matrices of the CI jobs
- [`actions/release-plugin`](actions/release-plugin/README.md): build and publish a plugin release
- [`actions/toot`](actions/toot/README.md): announce on Mastodon

## Reusable workflows

### Plugin E2E tests

[`.github/workflows/plugin-e2e.yml`](.github/workflows/plugin-e2e.yml) runs the Playwright
specs of a plugin against Galette core: PostgreSQL and Mailpit services, a fresh install,
the plugin database, the E2E fixtures (core ones and the plugin `Fixtures` class), then the
`plugins-chromium` project of core `playwright.config.ts`.

```yaml
jobs:
  e2e:
    uses: galette/.github/.github/workflows/plugin-e2e.yml@main
    with:
      galette-ref: master
```

| Input | Default | Description |
|-------|---------|-------------|
| `plugin` | repository name | Plugin directory name |
| `galette-ref` | `develop` | Galette core branch or tag to test against |
| `php-version` | `8.5` | PHP version |
| `check-url` | | Path that must not answer 404 once the server is up |

What the plugin provides:

- its specs in `tests/e2e/specs/*.spec.ts`; Galette fixtures and helpers are imported with the
  `@e2e/` alias, see `tests/e2e/README.md` in Galette core;
- its JavaScript, built by `npm run build` when `package.json` has such a script;
- optionally a `tests/e2e/setup.sh`, run with bash from the plugin directory after the
  database is installed and before the fixtures are seeded, with `GALETTE_PATH` set to core.
