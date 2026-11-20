---
project: household-treasurer (Domowy Skarbnik)
researched_at: 2026-11-20
question: which error tracker unblocks S-45 production-error-tracking
recommended: Bugsink 2.6 (self-hosted on halina175, own Postgres) + sentry/sentry-laravel ^4.31 + @sentry/vue ^11
runner_up: GlitchTip 7 (self-hosted)
fallback: no provider (Laravel exception handler → ntfy + log)
context_type: production, single user
scope: error tracking only; the deployment platform is decided in infrastructure.md and is not re-evaluated here
tech_stack:
  language: PHP 8.5 in production (code runs on 8.4.1), TypeScript
  framework: Laravel 13.42 + Inertia 3.11 + Vue 3.5
  runtime: one container (supervisord: php-fpm, nginx, queue:work, schedule:work), PostgreSQL 18, Mikrus halina175 behind Traefik and Cloudflare
---

# Error tracking for production

## Recommendation

**Run Bugsink on halina175 as its own stack in `kostecki-dev-infra`, with its own small Postgres. The app sends errors to it over a private Docker network, through the official Sentry SDKs (`sentry/sentry-laravel` ^4.31, `@sentry/vue` ^11).**

The interview made two hard filters: error data (stack traces, request context) must stay on halina175, and the tracker must cost 0 zł on the existing Mikrus. That removes every SaaS product (Sentry, Nightwatch, Flare, Honeybadger), self-hosted Sentry (7–16 GB RAM) and Telebugs ($299). Among self-hosted trackers that fit, Bugsink tracks errors only, which is exactly the need. It runs in one container with no Redis, alerts only on a new issue, a regression or an unmute, and has had a stable 2.x line with releases every 1–4 weeks since September 2025. GlitchTip 7 is the close runner-up (MIT, built-in MCP, server-side scrubber), but 7.0 came out on 2026-11-09 with known upgrade and missed-alert bugs, six weeks before the year turn this slice has to catch. The no-provider option costs no RAM, but the stack trace stays in a log file read over SSH, which is what the S-45 outcome rules out.

Interview answers (2026-11-20):

1. Stack traces and request context **must stay on the server**.
2. EU data residency: not asked, because answer 1 already excludes cloud storage.
3. Cost: **0 zł, the existing Mikrus only**.
4. Notification channel: **ntfy**. This is the public `ntfy.sh` with a random topic (`kostecki-dev-infra/docs/backups.md:77`), so a notification may carry a fixed title, the exception class and a link, never the message or the stack trace.
5. Scope: **backend and browser** (requests, queue, scheduler, artisan, plus Vue render errors, unhandled rejections and Inertia network errors; audit PLB-2).

## Candidates and hard filters

Facts were checked on 2026-11-20. Sources are linked inline.

