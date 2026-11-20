# Production Error Tracking — Plan Brief

> Full plan: `context/changes/production-error-tracking/plan.md`
> Research: `context/foundation/error-tracking.md` (provider choice, risk register, 2026-11-20)

## What & Why

Roadmap S-45: the owner gets a report of a production exception, with its stack trace and request context, without searching a log file over SSH. This includes errors that the code today turns into a redirect, a 403 or a validation message. It has to be live before 1 January 2027, the first year turn on production.

## Starting Point

Exceptions go only to an unrotated `laravel.log`, with `userId` as the only context. Laravel never reports `abort(5xx)`, 403s or validation-style refusals. Import write refusals and month plan item mismatches leave no trace (IMP-2, MPL-4). The browser has no error channel at all, and two of its `catch {}` blocks (2FA) swallow errors. There is no tracker and no app notification. `/up`, scheduler output and `InvariantViolation` were already fixed after the 2026-11-19 audit.

## Desired End State

Server, artisan, scheduler, queue and browser errors land in a self-hosted Bugsink on halina175. Each event carries the route, request id, user id and release (git SHA), and never request bodies, cookies, frame arguments or Vue props. A new issue sends one ntfy message with a fixed title, the exception class and a link. "Preview OK / write refused" refusals are reported with ids while the user's message stays the same. Every deploy proves the path with `sentry:test`.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Tracker | Bugsink 2.6 self-hosted, own Postgres, behind Cloudflare Access | Data must stay on the server and cost 0 zł; Bugsink tracks errors only | Research |
| Alerts | ntfy via an app relay: fixed title, `alert_reason`, exception class, link | ntfy.sh is public; the message can hold amounts (`QueryException`) | Research |
| Dependencies | `sentry/sentry-laravel` ^4.31 and `@sentry/vue` ^11, no source-map tooling | Covers server and browser, and 5.0-beta changes privacy defaults | Plan |
| Source maps | None in v1; minified traces with component name and release | Follows from the dependency choice; can be added as its own change | Plan |
| Infra scope | This change includes a `kostecki-dev-infra` phase (agent writes, PR; owner deploys) | One plan keeps network names, DSN and deploy order consistent | Plan |
| Private network | `errors-treasurer` in the infra root compose, internal, 172.30.7.0/24 | Same ownership rule as `proxy-*`; no stack can delete a network another uses | Plan |
| RAM fallback | Provider-independent phases first; RAM measurement gates phases 4–6; no-go → re-plan | Nothing is built speculatively, and work doesn't wait for the server | Plan |
| Owner 403s | Report `AuthorizationException` for a signed-in user at `warning`, 1 per route per minute | With one user, a 403 on own data is a bug or a state drift worth seeing | Plan |
| 5xx | `stopIgnoring(HttpException)` for status ≥ 500, except during maintenance mode | `abort(500/503)` becomes visible; deploy-time 503s don't flood the tracker | Research + Plan |
| Guards | Report-then-refuse for IMP-2, import pairs and month plan items; OPS-3 `abort(403)` → `InvariantViolation` | The whole "page said valid, write refused" class is visible before the year turn | Plan |
| Browser config | DSN and release rendered at runtime in `app.blade.php`, tunnel route outside `web` | The image builds assets against a throwaway `.env`; guests' 2FA/passkey errors must arrive | Plan |

## Scope

**In scope:**
- Reporting policy for 5xx and owner 403s.
- Request context middleware; release in the image; `LOG_STACK=daily,stderr`; `zend.exception_ignore_args`.
- `ImportWriteRefused` and `MonthPlanItemsRefused`; OPS-3 as an `InvariantViolation`.
- In `kostecki-dev-infra`: the Bugsink stack, networks, deploy script, `access-errors` uptime check and docs.
- Server SDK with a privacy config pinned by tests, and ADR-0112.
- Browser SDK, the tunnel route, and the 2FA/passkey catches.
- The ntfy relay.
- The year-turn rehearsal and the production rollout.

**Out of scope:**
- Source maps, tracing, replay, log shipping.
- Better import error messages in the UI (S-42).
- `firstOrFail` re-locks.
- The other audit findings.
- The no-provider variant, unless the RAM gate says no-go.

## Architecture / Approach

The SDKs report to Bugsink over a private Docker network (`errors-treasurer`), never through Cloudflare. Browser envelopes go to a same-origin `/error-tracking/tunnel`, which accepts only `event` items for our DSN and forwards them over the same network. Bugsink's webhook calls `/error-tracking/alerts/{secret}` on the app, which posts a minimal message to ntfy.sh. The SDK is inert without a DSN, so rollback means emptying the DSN.

```
browser ──/error-tracking/tunnel──┐
app (php-fpm, artisan, queue) ────┴─► errors-treasurer ─► Bugsink (+pgsql) ─webhook─► app relay ─► ntfy.sh
                                                         ▲ UI: errors.kostecki.dev via Traefik + Access
```

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Reporting policy and context | 5xx and owner 403s reported; route, request id, user id and release on every entry; rotated and stderr logs | Maintenance-mode 503s flooding reports if not excluded |
| 2. Report-then-refuse guards | `ImportWriteRefused`, `MonthPlanItemsRefused`, OPS-3 as `InvariantViolation` | Leaking amounts into context; ids only |
| 3. RAM gate and Bugsink stack | Measured memory budget, go/no-go, infra PR, deployed Bugsink behind Access | halina175 has no room → re-plan phases 4–6 |
| 4. Server SDK | sentry-laravel registered before the logger, private config, private network, `sentry:test`, ADR | Permissive SDK defaults sending bodies |
| 5. Browser SDK and tunnel | `@sentry/vue` with private options, guest-reachable tunnel, 2FA/passkey errors | `attachProps` sending props; tunnel abused as a relay |
| 6. Alert relay to ntfy | One minimal notification per new issue | Forwarding Bugsink's title with the exception message |
| 7. Rehearsal and rollout | 2027-01-01 00:30 rehearsal, ordered production deploy, end-to-end proof | Deploy order: networks before the app compose change |

**Prerequisites:** owner access to halina175, the Mikrus panel and the Cloudflare Zero Trust panel; the `testing_production_error_tracking` database for the worktree; the ntfy topic URL from the infra `.env`.

**Estimated effort:** about 5–7 agent sessions across 7 phases, plus about 2 hours of owner work (measurement, PR merge, DNS and Access, deploys). Target production by about 15 December 2026.

## Open Risks & Assumptions

- The memory threshold (idle `available` minus build peak ≥ 1 GB) is a proposal. The owner confirms it at the gate.
- Bugsink on Postgres is "not extensively tested" upstream. If ingest misbehaves, the same SDK config moves to GlitchTip by changing the DSN.
- The exact names of Bugsink's webhook payload fields and of Inertia 3's app hook are checked against current docs during implementation.
- The UI host name `errors.kostecki.dev` is assumed; the infra PR is where to change it.
- Browser traces are minified in v1. The component name and release are the debugging anchors.

## Success Criteria (Summary)

- On 1 January a refused December approval or a refused January import row shows up in Bugsink with ids, and the phone gets one notification without amounts.
- An unhandled error anywhere, server or browser, guest or signed in, arrives with route, request id and release, and with no financial data.
- A dead Bugsink is noticed by the `access-errors` check, not by silence.
