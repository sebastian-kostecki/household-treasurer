# Production errors go to a self-hosted Bugsink through the Sentry SDKs, with private defaults and a fixed reporting policy

The owner learned about production exceptions by reading `laravel.log`, and the handler dropped every `abort(5xx)`, every 403 and every validation refusal after a valid preview, so the worst failures left no trace (observability audit 2026-11-19). `context/foundation/error-tracking.md` (research 2026-11-20) compares the providers and the risk register; this ADR records the decision it supports. S-45 builds it in phases; this ADR covers the server SDK, the policy and the alert content.

Provider. A self-hosted Bugsink on the VPS (`kostecki-dev-infra`, `stacks/bugsink`, behind Cloudflare Access), fed by the official Sentry SDKs. The app reaches it only over the external network `errors-treasurer` with an internal DSN (`http://<key>@bugsink:8000/<project>`), so no event crosses the internet. The DSN is a secret of `.env.prod` (`SENTRY_LARAVEL_DSN`); an empty DSN keeps the SDK inert, which is how local, CI and test runs stay silent, and emptying it plus `config:cache` is the rollback.

Dependencies. `sentry/sentry-laravel` pinned `^4.31` on the server and `@sentry/vue` `^11` in the browser (Phase 5). The 5.x line removes `send_default_pii` and changes the privacy defaults, so an upgrade is its own change with its own tests, never a loose constraint.

Privacy. Bugsink has no server-side scrubbing, so the SDK drops the data before it leaves the app, and each option is set explicitly because the defaults are permissive: `send_default_pii` false, `max_request_body_size` `never`, no `data_collection` array (a partial array flips every omitted key to permissive), SQL breadcrumbs without bindings, no tracing or profiling, transport timeouts of 1 s (connect) and 2 s. `zend.exception_ignore_args = On` in `php.ini` removes frame arguments, and `App\Support\ErrorTracking\SentryEventScrubber::beforeSend`, a static callable (a Closure in config breaks `config:cache`), removes request data, cookies, headers and the query string, strips frame `vars` and tags the event with `route`, `request_id`, `user_id` and `release` from the Laravel Context, so privacy of arguments does not rest on one setting. `tests/Feature/ErrorTrackingServerTest.php` pins all of it. Known limit: exception messages (a `QueryException` carries the SQL with its bound values) reach Bugsink, which is acceptable on our own server and is why the alert relay forwards the exception class only.

Registration. `Integration::handles($exceptions)` is the first line of `withExceptions`, before the reporting policy and without `->stop()`, so the tracker still receives an exception when the log write fails and the log keeps its copy. The `sentry` log channel is not added to `LOG_STACK`, which would send every exception twice.

Reporting policy. `abort(5xx)` is reported, except the 503 of maintenance mode and the failing `/up` ping during a deploy. 403, 404, 419, 429 and validation errors stay quiet. An `AuthorizationException` for the signed-in owner is reported at `warning`, at most once per route per minute; a guest's is not. A refusal after a valid preview is a named exception with ids (`.ai/rules/errors.md`).

Alerts. A new, regressed or unmuted Bugsink issue sends one ntfy message with a fixed title, the alert reason, the exception class and a link, never the exception message. Every deploy runs `artisan sentry:test` when the DSN is set and only warns when it fails, because the deployment is already finished at that point. The command builds its own client, so it proves the path and not the privacy settings.

We rejected:

- GlitchTip and hosted providers (the research ranks cost, memory on the VPS and data on our own server above the extras);
- the `sentry` log channel in `LOG_STACK` (double reporting);
- a `before_send` Closure (breaks `config:cache`);
- `sentry-laravel` 5.x (different privacy defaults, still beta);
- reporting every 4xx (noise that hides the 5xx).

Relation to other ADRs. None is amended; ADR-0110 left its `OAuthServerException` quiet. Details of the research: `context/foundation/error-tracking.md`.
