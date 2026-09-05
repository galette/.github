# Toot

Publishes a status on a Mastodon account.

```yaml
- uses: galette/.github/actions/toot@main
  with:
    status: |
      Galette 1.2.1 is out!
      https://github.com/galette/galette/releases/tag/1.2.1
    token: ${{ secrets.MASTODON_TOKEN }}
    instance: ${{ vars.MASTODON_INSTANCE }}
    idempotency-key: galette-1.2.1
```

## Which account does it post to?

The one the token belongs to. **The action has no default token and no default
instance**: a caller that wires neither publishes nothing and succeeds. That is
deliberate — a default instance would mean a misconfigured workflow posts to the
project's account instead of staying silent.

For the Galette account, `MASTODON_TOKEN` and `MASTODON_INSTANCE` are set at the
organisation level with the **Selected repositories** visibility. A repository
that is not on that list reads an empty string, so it publishes nothing without
failing. Adding a repository to the list is how the announcement is granted;
removing it is how it is revoked.

A plugin maintained outside that list — a community plugin, whether or not it
lives in `galette-plugins` — announces on **its own** account by setting
`MASTODON_TOKEN` and `MASTODON_INSTANCE` at the repository level. A repository
secret wins over an organisation secret of the same name, so the two cases cannot
contaminate each other.

Create the token in Mastodon under Preferences → Development → New application,
with the **`write:statuses`** scope and nothing else.

## Inputs

| Input | Default | Notes |
|---|---|---|
| `status` | — | Required. Plain text; Mastodon linkifies URLs. |
| `token` | `''` | Empty means "do not publish", and is not an error. |
| `instance` | `''` | Host, e.g. `framapiaf.org`. A `https://` prefix is stripped. Empty behaves like an empty token. |
| `visibility` | `public` | `public`, `unlisted`, `private` or `direct`. |
| `language` | `en` | ISO 639 code. |
| `idempotency-key` | `''` | Sent as `Idempotency-Key`. Mastodon returns the existing status instead of creating a second one, so re-running a workflow does not announce twice. Worth setting. |
| `dry-run` | `false` | Prints the status and its length, publishes nothing. |

Outputs: `posted` (`"true"` when something was published) and `url`.

## Length

Mastodon accepts 500 characters, counts characters rather than bytes, and counts
**every link as 23 characters** whatever its real length. The action applies the
same rule before posting, so it does not reject a status the server would have
accepted — release announcements are mostly links, and the difference is large.
