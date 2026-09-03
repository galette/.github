# Release a Galette Plugin

This composite GitHub Action builds a plugin archive with the plugin's own `bin/release`
and publishes it as a GitHub release, in two modes:

- **tag** — a pushed tag produces `galette-plugin-<slug>-<version>.tar.bz2`, attached to the
  release for that tag;
- **nightly** — a daily build of the development branch, attached to a single rolling
  prerelease named `nightly` whose asset is replaced every night.

Plugin archives are published on GitHub only; nothing is uploaded to galette.eu any more.

## Inputs

| Input | Required | Default | Description |
|-------|----------|---------|-------------|
| `version` | No | `''` | Tag to release. Empty builds a nightly instead. |
| `nightly-branch` | No | `develop` | Branch a nightly is built from. |
| `nightly-tag` | No | `nightly` | Tag carrying the rolling nightly release. |
| `php-version` | No | `8.3` | PHP version used to install the plugin Composer dependencies. |
| `php-extensions` | No | `sodium` | PHP extensions needed to install them. |
| `attest` | No | `true` | Attach a signed build provenance attestation. |
| `dry-run` | No | `false` | Build the archive and upload it as a workflow artifact, publish nothing. |
| `token` | No | `${{ github.token }}` | Token used to publish the release. |

## Outputs

| Output | Description |
|--------|-------------|
| `archive` | Absolute path to the built archive |
| `archive-name` | File name of the built archive |
| `version` | Version that was built, `dev` for a nightly |

## Usage

### On a tag

```yaml
permissions:
  contents: write
  id-token: write
  attestations: write
  artifact-metadata: write
  pages: write

steps:
  # The default branch, not the tag: bin/release archives the tag's tree, but
  # the tooling doing it has to be the current one — building an old tag with
  # the bin/release that shipped in it would run whatever bugs it had.
  - uses: actions/checkout@v7
    with:
      ref: ${{ github.event.repository.default_branch }}
      fetch-depth: 1

  # On a tag push, actions/checkout writes refs/tags/<tag> pointing straight at
  # the commit, i.e. a lightweight tag, even when the remote tag is annotated.
  # bin/release reads the tag object, so fetch it back.
  - name: Fetch the annotated tag
    run: git fetch --no-tags --force --depth=1 origin "+refs/tags/${GITHUB_REF_NAME}:refs/tags/${GITHUB_REF_NAME}"

  - uses: galette/.github/actions/release-plugin@main
    with:
      version: ${{ github.ref_name }}
```

Do **not** use `fetch-depth: 0` or `fetch-tags: true`: they bring in every tag, including
the lightweight ones some plugins still carry, on which `bin/release` fails.

### Nightly

```yaml
steps:
  - uses: actions/checkout@v7
    with:
      ref: develop
      fetch-depth: 1

  - uses: galette/.github/actions/release-plugin@main
```

`bin/release -n` resolves the local `develop` branch, so a plugin whose development branch
has another name needs `git branch -f develop HEAD` after the checkout, along with
`nightly-branch`.

## Releasing a plugin

1. Bump `version:` (and `date:`) in `_define.php`, and `compver:` if the plugin now targets a
   newer Galette generation. `compver` is what the plugin page will name, so it is worth
   getting right before the tag rather than after.
2. Recompile the translations: `cd lang && make mo`. The `.mo` files are committed and
   `git archive` ships the tree of the tag, so a build never regenerates them.
3. Commit, then tag **annotated** with the bare version number: `git tag -a 2.3.0 -m 2.3.0`.
   Lightweight tags and `v`-prefixed tags are rejected.
4. Push the tag. The workflow checks that `_define.php` at that tag declares exactly that
   version, builds the archive, and publishes the release.

To rehearse without touching tags, run the workflow manually with `dry-run: true`: the
archive is uploaded as a workflow artifact and nothing is published.

## What it writes on the Pages branch

A plugin page names the Galette generation its stable download targets. That number is
`compver` in `_define.php`, which lives on the code branches while the site lives on a Pages
branch of the same repository — and Jekyll on GitHub Pages runs in safe mode, so it can read
neither another branch nor the network. The action therefore copies it across, as
`_data/galette.yml` on the Pages branch:

