# Nightwatch

[Laravel Nightwatch](https://nightwatch.laravel.com) is the production monitoring
service (requests, jobs, scheduled tasks, exceptions and logs). It is only
active in production; tests and local development run with it disabled.

## How it is wired

The `laravel/nightwatch` package is always installed, and its service provider
registers a `nightwatch` log channel automatically. Nothing was added to
`config/logging.php`: the package injects the channel in
`NightwatchServiceProvider::registerLogger()`.

Whether Nightwatch actually does anything is controlled by a single flag:

| Environment | `NIGHTWATCH_ENABLED` | Where it is set |
| - | - | - |
| Production | `true` | server `.env` |
| Local | `false` | `.env` |
| Tests | `false` | `phpunit.xml` |

With `NIGHTWATCH_ENABLED=false` the provider returns early, `shouldCaptureLogs()`
returns `false`, and the log handler is inert: no data is buffered and no
connection is attempted. This is why tests do not need an agent and why adding
the channel to the log stack locally is harmless.

## Enabling it in production

```env
NIGHTWATCH_ENABLED=true
NIGHTWATCH_TOKEN=<environment token from the Nightwatch dashboard>
```

The **agent** must also be running, otherwise no data reaches Nightwatch:

```bash
php artisan nightwatch:agent
```

Run it under a process monitor (Forge, Supervisor, Docker) so it survives
deploys. The agent listens on `127.0.0.1:2407` by default; if you host several
applications on one server, give each one its own port via
`NIGHTWATCH_INGEST_URI` and `--listen-on`.

Check it with:

```bash
php artisan nightwatch:status
```

The command exits non-zero when the agent cannot accept connections.

## Capturing logs

Log capture is separate from request/job monitoring: logs only reach Nightwatch
if the `nightwatch` channel is part of the configured stack. The recommended
setup keeps the local file channel and appends Nightwatch:

```env
LOG_CHANNEL=stack
LOG_STACK=single,nightwatch
```

Using `LOG_CHANNEL=nightwatch` instead sends logs **only** to Nightwatch. Do not
do that here: it drops `storage/logs/laravel.log`, which is our fallback when
the remote service is unreachable, and it silently loses logs from executions
that Nightwatch does not sample.

## Log level

Nightwatch resolves its level in this order:

1. `NIGHTWATCH_LOG_LEVEL`
2. Laravel's `LOG_LEVEL`
3. `debug`

Set `NIGHTWATCH_LOG_LEVEL=warning` to keep `debug`/`info` noise out of the
dashboard while the local file still records everything.

## Sampling

Sampling is applied **per execution context**, not per event. A request,
command or scheduled task is either sampled in — and then every query, job, log
and exception inside it is captured — or sampled out, and nothing from it is
captured. It is not "10% of the events".

Recommended starting point:

```env
NIGHTWATCH_REQUEST_SAMPLE_RATE=0.1
NIGHTWATCH_EXCEPTION_SAMPLE_RATE=1.0
NIGHTWATCH_COMMAND_SAMPLE_RATE=1.0
NIGHTWATCH_SCHEDULED_TASK_SAMPLE_RATE=1.0
```

Why those values in this application:

- **Requests at 0.1**: this is the traffic that burns the monthly quota. 10% is
  enough to profile performance, and the remaining 90% still shows up in
  aggregate counts.
- **Exceptions at 1.0**: this is the important one. Exception capture is
  independent of request sampling — with the default `1.0`, a request that
  throws is captured **even if it was sampled out**. Setting
  `NIGHTWATCH_EXCEPTION_SAMPLE_RATE=0` would tie errors to the 10% and you would
  silently miss 90% of them. Do not do that.
- **Commands and scheduled tasks at 1.0**: their volume is tiny (a handful of
  `dailyAt` tasks plus a `everyMinute` heartbeat) and they are exactly where
  silent failures hurt — a broken archive or FTP ingest job that never runs is
  invisible otherwise.
- **Jobs are not sampled by this setting.** Queued jobs are captured through
  their parent execution context (the request, command or scheduled task that
  dispatched them), so `WarmPhotoCdn`, `ProcessPhoto` and friends follow the
  rate of whatever triggered them.

Sampling only affects data sent to Nightwatch: `storage/logs/laravel.log` keeps
recording everything, which is another reason to keep the stack channel.

Do not enable Nightwatch in tests: every test would attempt to buffer and flush
events to an agent that is not there.

## Failure behaviour

When the agent is not running but Nightwatch is enabled, writing a log emits a
`stream_socket_client(): Unable to connect to tcp://127.0.0.1:2407` PHP warning;
the request itself still succeeds. The ingest timeouts are short (0.5s), so a
down agent does not stall responses for long.
