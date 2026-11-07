<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Ekrany wejścia według Atramentu

- **Plan**: context/changes/auth-atrament-ui/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3, 4, 5
- **Date**: 2026-11-07
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 5 warnings, 4 observations

## Verification run (Step 3)

| Command | Result |
|---|---|
| `npm run check:ui` | pass |
| `npm test` | pass — 37 files, 403 tests |
| `npm run check` (vp check) | pass — 291 files formatted, 0 lint warnings |
| `npm run types:check` | pass |
| PostgreSQL (Sail): `tests/Feature/Auth`, `AuthKitchenSinkTest`, `UiTranslationKeysTest`, `tests/Feature/Settings` | pass — 47 passed, 2 skipped (registration disabled by design, pre-existing) |
| `vendor/bin/sloppy ci` | pass — 100/100, 0 findings |
| `vendor/bin/pint --dirty --format agent` | pass |

Not re-run by the reviewer: `composer ci:check` as a whole, `npm run build`, Playwright e2e `auth.setup.ts`, browser/screenshot checks (manual items 2.5, 3.5, 4.7–4.10, 5.5–5.7 ticked in Progress; evidence = 74 files in `screenshots/`, nothing else observable in the diff).

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | PASS |
| Safety & Quality | WARNING |
| Architecture | WARNING |
| Pattern Consistency | WARNING |
| Success Criteria | WARNING |

## Findings

### F1 — Docs and rules promise sink states `error` and `recovery` that do not exist

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Plan Adherence
- **Location**: app/Http/Controllers/Dev/AuthKitchenSinkController.php:17-25; .ai/rules/js-auth.md:32; context/foundation/design-system.md:108; tests/Feature/AuthKitchenSinkTest.php
- **Detail**: Plan Faza 5 specified states `error` (prop `errors`, `__('auth.failed')` for Login) and `recovery` (TwoFactorChallenge), with a test `?screen=login&state=error` → `auth/Login` with `errors.email`. The implementation has neither state; the test instead asserts that `state=error` returns 404, and the controller docblock explains why (Inertia `Form` fills `errors` only after a rejected submit). The technical reason is plausible, but `js-auth.md` and `design-system.md` still say the sink contains "default, error, status, no-email, recovery", and neither the plan nor ADR-0056 records the change. The next agent following the rule will look for states that are not there. The "error" screenshots required by the plan can only come from a real submit.
- **Fix A ⭐ Recommended**: Keep the implementation; correct `js-auth.md`, `design-system.md` (and ADR-0056 if it lists states) to the real state set, and add a short addendum to plan.md recording the deviation and how error screens are captured.
  - Strength: Docs match reality; no code risk; the rationale already exists in the controller docblock.
  - Tradeoff: The error and recovery states are not reachable as static pages; they stay visually unverified except via live submit.
  - Confidence: HIGH — the three docs and the test all agree on what is actually implemented.
  - Blind spot: Whether `screenshots/` contains real error/recovery shots was not checked.
