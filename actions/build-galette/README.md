# Build Galette Composite Action

This composite GitHub Action handles the installation and caching of Galette dependencies (Composer, npm, and built assets).

## Features

- ✅ Automatic Composer dependency caching
- ✅ Automatic npm dependency caching
- ✅ Semantic hash-based caching for built assets (accelerates builds significantly)
- ✅ Optional Galette core checkout (for plugin builds or external consumers)

## Inputs

| Input | Required | Default | Description |
|-------|----------|---------|-------------|
| `php-version` | ✅ Yes | - | PHP version to use for cache keys (from matrix) |
| `skip-checkout` | No | `false` | Skip Galette checkout (set to `true` when Galette repo is already checked out at workspace root) |
| `galette-ref` | No | `develop` | Galette core branch/tag to checkout (ignored when `skip-checkout` is `true`) |
| `enable-assets-cache` | No | `true` | Enable semantic hash cache for built frontend assets |

## Outputs

| Output | Description |
|--------|-------------|
| `galette-path` | Path to Galette root directory (`.` when `skip-checkout` is `true`, `galette-core` otherwise) |

## Usage Examples

### For Galette Core (repo already checked out)

```yaml
steps:
  - name: Checkout
    uses: actions/checkout@v6

  - name: Setup PHP
    uses: shivammathur/setup-php@v2
    with:
      php-version: ${{ matrix.php-version }}
      tools: composer, pecl
      coverage: ${{ matrix.coverage }}
      extensions: apcu
      ini-values: apc.enable_cli=1

  - name: Build Galette
    uses: galette/.github/actions/build-galette
    with:
      php-version: ${{ matrix.php-version }}
      enable-assets-cache: ${{ matrix.cache }}
      skip-checkout: true
```

### For Galette Plugins (action checks out Galette core)

```yaml
steps:
  - name: Setup PHP
    uses: shivammathur/setup-php@v2
    with:
      php-version: ${{ matrix.php-version }}
      tools: composer
      coverage: none

  - name: Checkout plugin
    uses: actions/checkout@v6
    with:
      path: plugin-source

  - name: Build Galette
    id: build
    uses: galette/.github/actions/build-galette
    with:
      php-version: ${{ matrix.php-version }}
      galette-ref: develop

  - name: Checkout plugin
    uses: actions/checkout@v6
    with:
      path: galette-core/galette/plugins/plugin-name

  - name: Install plugin dependencies (depending on plugin)
    run: |
      cd galette-core/galette/plugins/plugin-name
      composer install --ignore-platform-reqs
```

## How It Works

### Checkout

`skip-checkout` flag is used for Galette core. It sets the path to `.` and do not proceed checkout: it must be done before.

Otherwise, the action will:

1. **Set paths:** sets working directory to `galette-core/`
2. **Checkout Galette core** Clones the Galette repository to `galette-core/` at the specified `galette-ref`

### Common steps

1. **Show versions:** Displays PHP, Composer, Node, and npm versions for troubleshooting
2. **Composer cache:** Sets up Composer cache based on PHP version and `composer.lock` hash
3. **npm cache:** Sets up npm cache based on `package-lock.json` hash
4. **Semantic hash:** Calculates a hash of the `./ui` directory content (if assets cache is enabled)
5. **Assets cache:** Restores built assets from cache if available (covers `galette/webroot/themes/` and `galette/webroot/assets/`)
6. **Install dependencies:** Runs `bin/install_deps` which handles Composer install and npm build (skips npm build if assets were restored from cache)

## Cache Keys

- **Composer**: `{os}-composer-{php-version}-{composer.lock hash}`
- **npm**: `{os}-npm-{package-lock.json hash}`
- **Assets**: `{os}-galette-{semantic hash of ui/ directory}`

The semantic hash for assets is based on the content of the `ui/` source directory, ensuring the cache is invalidated only when source files change, not when lockfiles are updated.

## Notes

- PHP setup must be done **before** calling this action
- For plugins, the action checks out Galette core automatically; the plugin must be copied into place and its own dependencies installed **after** this action