```yaml
release: "2.2.1"
compver: "1.2.0"
series: "1.2"
```

The theme reads `site.data.galette.series`, and ignores the file unless `release` matches the
release its download cartouche actually names. `plugin.min_galette` in the site's
`_config.yml` stays as the fallback, for a plugin with no release workflow yet.

Being the same repository, `github.token` is enough — no cross-repo secret. What matters:

- **Only in tag mode**, and only when the tag *is* `releases/latest`. Republishing an old
  version with `--latest=false` must not make the page name the generation that release
  targeted. A nightly writes nothing at all: it requires a Galette nightly, which is what the
  page says, and writing every night would commit every night.
- **Only when the value changes**, and only on a branch whose `_config.yml` references
  `galette/theme-ghpages`. The Pages branch is discovered through the API, never assumed: it
  is `gh-pages-galette-theme` on three plugins, and may be a subdirectory.
- **Never fatal.** A protected branch, a race with Weblate on the same branch, no page at
  all — each one warns and leaves the page naming whatever it had. A published release is
  never failed over a documentation file.

`compver` is the Galette *generation* a build targets, not an open floor:
`Galette\Core\Plugins::register()` disables a plugin whose `compver` is **lower** than the
running Galette's `GALETTE_COMPAT_VERSION`, and imposes no upper bound. A release declaring
`1.2.0` is refused by Galette 1.3, so the page reads "for Galette 1.2".

## Verifying a download

Every published archive carries a build provenance attestation, which replaces the detached
GPG signature that used to sit next to the tarballs on galette.eu:

```bash
gh attestation verify galette-plugin-oauth2-3.0.2.tar.bz2 --repo galette-plugins/plugin-oauth2
```

## Notes

- The action needs `bin/release` to accept `--no-sign`, and to survive lightweight tags in
  the repository. Both landed in the plugins' scripts alongside this action. Since the script
  is taken from the default branch and not from the tag, older tags are buildable too.
- `@main` is unpinned, and this action holds `contents: write` for every plugin that calls it.
  Cutting a `v1` tag and moving the callers to it is worth doing once the pilots are settled.
- `bin/release` ignores the exit code of every subprocess it runs, so the action inspects the
  archive it produced: exactly one tarball, carrying `_define.php`, and `vendor/autoload.php`
  whenever the plugin has a `composer.json`.
- A plugin page declares no version: the theme reads the latest release. That is captured
  when GitHub Pages builds the site, so publishing a release is not enough — the action asks
  for a page build afterwards, and warns rather than fails where there is no page to build.
  The same build picks up the `_data/galette.yml` written just before it.
- `read-define.php` is the one way this action reads a `_define.php`. It replays the real
  signature of `Plugins::register()` rather than matching text, because the plugins in the
  wild do not agree on shape: most pass named arguments, `plugin-oauth2` and
  `plugin-legalnotices` pass them positionally, `plugin-legalnotices` carries no trailing
  comments at all, and `plugin-objectslend` writes `//Galette version compatibility` where
  everything else writes `//Galette compatible version`.
- `nightly-tag` is only worth setting when the default name cannot be used on a repository.
  Deleting an immutable release burns its tag name for good — GitHub then refuses to create
  that ref at all — so a repository that once had an immutable `nightly` needs another name.
  The archive keeps its own name either way, since `bin/release` chooses it; only the download
  URL changes, which a plugin page covers with `plugin.nightly_url`.
- The nightly notes carry what has landed since the last stable release, generated by GitHub
  from the merged pull requests. The `nightly` tag follows the built commit, so the release's
  source links and that changelog's compare link point at what was actually built. Where a
  ruleset restricts tag pushes, the tag stays where it is and the run warns instead of failing.
- A rolling nightly needs mutable releases: its archive is replaced every night. If a
  repository enables immutable releases, the workflow stops with an explicit error naming the
  setting rather than letting the upload fail on an opaque API message. Tag releases are
  unaffected — their asset goes in when the release is created.
