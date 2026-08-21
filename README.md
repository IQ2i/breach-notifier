# breach-notifier

Monitors RSS/Atom feeds of data breaches (e.g. [FrenchBreaches](https://frenchbreaches.com/feed.xml)) and reports, via a console command, the ones that match a watchlist of companies.

This is a console-only Symfony application — there is no web layer, no routes, no controllers. `bin/console app:breach:check` is the only entrypoint.

## Requirements

- Docker (recommended), **or** PHP 8.4+ and Composer for a local install.

## Installation

```bash
cp feeds.dist.yaml feeds.yaml
cp watchlist.dist.yaml watchlist.yaml
cp notifications.dist.yaml notifications.yaml
make build   # build the dev Docker image
make install # composer install
make migrate # create the SQLite database (var/data_dev.db)
```

Without Docker: `composer install && php bin/console doctrine:migrations:migrate -n`.

## Configuration

### Feeds — `feeds.yaml`

```yaml
feeds:
    - name: 'FrenchBreaches'
      url: 'https://frenchbreaches.com/feed.xml'
```

Add a feed by adding a `name`/`url` entry. The format (RSS or Atom) is auto-detected.

### Watchlist — `watchlist.yaml`

```yaml
companies:
    - name: 'SFR'
      aliases: ['Société Française du Radiotéléphone']

    - name: 'Orange'
      match_in: ['title']   # generic name: only search the alert title
```

- `aliases`: other legal names the company may appear under.
- `match_in`: `title` and/or `content` (default: both). Useful to limit false positives on a generic name.
- Matching is case- and accent-insensitive, and word-bounded ("SFR" does not match "SFRAIS").

Both files are re-read on every run of the command — no cache clear needed after an edit. Notification channels (`notifications.yaml`) follow the same pattern, see below.

`feeds.yaml`, `watchlist.yaml` and `notifications.yaml` are gitignored: each environment (dev, server) has its own version, edited directly in place, without a commit or a deploy. The `*.dist.yaml` files are the templates — copy them without the `.dist` part during installation.

## Command

```bash
php bin/console app:breach:check [options]
```

| Option | Effect |
|---|---|
| `--all` | Show all known matches, not just new ones from this run |
| `--json` | Machine-readable JSON output instead of the console table |
| `--dry-run` | No DB writes, no notifications |
| `--no-fetch` | Skip network, re-match existing DB rows only (useful after editing the watchlist) |
| `--no-notify` | Skip all notifications for this run |
| `--feed=NAME` | Restrict processing to a single feed |
| `--channel=ID` | Restrict notifications to a single channel (see `notifications.yaml`) |

Exit codes: `0` no new matches, `2` new matches found, `1` error. A notification failure does not affect the exit code (see console warnings or the `notifications` key in JSON output).

## Notifications — `notifications.yaml`

Built on [Symfony Notifier](https://symfony.com/doc/current/notifier.html). Each channel has an id and a single recipient:

```yaml
channels:
    email:
        type: email
        from: '%env(BREACH_MAIL_FROM)%'
        recipient: '%env(BREACH_MAIL_TO)%'

    free_mobile:
        type: free_mobile
        recipient: '%env(FREEMOBILE_DSN)%'

    mattermost:
        type: mattermost
        recipient: '%env(MATTERMOST_DSN)%'

    pushover:
        type: pushover
        recipient: '%env(PUSHOVER_DSN)%'
```

- `type`: `email`, `free_mobile`, `mattermost` or `pushover`.
- `from`: sender address, required for `email` only.
- `recipient`: single recipient, format depends on the type (see table below).
- The `%env(VAR_NAME)%` syntax is recognized in `dsn`, `from` and `recipient`, and resolved from `.env.local`. A missing or empty variable silently disables the channel (visible with `-v`) instead of failing the command.
- Like `feeds.yaml`/`watchlist.yaml`, this file is re-read on every run.

Set the corresponding DSNs in `.env.local`:

```
MAILER_DSN=smtp://user:pass@host:port
BREACH_MAIL_FROM=alerts@example.com
BREACH_MAIL_TO=security@example.com
FREEMOBILE_DSN=freemobile://LOGIN:API_KEY@default?phone=0611223344
MATTERMOST_DSN=mattermost://ACCESS_TOKEN@mattermost.example.com/PATH?channel=security
PUSHOVER_DSN=pushover://USER_KEY:APP_TOKEN@default
```

No channel configured (missing file, empty `channels` section, or all env variables empty): the command runs normally without notifying anything.

### Per-channel specifics

| Channel | Recipient | Limitation |
|---|---|---|
| `email` | an email address | a single recipient per channel |
| `free_mobile` | a **complete** Free Mobile DSN (`%env(...)%`) | the Free Mobile API only sends to the phone number tied to the account used — the recipient must have their own Free Mobile account with the "SMS notifications" option enabled |
| `mattermost` | a **complete** Mattermost DSN (`%env(...)%`) | the target channel is carried by the DSN's `?channel=` query parameter — declare one channel per Mattermost room, each with its own `?channel=`; the access token must belong to a bot/user allowed to post there |
| `pushover` | a **complete** Pushover DSN (`%env(...)%`) | the user/group key and application token are carried by the DSN itself — declare one channel per Pushover user/group; messages are truncated to 1024 characters, the API limit |

## Docker

The image is multi-stage: `dev` (used by `compose.yaml`, bind-mounted source, includes `pcov` for coverage) and `prod` (self-contained, code baked in, `--no-dev` dependencies, opcache, runs as a non-root user).

### Development

```bash
make build && make install && make migrate
make check   # or: make sf cmd="app:breach:check -v"
make test
make lint
```

### Production

```bash
make build-prod                 # docker build --target prod -t breach-notifier:prod .
cp .env .env.local && $EDITOR .env.local   # fill in real DSNs/recipients
make check-prod                 # docker compose -f compose.prod.yaml run --rm breach-notifier
```

The entrypoint runs pending Doctrine migrations before every invocation, so it's safe to run repeatedly. `compose.prod.yaml` mounts `var/` (so the SQLite database persists across runs — this is what keeps the command idempotent) and the three `*.yaml` config files read-only.

Schedule it from the host's cron, e.g. hourly:

```
0 * * * * cd /srv/breach-notifier && docker compose -f compose.prod.yaml run --rm breach-notifier >> var/log/cron.log 2>&1
```

Without Docker, the same one-shot pattern applies directly:

```
0 * * * * cd /srv/breach-notifier && php bin/console app:breach:check >> var/log/cron.log 2>&1
```

## Tests

```bash
make test
```

## Sponsors

<p align="center">
  <a target="_blank" href="https://www.mezcalito.fr">
    <img alt="Mezcalito - Agence Digitale à Grenoble depuis 2006" src="https://raw.githubusercontent.com/IQ2i/breach-notifier/main/doc/static/mezcalito.svg" width="300">
  </a>
</p>

## License

[MIT](LICENSE)
