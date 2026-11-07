<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Strona powitalna według Atramentu

- **Plan**: context/changes/welcome-atrament-ui/plan.md
- **Scope**: Full plan (4 of 4 phases)
- **Reviewed phases**: 1, 2, 3, 4
- **Date**: 2026-11-07
- **Verdict**: APPROVED
- **Findings**: 0 critical, 1 warning, 6 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | PASS |
| Scope Discipline | PASS (accepted extras: sidebar replica, `landing-kpi`, `.landing-preview-row-plan`, 390 px bar fix) |
| Safety & Quality | PASS |
| Architecture | PASS |
| Pattern Consistency | WARNING (1 finding) |
| Success Criteria | PASS (automated); manual items left unchecked by request |

## Findings

### F1 — Non-focusable preview guarded only by string match, invalid `aria-pressed` on span
- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW
- **Dimension**: Pattern Consistency
- **Location**: resources/js/components/LandingAppPreview.vue:140, resources/js/ui-contract.test.ts:346
- **Detail**: `aria-pressed` on a `<span>` is invalid ARIA; the guard did not ban `<Button`, `tabindex`, `<input`.
- **Fix**: remove `aria-pressed`; extend the ban list.
- **Decision**: FIXED

### F2 — Sink test asserts only `auth.user.id`
- **Severity**: 👁 OBSERVATION · **Impact**: 🏃 LOW · **Location**: tests/Feature/WelcomeKitchenSinkTest.php:65
- **Fix**: also assert `auth.user.email`.
- **Decision**: FIXED

### F3 — ADR-0057 did not record the measured 36 px result
- **Severity**: 👁 OBSERVATION · **Impact**: 🏃 LOW · **Location**: ADR-0057 (Dimension tokens)
- **Fix**: add the measurement sentence.
- **Decision**: FIXED

### F4 — Progress rows 4.2 and 4.4 lacked a SHA
- **Severity**: 👁 OBSERVATION · **Impact**: 🏃 LOW · **Location**: plan.md Progress
- **Decision**: FIXED

### F5 — `.landing-preview` uses `bg-background`, plan said `card` panel
- **Severity**: 👁 OBSERVATION · **Impact**: 🏃 LOW · **Location**: resources/css/app.css (.landing-preview)
- **Detail**: Visually verified in screenshots; main area contrasts with the dark sidebar.
- **Decision**: ACCEPTED (needs a human eye vs. the PageWelcome card)

### F6 — Hardcoded sample data in preview (years 2025–2027, user name, ⌘K); `WelcomeHardcodedUiTest` duplicates `check:ui`
- **Severity**: 👁 OBSERVATION · **Impact**: 🔎 MEDIUM
- **Detail**: Static mock-up strings will age (years); PHP guard mirrors Dashboard's one by design (plan).
- **Decision**: ACCEPTED (mock-up data; duplication follows DashboardHardcodedUiTest)

### F7 — Landmarks: logo link to `#top`, `<nav>` without `aria-label`, no mobile nav alternative
- **Severity**: 👁 OBSERVATION · **Impact**: 🏃 LOW
- **Decision**: ACCEPTED (plan: nav from `md`)