| Candidate | Where data lives | Fails on | Kept? |
|---|---|---|---|
| Sentry (sentry.io) | vendor cloud (US or Frankfurt) | data leaves the server; free tier 5k errors, 1 user, Team $29/mo ([pricing](https://sentry.io/pricing/)) | no |
| Sentry self-hosted | own server | installer requires 14 GB RAM / 4 CPU, or 7 GB / 2 CPU errors-only ([_min-requirements.sh](https://github.com/getsentry/self-hosted/blob/master/install/_min-requirements.sh)) | no |
| Laravel Nightwatch | vendor cloud (US/EU) | "a fully-managed product", no self-hosting, no browser SDK ([pricing FAQ](https://laravel.com/nightwatch/pricing)) | no |
| Flare (Spatie) | vendor cloud, region not stated | no self-hosting (the daemon only forwards), no free tier, Hobby $9/mo ([pricing](https://flareapp.io/pricing), [daemon](https://flareapp.io/docs/php/general/flare-daemon)) | no |
| Honeybadger | vendor cloud (EU on Team+) | self-hosting only "ask us" above Business ([plans](https://www.honeybadger.io/plans/)) | no |
| Telebugs | own server | $299 one-off license ([pricing](https://telebugs.com/pricing)) | no |
| Errbit / Exceptionless | own server | MongoDB 7 / Elasticsearch, too heavy; Errbit speaks the Airbrake protocol ([errbit](https://github.com/errbit/errbit), [exceptionless](https://exceptionless.com/docs/self-hosting/docker/)) | no |
| Laravel Pulse | own DB | stores only class, location and count, no trace; requires Livewire ([recorder](https://raw.githubusercontent.com/laravel/pulse/1.x/src/Recorders/Exceptions.php)) | no |
| Laravel Telescope (exceptions only) | own DB | no notifications, no browser errors; trace has file and line only; 24 h default prune ([docs](https://laravel.com/docs/13.x/telescope)) | no |
| Rustrak | own server | v0.x, no stability statement, PHP not listed as tested ([README](https://github.com/rustrak/rustrak)) | watch list |
| **Bugsink** | own server | — | **yes** |
| **GlitchTip** | own server | — | **yes** |
| **No provider** (handler → ntfy + log) | own server | — | **yes** |

Rustrak would fit on paper (AGPL-3.0, SQLite, under 60 MB RAM in a 20k-event burst, Sentry envelope, JSON webhook alerts on new issue, regression and unmute). It is worth a second look once it reaches 1.0 and names PHP as tested.

Small database-backed Laravel packages (`acolyte/laravel-errorlog` v2.0.0, `hewerthomn/laravel-error-tracker` v1.1.1, `korioinc/laravel-exception-viewer` 1.0.10) support Laravel 13, but each has 0–9 GitHub stars. A new dependency on that little adoption is a bigger risk than the problem it solves.

## Scoring

Pass / Partial / Fail against the criteria this research uses. Cost and privacy are also the hard filters above.

| Criterion | Bugsink | GlitchTip 7 | No provider |
|---|---|---|---|
| Cost | Pass: free under PolyForm Shield 1.0.0 (only competing products are barred) | Pass: MIT | Pass |
| Data privacy | Pass with SDK config: on-server, but no server-side scrubbing ([#243](https://github.com/bugsink/bugsink/issues/243)), so the SDKs must drop request bodies, frame arguments and props | Pass: on-server, PII scrubber since 6.2.1, off by default and documented only in the changelog | Pass: nothing new leaves the container except the ntfy title |
| Load on Mikrus | Partial: one container (gunicorn + snappea worker) plus its own Postgres. About 300 MB process memory reported on an idle install; `docker stats` can show 1.4–2 GB because of reclaimable kernel cache ([#367](https://github.com/bugsink/bugsink/issues/367)). No documented RAM minimum. | Partial: all-in-one process, 512 MB recommended (256 MB minimum), plus Postgres 15+ with `max_locks_per_transaction=512`. 7.0 dropped memory-based worker recycling (p95 1.1 → 2.7 GB on a busy install, "a handful of events an hour never reaches these levels"). | Pass: no new process |
| Laravel integration | Pass: PHP and JavaScript SDKs "verified" ([compat](https://bugsink.com/sentry-sdk-compatible/)); it keeps `event` items and tells SDKs to stop sending traces; DSN read from the envelope header, so a tunnel works | Pass: official setup pages for [sentry-laravel](https://glitchtip.com/sdkdocs/php-laravel) and [@sentry/vue](https://glitchtip.com/sdkdocs/javascript-vue); tunnelled envelopes were rejected until 7.0 ([#418](https://gitlab.com/glitchtip/glitchtip-backend/-/issues/418)) | Partial: native `report()`, but grouping, deduplication and a UI are ours to build; browser stack traces arrive minified with no source maps |
| Notifications | Partial: alerts only on NEW, REGRESSED and UNMUTED. The custom webhook POSTs the full issue JSON with no template and no secret header ([docs](https://www.bugsink.com/docs/alerts-custom-webhook/)), so ntfy needs a relay | Partial: native ntfy since 6.0.7, but the body carries the error message, culprit and tags, so a relay is still needed. One alert per issue per rule. An event that arrives just after the 60 s check can be missed ([#525](https://gitlab.com/glitchtip/glitchtip-backend/-/issues/525)) | Pass: direct HTTP to ntfy, throttled with `$exceptions->throttle()` |
| Maintenance | Pass: `docker compose pull`, migrations run on start, frequent patch releases; error data is disposable, so no backups needed | Partial: majors yearly and breaking. Upgrading to 7.0 from below 6.2.0 loses issue data ([#530](https://gitlab.com/glitchtip/glitchtip-backend/-/issues/530)), and renamed retention variables are silently ignored | Partial: our own code and tests, in the app |
| Agent-friendliness | Partial: REST "canonical" API with OpenAPI and scoped tokens, lookup by SDK event id; no llms.txt; no official MCP ([#584](https://github.com/bugsink/bugsink/issues/584)), community read-only MCP exists | Pass with caveats: `.md` docs, built-in MCP (17 tools, off by default, experimental), `glitchtip-cli` (beta), OpenAPI | Fail: `laravel.log` over SSH only, which agents cannot reach (infrastructure.md, Approval) |

### 1. Bugsink (recommended)

Bugsink tracks errors and nothing else: no tracing, logs, replays or uptime to tune or switch off. Alerts fire on a new issue, a regression or an unmute, never on each event, so one bad night on 1 January makes one notification per distinct error. A single image runs migrations, the web process and the background worker under one supervisor. The only extra piece is a database, because the docs say: "Docker volumes do not provide the necessary guarantees for SQLite in WAL-mode … Hence: not recommended" ([docker-install](https://www.bugsink.com/docs/docker-install/)). Latest release is 2.6.3 (2026-11-06) ([PyPI](https://pypi.org/pypi/bugsink/json)). It falls short on agent access (no official MCP) and on scrubbing, which has to happen in the SDKs. Here that is enforced in our own config anyway.

### 2. GlitchTip 7

GlitchTip is the better-equipped product: MIT licence, server-side PII scrubber, native ntfy, a built-in MCP server, TOTP and passkeys, and its own uptime checks. Valkey is optional, since Postgres can carry the queue and cache ([install](https://glitchtip.com/documentation/install.md)). It loses on timing and risk, not on features. 7.0 is eleven days old, it changed the memory model, tunnel support works only from 7.0, and two open bugs concern exactly what S-45 needs: alerts ([#525](https://gitlab.com/glitchtip/glitchtip-backend/-/issues/525)) and source-map uploads that fail silently ([#506](https://gitlab.com/glitchtip/glitchtip-backend/-/issues/506)). If Bugsink fails the RAM measurement or its Postgres path proves unreliable, switching costs only the DSN, because the same SDKs and config work.

### 3. No provider

A `report()` callback in `bootstrap/app.php` sends a throttled ntfy message, the exception goes to `daily` + `stderr` logs, and a browser endpoint writes client errors to the same log. This is the floor if halina175 has no RAM to spare. It lacks grouping, a place to read the stack trace without SSH, and readable browser traces. Part of it stays in the recommended setup anyway: the log keeps writing alongside the tracker.

## Anti-Bias Cross-Check: Bugsink

### Devil's Advocate — Weaknesses

1. **Unknown RAM pool.** The Mikrus plan size is not recorded anywhere (infrastructure.md, Unknown unknowns), and the known caps already add up to 2.8 GB: treasurer app 1 GB + pgsql 512 MB, menu-maker 768 MB + 512 MB, plus root-keeper, Vaultwarden, Backrest and Traefik. Bugsink's gunicorn starts `min(cpu_count, 4)` workers ([gunicorn.docker.conf.py](https://github.com/bugsink/bugsink/blob/main/bugsink/gunicorn.docker.conf.py)). Inside LXC `cpu_count` may report the host's cores, and no documented setting lowers it. An OOM kill on the shared kernel can hit Vaultwarden or the app's Postgres instead.
2. **The PHP transport is synchronous.** sentry-php sends each event over curl at the end of the failing request, with `http_connect_timeout` 2 s and `http_timeout` 5 s by default ([HttpTransport](https://github.com/getsentry/sentry-php/blob/master/src/Transport/HttpTransport.php)). If Bugsink hangs, every request that errors waits up to 7 s, during exactly the incident you want to see.
3. **SDK defaults send financial data.**
   - `max_request_body_size` defaults to `medium`, which sends form and JSON bodies up to 10 KB even with PII off.
   - Frame arguments are sent whenever `zend.exception_ignore_args` is Off. `docker/8.5/php.ini` does not set it.
   - Setting a partial `data_collection` array flips every omitted key to permissive ([docs](https://docs.sentry.io/platforms/php/guides/laravel/data-management/data-collection.md)).
   - In `@sentry/vue` 11, `attachProps` defaults to true, which means Inertia page props with amounts. An unset `dataCollection` collects user info, cookies and bodies ([migration](https://docs.sentry.io/platforms/javascript/migration/v10-to-v11.md)).
   - Bugsink stores whatever arrives.
4. **The alert path has three hops and can go quiet.** Bugsink sends the webhook to a relay, and the relay posts to ntfy.sh. The webhook has no secret header, so the relay URL must carry the secret. A stopped Bugsink container produces no errors, which looks the same as a healthy app.
5. **A smaller vendor.** Bugsink is one company's source-available project. Its Docker docs call Postgres "probably" working and "not extensively tested" ([docker-install](https://www.bugsink.com/docs/docker-install/)), although its own compose sample uses `postgres:17-alpine`. Header-based login for Cloudflare Access is an open, unmerged PR ([#507](https://github.com/bugsink/bugsink/issues/507)), so the UI needs two logins.

### Pre-Mortem — How This Could Fail

S-45 shipped on 20 December. The Bugsink database went into the app's existing pgsql container "to save RAM". On 1 January at 00:40 an import hit a lock storm, pgsql reached its 512 MB cap and restarted, and Bugsink went down with it. Every report from the app timed out after 7 s, so each failing click took 7 seconds longer, and none of the reports arrived. Nobody knew Bugsink was down: there was no uptime check for it, and silence looked like a quiet night. The browser errors had never worked: the tunnel route sat behind `auth`, so errors from the 2FA screen (PLB-5) were refused with 401, and source maps were never uploaded because the image is built on the server, where the upload step could not reach Bugsink behind Cloudflare Access. On 3 January an event finally arrived. Its request body held a pasted bank-statement row, because `max_request_body_size` was left at its default. Each assumption sounded reasonable: "it's a tiny app", "defaults are private", "we'll notice if it breaks".

### Unknown Unknowns

- **`QueryException` messages contain the SQL with bound values substituted.** Amounts and descriptions end up in the exception message even with SQL breadcrumb bindings off. That is acceptable inside Bugsink on our own server, but it is why the ntfy relay may forward the exception class only, never the title Bugsink builds from the message.
- **sentry-laravel 5.0.0-beta1 was released on 2026-11-18** and removes `send_default_pii` ([release](https://github.com/getsentry/sentry-laravel/releases/tag/5.0.0-beta1)). A loose constraint would pull the 5.x line when it goes stable, with different privacy defaults. Pin `^4.31`.
- **Browser errors must reach Bugsink without Bugsink being public.** If the UI sits behind Cloudflare Access, the browser cannot post to it directly. The SDK's `tunnel` option to a same-origin app route solves that. The route must also accept guests, or errors on the login, 2FA and passkey screens (PLB-5, PLB-6) never arrive.
- **`PHONEHOME` defaults to `True`** ([settings](https://www.bugsink.com/docs/settings/)). On a network without internet access it fails quietly. Set it to `False` explicitly.
- **Internal webhooks are blocked by default.** `ALERTS_WEBHOOK_DENY_NON_GLOBAL=True` stops Bugsink from calling a private Docker address, and the relay sits on one.
- **`docker stats` will show Bugsink "leaking"** up to 1.4–2 GB of page cache on an idle install (#367). That cache counts toward a cgroup `mem_limit`, but the kernel reclaims it before an OOM kill. Do not read it as a leak, and do not set a limit so tight that reclaim thrashes.

## Operational Story

- **Where it runs:** `kostecki-dev-infra/stacks/bugsink/`, following the Backrest pattern: own `proxy-bugsink` network to Traefik, host `errors.kostecki.dev` (name to confirm) behind Cloudflare Access for the whole host, like `backup.kostecki.dev`. No outbound internet. A second internal network, shared only by the treasurer app container and Bugsink, carries ingest (app → Bugsink) and the webhook (Bugsink → app relay). Ingest never goes through Cloudflare.
- **Secrets:** Bugsink `SECRET_KEY` (at least 50 random characters) and the admin password go in the stack's env file on halina175. The DSN (`SENTRY_LARAVEL_DSN`), the relay secret and the existing ntfy topic URL go in the treasurer's `.env.prod`. The DSN key only allows sending events.
- **Upgrades:** pin a minor tag (`bugsink/bugsink:2.6`) and pull deliberately. Migrations run on container start. Tracker data is disposable: keep it out of restic and R2, with short retention (`MAX_EVENT_AGE_DAYS`).
- **Rollback:** previous image tag plus `up -d`. On the app side, remove the DSN from `.env.prod` and run `scripts/artisan.sh config:cache`; the SDK goes inert and logging continues.
- **Approval:** a human creates DNS, the Access application and the stack on halina175, and changes `.env.prod`. An agent can write both repos' code and docs, and read Bugsink through its API once a read-only token is shared.
- **Logs:** issues in the Bugsink UI. The same exceptions still go to `storage/logs/laravel-*.log` and container stderr. Bugsink's own logs: `docker compose logs bugsink` on halina175.
- **Is the tracker alive:** an `access-errors` check in `uptime-check.sh` (302 from Access, as for `backup.*`), plus a test event after each deploy (`php artisan sentry:test`) that must appear in Bugsink.

## Risk Register

| Risk | Source | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| Bugsink + its Postgres push halina175 into OOM | Devil's advocate / Unknown unknowns | M | H | Measure first (`free -m`, `docker stats --no-stream`). Set `mem_limit` on both containers. Check how many gunicorn workers start inside LXC and pin them if possible. Set `SNAPPEA_NUM_WORKERS` to 1–2. If it does not fit, fall back to the no-provider option. |
| Tracker DB shares the app's pgsql and dies with it | Pre-mortem | M (if shared) | H | Separate `postgres:18-alpine` container for Bugsink, never the app's pgsql |
| Dead or slow Bugsink delays every failing request by up to 7 s | Devil's advocate | L | M | Ingest over the internal network. Lower `http_connect_timeout`/`http_timeout` to about 1 s / 2 s. The log keeps a copy whatever happens to Bugsink. |
| SDK defaults put request bodies, frame args or Vue props with amounts into Bugsink | Devil's advocate / Research | H (if defaults kept) | M | PHP: `max_request_body_size => 'never'`, keep `send_default_pii` false, no partial `data_collection`, `zend.exception_ignore_args = On` set explicitly in `docker/8.5/php.ini`, `before_send` drops cookies and request data. Vue: `attachProps: false`, explicit minimal `dataCollection`, no tracing, replay or session integrations, filter console breadcrumbs. Assert the config in a test. |
| ntfy.sh (public) receives an exception message with data | Unknown unknowns | M | H | The relay forwards a fixed title, `alert_reason`, the exception class and the Bugsink URL, nothing else |
| Bugsink silently down looks like "no errors" | Devil's advocate / Pre-mortem | M | M | `access-errors` uptime check on Healthchecks.io, plus a test event after each deploy |
| Browser errors never arrive (tunnel behind `auth`/CSRF, Access blocks direct posts) | Pre-mortem / Unknown unknowns | M | M | A same-origin tunnel route open to guests, throttled, with a size cap, that only forwards envelopes whose DSN matches the project. One E2E or feature test proves an envelope reaches a fake ingest. |
| Minified browser stack traces are unreadable | Pre-mortem | H | L | Decide in the plan: hidden source maps uploaded in `deploy.sh` from a container on the internal network, or accept minified traces in the first version |
| sentry-laravel 5.x lands with new privacy defaults | Unknown unknowns | M | M | `"sentry/sentry-laravel": "^4.31"`, a 5.x upgrade as its own change |
| Bugsink's Postgres path is "not extensively tested" | Devil's advocate | L | M | Use the versions from the official compose sample. If ingest misbehaves, the same SDK config moves to GlitchTip by changing the DSN. |
| Not live before 31.12 (two repos, DNS, Access, human deploy) | Pre-mortem | M | H | Target production by about 15 December. Rehearse the year turn in a worktree with `app:rehearsal-clock` and a fake ingest before deploying. |
| Validation-style refusals still never reach the tracker | Research (audit) | H | M | See "What S-45 does about `internalDontReport`" below |

## Getting Started

This is the input for `/10x-plan production-error-tracking`. Each item is a decision or a step for the plan, not code.

### Before the plan

1. **Approve the dependencies** (AGENTS.md): `sentry/sentry-laravel` ^4.31 (stable 4.31.0, 2026-11-19, Laravel 13 and PHP 8.4–8.6 in its CI), `@sentry/vue` ^11 (11.15.0, 2026-11-19, about 37.5 KB gzip for errors only) and, if the plan keeps source maps, `@sentry/vite-plugin` or `sentry-cli` as a build tool. Do not use 5.0.0-beta1.
2. **Measure halina175** before writing the stack: total and free RAM, current `docker stats`, and the CPU count visible inside a container. Write the Mikrus plan size into `kostecki-dev-infra/docs/ARCHITECTURE.md`, which closes an open item from `infrastructure.md`. Go/no-go for Bugsink: its container and its Postgres must fit under fixed `mem_limit`s with headroom for the app's image build.

### Server side (kostecki-dev-infra, human deploys)

3. Add the Bugsink stack: a pinned image, its own Postgres 18, `SINGLE_USER=True`, `PHONEHOME=False`, `BEHIND_HTTPS_PROXY=True`, `BASE_URL`, retention, and `ALERTS_WEBHOOK_DENY_NON_GLOBAL=False` for the internal relay. Add an internal network joined by Bugsink and the treasurer app container only, then DNS and a Cloudflare Access application for the UI host. No public ingest path.
4. Add the `access-errors` check to `uptime-check.sh` and Healthchecks.io.

### App side (this repo)

5. **Register the tracker before the logger.** In `bootstrap/app.php` `withExceptions`, `Integration::handles($exceptions)` comes first. Reporting callbacks run before the default log write, so the tracker still gets the exception when `laravel.log` is unwritable (audit PLB-1). Do not call `->stop()`, so the log keeps its copy. In `.env.prod.example`, change `LOG_STACK=single` to `LOG_STACK=daily,stderr` (PLB-1, BKP-8). The `sentry` log channel stays out of the stack, so each exception is sent once.
6. **Context without data:** `buildContextUsing` or a `Context::add` middleware with route name, request id, user id (never e-mail) and release. The release is the image's git SHA, which `scripts/deploy.sh` already tags (ADR-0107) (PLB-4). Classes such as `InvariantViolation` keep their own `context()` with ids only (`.ai/rules/errors.md`).
7. **Privacy config** as in the risk register, with a test that fails if `max_request_body_size`, `send_default_pii` or the frame-argument setting drift. Set `zend.exception_ignore_args = On` in `docker/8.5/php.ini`, and use short transport timeouts.
8. **Queue, scheduler and artisan** need no extra wiring. `Worker::runJob`, `schedule:run` and the console kernel all call the handler's `report()`. Background scheduled tasks that exit non-zero are not reported; the backup tasks already mail their output on failure (`backup-signals`). Bugsink drops cron check-ins, so Healthchecks.io stays the dead-man's switch.
9. **Browser:** initialise `@sentry/vue` in `resources/js/app.ts` with the private settings above and `tunnel` set to a same-origin route. That route is excluded from `auth` (guests included), rate-limited, size-capped, and forwards only envelopes for our DSN to Bugsink over the internal network. Inertia `networkError` and component-resolve failures are captured as client errors. Server 5xx responses are already reported server-side, so `httpException` is not reported twice (PLB-2, PLB-5, PLB-6, OPS-5). Decide on source maps (risk register).
10. **ntfy relay:** an app route with the secret in its URL takes Bugsink's webhook. It posts to the existing ntfy topic a fixed title (e.g. "Domowy Skarbnik: nowy błąd"), `alert_reason`, the exception class and `Click:` set to the Bugsink issue URL. It never forwards Bugsink's `title`, which holds the exception message.
11. **Proof:** feature tests against a fake ingest (the audit's `fake-ingest.mjs` approach), checking that an `InvariantViolation`, a queue failure and a browser envelope through the tunnel each arrive with no request body or props. Run them on PostgreSQL. Rehearse 2027-01-01 00:30 with `app:rehearsal-clock` before the production deploy. After deploy, `sentry:test` must show up in Bugsink and on ntfy.

### What S-45 does about `internalDontReport`

Laravel 13.42 never reports `ValidationException`, `HttpException` (every `abort()`), `ModelNotFoundException`, `AuthorizationException`, `TokenMismatchException` and the rest of `internalDontReport` (`vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php:170-183`). sentry-laravel is a `reportable()` callback, so it obeys the same list. **No tracker fixes this on its own. S-45 fixes it only where the exception is a bug signal, and it does not lift the list as a whole:**

- **Already done, keep using it:** `reported-invariants` (commit `5fe1aa62`) replaced post-validation guards in five Actions with `App\Support\InvariantViolation`, which is reported. It also made the month-plan and import catches call `report()` before turning the exception into a message (`PastMonthPlanRefused`, `ImportFileRejected`). `.ai/rules/errors.md` makes that the rule. These reach Bugsink with no extra work.
- **Close the remaining known gap in S-45:** import write refusals are still bare `ValidationException::withMessages` in `app/Actions/ImportFinancialOperations.php:73,151,172,210,237,275` (audit IMP-2), so a preview that passes and a write that refuses leave no trace. Report a named exception with ids (account, row index, rule; no amounts or descriptions) before throwing the message, as `PastMonthPlanRefused` does. The plan should grep for other `ValidationException`, `abort()` or `firstOrFail` guards inside Actions that run after validation, and convert those too.
- **Lift exactly one class: server-error `HttpException`.** Call `stopIgnoring(HttpException::class)` together with `dontReportWhen` for status codes below 500. Then `abort(500)` or `abort(503)` from code is reported, while 403, 404, 419 and 429 stay quiet.
- **Keep ignored:** `ValidationException` for input the user can fix, `ModelNotFoundException` from route model binding (a stale tab), `TokenMismatchException`, and 404s from bots. Reporting them would flood the tracker and copy form input into it.
- **Open decision for the plan:** whether an `AuthorizationException` for the signed-in owner should be reported. With one user, a 403 on their own data is usually a bug (OPS-3, MPL-7), but a double-clicked phase change also lands there. A throttled report at `warning` level is the cheap option.

## Proposed line for infrastructure.md

> Application error tracking (S-45): self-hosted Bugsink on halina175 with the Sentry SDKs, alerts to ntfy without error content — comparison, risks and plan input in [error-tracking.md](error-tracking.md).

## Out of Scope

- The deployment platform (decided in `infrastructure.md`)
- Uptime and backup monitoring (Healthchecks.io, `backup-signals`)
- Performance tracing, logs shipping and session replay
- Fixing the other audit findings beyond what S-45 needs (import UX, OPS-1/OPS-2 counter drift, BKP-*)