- **Fix B**: Implement `error`/`recovery` in the sink (e.g. pass `errors` via `Inertia::render` props, which the plan's Key Discoveries said overrides shared props).
  - Strength: Matches the plan literally and gives the visual gate the error state.
  - Tradeoff: The author already found that `Form` does not pick up `errors` props for display; this may need a client-side hook in the page.
  - Confidence: LOW — the author's finding contradicts the plan's assumption and was not independently reproduced.
  - Blind spot: Not tried.
- **Decision**: FIXED via Fix A (js-auth.md, design-system.md, ADR-0056 and plan addendum now describe the real sink states)

### F2 — Card footer via `Teleport defer` to `#auth-card-foot`: undocumented and unverified under SSR

- **Severity**: ⚠️ WARNING
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Architecture
- **Location**: resources/js/layouts/auth/AuthCardLayout.vue:94; resources/js/components/AuthCardFooter.vue:10
- **Detail**: Plan said "opcjonalny slot `footer` w `.auth-card-foot`". Inertia pages render into the default slot, so a named slot cannot be filled; the author used a new component `AuthCardFooter.vue` that teleports into an element found by id. This is a reasonable workaround but (a) it is recorded in neither plan, ADR-0056 nor `js-auth.md`, and (b) `config/inertia.php` has `'ssr' => ['enabled' => true]` and `package.json` has `build:ssr`. Vue's SSR does not inline teleported content into the page HTML, so with SSR running the footer ("Nie masz telefonu?", logout link, etc.) would be absent from the server HTML and appear only after hydration. No evidence in the repo that an SSR server runs in production, and the footer was not tested under SSR.
- **Fix**: Document the mechanism in ADR-0056 and `js-auth.md`, then check once with `npm run build:ssr` + the SSR server that the footer renders and no hydration warning appears; if it fails, use `useLayoutProps`/`setLayoutProps` to pass the footer as a layout prop or render the footer inside the page body.
  - Strength: Settles the question with evidence instead of assumption.
  - Tradeoff: If SSR is not used in production, the check is wasted effort; a layout-prop alternative would be a bigger rework.
  - Confidence: MED — the Vue SSR teleport limitation is well known but was not reproduced here.
  - Blind spot: Whether production runs the SSR server at all.
- **Decision**: FIXED — checked: the app has no SSR entry (`ssr.ts`), no `inertia:start-ssr` and no `bootstrap/ssr`, so the teleport runs client-side only; mechanism and that assumption recorded in ADR-0056 and js-auth.md

### F3 — Literal `action="/register"` in Register.vue slips past the guard

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/pages/auth/Register.vue:29; resources/js/ui-contract.test.ts
- **Detail**: ADR-0056 and `js-auth.md` say routes come from Wayfinder, never literal. The `ui-contract` guard checks `href="/` only, so `action="/register"` passes. The page is unreachable while registration is off, and there is no Wayfinder `register` route to use yet.
- **Fix**: Extend the contract test to also reject `action="/`, and for Register leave a one-line comment (or an explicit allow-list entry) stating that the Wayfinder route will replace it when registration is enabled.
- **Decision**: FIXED — new ui-contract assertion rejects `action="/` in entry files; Register.vue is the one documented exception (no Wayfinder route while registration is off)

### F4 — Password toggle has both a state-dependent `aria-label` and `aria-pressed`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/PasswordInput.vue:41-47
- **Detail**: Label switches between "Pokaż hasło" and "Ukryj hasło" and `aria-pressed` is also set, so a screen reader announces "Ukryj hasło, przycisk przełączany, wciśnięty" — double state. Also affects Security.vue and DeleteUser.vue.
- **Fix**: Drop `:aria-pressed` and keep the changing label (the plan only asked for label keys `show`/`hide`).
- **Decision**: FIXED — removed `aria-pressed` from PasswordInput, label keeps switching

### F5 — Plan item "C13 passkey redirect" turned out to be a false alarm; plan not updated

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Success Criteria
- **Location**: context/changes/auth-atrament-ui/plan.md (Faza 2 #1–#2, Progress 2.1)
- **Detail**: Fortify already sets `passkeys.redirect` to `Fortify::redirects('login')` (`vendor/laravel/fortify/src/FortifyServiceProvider.php:139`), so `config/passkeys.php` was correctly not created and `PasskeyLoginRedirectTest` passes without app code changes. This is only recorded in the commit message of 8c7e3ae. The plan still says the first case "ma nie przechodzić" and lists `config/passkeys.php`, and Progress 2.1 is ticked as if every new test had been red first. The test itself is sound (mocks only `VerifyPasskey`, real response and config run), it is a regression guard.
- **Fix**: Add an addendum to plan.md Faza 2 stating that C13 was a false alarm and that 2.1 applies to `PolishAuthMessagesTest` only.
- **Decision**: FIXED — plan addendum records C13 as a false alarm and limits 2.1 to PolishAuthMessagesTest

### F6 — Kitchen sink fixtures use text different from real copy

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: app/Http/Controllers/Dev/AuthKitchenSinkController.php:70,76
- **Detail**: Fixture status strings ("Wysłaliśmy link do zresetowania hasła na podany adres e-mail.", "Hasło zostało zmienione. Zaloguj się nowym hasłem.") differ from `lang/pl/passwords.php` (`sent` = "Wysłaliśmy link do zmiany hasła na Twój adres e-mail.", `reset` = real text). The visual gate screenshots therefore do not show the strings users will see; wrapping may differ.
- **Fix**: Use `__('passwords.sent')` and `__('passwords.reset')` in the fixtures. Optionally strengthen `AuthKitchenSinkTest` to assert the `status` value, not just `has('status')`.
- **Decision**: FIXED — fixtures use `__("passwords.reset")` / `__("passwords.sent")`; sink test asserts the status value

### F7 — TwoFactorChallenge: focus lost on mode switch; OTP error not linked to the field

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/pages/auth/TwoFactorChallenge.vue:43-47, 89, 97, 135
- **Detail**: Switching between code and recovery mode swaps the whole form with `v-if`/`v-else` (and the Teleport footer), so the focused toggle button is destroyed and focus drops to `<body>`; `autofocus` is not honoured on dynamic insertion. The OTP `InputError` has no `id` and the OTP has no `aria-describedby`, unlike the recovery field.
- **Fix**: After toggling, `nextTick` and focus the new input; give the OTP error an id and set `aria-describedby`.
  - Strength: Keyboard and screen-reader users keep context on a security-critical screen.
  - Tradeoff: Small amount of focus-management code; needs a manual keyboard check.
  - Confidence: MED — read from code, not reproduced in a browser.
  - Blind spot: Whether `InputOTP` forwards `aria-describedby` to the real input.
- **Decision**: FIXED — focus moves to the new field after the mode switch; OTP error has an id and `aria-describedby`

### F8 — Year block computed with `new Date()` in layout setup (SSR hydration risk)

- **Severity**: 💡 OBSERVATION
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Safety & Quality
- **Location**: resources/js/layouts/auth/AuthCardLayout.vue:16
- **Detail**: With SSR enabled (see F2) the date is computed on the server and again in the browser; near midnight, a year boundary or a timezone difference the `{{ year }} · {{ percent }}%` text and month states can mismatch on hydration. `authYearProgress` itself is correct (UTC day count, leap years, brute-forced for 2025/2026/2028/2100). Plan Q6 explicitly chose client-side computation.
- **Fix**: Resolve together with F2: if SSR is used in production, compute the date in `onMounted` (or via a client-only block); otherwise accept.
- **Decision**: ACCEPTED — no SSR in the app (see F2), hydration mismatch cannot occur; ADR-0056 notes that SSR would need a second look

### F9 — Small leftovers

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/TextLink.vue:6,34; resources/js/layouts/auth/AuthCardLayout.vue:36-56
- **Detail**: (a) `TextLink` still declares a `tabindex` prop although the auth rule forbids tabindex; no auth page uses it. (b) The year percentage is read twice by assistive tech (visible text plus `sr-only` span). (c) An `<h2>` in the aside precedes the card's `<h1>` in DOM order. (d) `PasswordInput` button was not restyled `IconAction`-like as the plan suggested (functional change only).
- **Fix**: Remove the `tabindex` prop from `TextLink`; mark the visible year head `aria-hidden` (or drop the `sr-only` span); optionally demote the aside heading to `p`.
- **Decision**: FIXED (a, b) — `tabindex` prop removed from TextLink, visible year head `aria-hidden`; (c) h2 order and (d) IconAction styling left as is (cosmetic, no functional impact)
