# App shell and OpenShip-inspired visual language

## Problem Statement

The product requirements describe what Household Treasurer does, but the running app is still the Laravel Vue starter: English chrome, a placeholder Dashboard, default shadcn colours, and no places for Budget, Accounts, or Operations. The User cannot tell this is a Polish household budget tool, cannot navigate the MVP information architecture, and has no agreed visual system. Implementing domain features on the starter look would lock in the wrong language, density, and screen map.

## Solution

Restyle the authenticated shell and auth screens to an OpenShip-inspired language (dense sidebar and cards, teal primary) implemented in the existing Tailwind + shadcn-vue kit, with light as the default theme. Expose a Polish information architecture: Dashboard, Budget, Accounts, Operations, and Settings, plus a year switcher in the sidebar header. Pages are real routes with empty or first-run content; they do not yet persist Accounts, Budgets, or Financial Operations. CSV import remains an action on Operations, not a top-level nav item.

Visual reference: Openship dashboard language (https://github.com/oblien/openship), reimplemented — not forked. Recorded in ADR-0001.

## User Stories

1. As a User, I want the app to look like a calm household tool on a light background, so that it does not feel like a server console when I open it.
2. As a User, I want to switch to dark appearance, so that I can use the same product at night with the same meaning of colours.
3. As a User, I want primary actions and focus rings in teal (`#0b7285`), so that the product has one recognisable accent.
4. As a User on a large screen, I want a dense sidebar and tight card layout, so that I can see balances and execution without scrolling past empty chrome.
5. As a User on a phone, I want larger tap targets and a looser layout than desktop, so that I can use the same flows with a finger.
6. As a User, I want the interface in Polish, so that labels match how I think about a household budget.
7. As a User who is not signed in, I want login, register, and password-reset screens in the same visual language (card on a light background), so that signing in does not feel like a different product.
8. As a User, I do not need a marketing landing page in this work, so that effort goes into the logged-in app.
9. As a signed-in User, I want a persistent sidebar with Dashboard, Budżet, Konta, Operacje, and Ustawienia, so that I always know where I am.
10. As a signed-in User, I want a Budget year control in the sidebar header at all times, so that every screen is about one year without hunting for a filter.
11. As a User with no Budget years yet, I want the year control to show an empty or “add year” state, so that I am not stuck with a fake year.
12. As a User, I want Dashboard to summarise Liquidity and Execution, so that I see cash and plan progress in one place without editing the plan there.
13. As a User on Dashboard, I want a top row of cards for total funds and Account balances, so that Liquidity is not mixed into category numbers.
14. As a User on Dashboard, I want a lower section for Category execution (income and expense), so that I can compare plan versus booked amounts.
15. As a User looking at an expense Category, I want a simple progress bar against the Current Plan, so that I see limit usage without charts.
16. As a User who has just registered, I want a Dashboard checklist (create an Account, create a Budget, add Categories, start execution), so that I know the first path without a blocking wizard.
17. As a User, I want a Budget page that is not the Dashboard, so that planning work has its own place.
18. As a User on Budget, I want one page: phase (draft or execution), a start-execution action when still in draft, then income and expense tables with plan and execution columns, so that I can compare Initial Plan, Current Plan, and Execution in one row.
19. As a User in draft, I want to understand that in-place plan editing belongs on this page later, so that the layout already matches how I will change amounts.
20. As a User, I want an Accounts page in the nav, so that I have a home for bank and cash Accounts even before they persist.
21. As a User, I want an Operations page in the nav, so that Financial Operations and Own Transfers have a list home.
22. As a User on a large screen, I want Operations as a table, so that many rows stay scannable.
23. As a User on a phone, I want Operations as cards, so that I do not have to scroll the table sideways.
24. As a User, I want Import CSV as a button on Operations, not its own nav item, so that import is clearly “another way to add operations”.
25. As a User, I want Settings to remain profile, security, and appearance, in Polish, so that account-of-login settings stay where the starter put them.
26. As a User, I want amounts formatted as `1 234,56 zł` and dates as `DD.MM.RRRR`, so that numbers are unambiguous in Polish.
27. As a User, I want over-limit Execution and negative Account balances in red, income and positive amounts in green, and archived things in muted grey, so that warnings are visible without reading every label.
28. As a User, I want teal used for brand and actions, not for danger, so that red still means “over” or “negative”.
29. As a User, I want archived Accounts and Categories hidden on working lists, with a “Pokaż zarchiwizowane” filter, so that daily work stays clean while history remains reachable.
30. As a User, I want field errors next to the fields, a toast when a save succeeds, and a confirm step before destructive actions, so that I do not lose data by accident and I get confirmation when something worked.
31. As a User, I want starter documentation and repository footer links removed from the app sidebar, so that the product does not advertise the Laravel kit.
32. As a User, I want page titles and breadcrumbs in Polish, so that the chrome matches the nav.
33. As a developer, I want money still stored and calculated as integer minor units when domain data exists later, so that this UI work does not introduce float amounts.
34. As a User, I want the word “konto” in the UI to mean an Account (bank or cash), not my login, so that I am not confused with User settings.
35. As a User visiting Welcome, I want either a simple redirect into auth/dashboard or an unchanged starter page that is not part of this visual system, so that we do not build a marketing site.

## Implementation Decisions

- Follow ADR-0001: OpenShip visual language on shadcn-vue; no Next.js, no Openship `@repo/ui`, no new CSS framework, no new UI kit dependency.
- Keep Laravel + Inertia + Vue + TypeScript. Do not add a REST API, vue-i18n, or a browser-test plugin for this spec.
- Theme: set CSS tokens so `--primary` is `#0b7285` (and matching foreground). Light is the default appearance for new Users and first paint; dark uses the same primary and semantic colours on dark surfaces. Keep the existing appearance switch.
- Typography: keep the starter sans font. Change colour, radius, and spacing density to read closer to an ops dashboard on desktop; increase padding and control size at typical mobile breakpoints.
- Auth screens: use the card auth layout, Polish copy, same primary token. Do not add a split marketing pane.
- Welcome / home route: out of the visual system. Prefer leaving it as-is or sending guests to login; do not restyle it as an OpenShip marketing page.
- Shell: keep the existing sidebar app layout. Primary nav (Polish labels): Dashboard, Budżet, Plan miesiąca, Konta, Lokaty, Operacje; Ustawienia live in the user menu, not in the nav. Remove kit footer links (repository, Laravel docs).
- Budget year switcher: always visible in the sidebar header. The control is a button with a menu of years and "Dodaj rok"; in the collapsed icon strip it shrinks to an icon with a tooltip. Shared Inertia data should expose the selected year (or null) so every page shares one context. No domain persistence of Budget yet; the control can be a non-functional or empty-state control that is already in the chrome.
- New authenticated Inertia pages (empty/first-run, not full CRUD): Dashboard (checklist + Liquidity card row + Execution section with expense progress bars in empty/zero state), Budget (phase badge, start-execution control disabled or explained as upcoming, two tables), Accounts (empty list), Operations (empty table/cards + Import CSV control that can be disabled or explain upcoming).
- Do not implement Account, Budget, Category, Financial Operation, Own Transfer, or CSV import persistence in this spec. Domain rules stay in the PRD for later tickets.
- Polish UI copy lives in Laravel language files (or equivalent already in the repo) and is passed into pages as needed. Code, logs, and glossary remain English (`User`, `Account`, `Budget`, … per CONTEXT.md).
- Number and date formatting helpers must use the Polish patterns above whenever amounts or dates are shown. Do not rely on the browser locale.
- Semantic colours: destructive/red for over-limit and negative balance; a green token for income/positive; muted for archived. Do not let Users pick Category colours.
- Feedback: keep Inertia inline validation; success toasts for completed writes when those writes exist (may only apply to settings/auth in this spec); confirm dialogs for destructive starter actions that already exist (e.g. profile/security) when those screens are translated.
- Archived filter UI can be present in a disabled or empty-list form on Accounts and Budget; behaviour without records is “no archived rows”.
- Shared Inertia props (extend the existing share middleware): at least appearance-related defaults already in the kit, plus shell fields needed for tests (selected Budget year or null, first-run checklist flags all false/incomplete when no domain tables exist).
- Wayfinder-generated route helpers remain the way Vue reaches named routes.

## Testing Decisions

- A good test asserts what a User can reach and what the server sends to Inertia, not Tailwind class names, hex values, or screenshot diffs.
- Single seam: existing Pest feature tests over HTTP (the same seam as Dashboard and auth tests). Assert guests are redirected from authenticated routes; signed-in Users get 200; `assertInertia` (or equivalent already used when added) checks the page component name for Dashboard, Budget, Accounts, Operations, and settings.
- Assert shared props for selected year (null) and first-run flags rather than querying Vue internals. If Polish strings are in the Inertia payload or document for login/register, assert those labels; do not add `pest-plugin-browser`.
- Do not unit-test CSS token files. Visual regression is out of this spec.
- Prior art: `tests/Feature/DashboardTest.php` and `tests/Feature/Auth/*` (PHPUnit-style TestCase, `RefreshDatabase`, `actingAs`, `route()`). New tests should match neighbouring files (Pest PHPUnit classes already in Feature). Cover failure modes: guest cannot open Budget/Accounts/Operations; unknown appearance still defaults to light on first visit if that behaviour is implemented.

## Out of Scope

- Domain persistence and business rules from the PRD (Accounts, Budgets, Categories, Financial Operations, Own Transfers, CSV import, execution start that writes an Initial Plan).
- Forking or embedding Openship source.
- Changing tech stack, adding Redis, REST, WorkOS, or extra CSS libraries.
- Marketing website, charts beyond a simple expense progress bar, User-picked colours, i18n language switcher, monthly views, shared household Budgets, currencies other than PLN.
- Real-browser / visual snapshot tests.
- Implementing the year switcher against a Budget table (chrome only).

## Further Notes

- Glossary: `CONTEXT.md`. Product requirements: `.docs/prd.md`, `.docs/mvp.md`. Stack: `.docs/tech-stack.md`. Visual ADR: `.docs/adr/0001-openship-visual-language.md`.
- Next step after this spec is `/to-tickets` (tracer-bullet tickets with blocking edges), then `/implement` per ticket. Do not implement this spec in the same change as domain CRUD.
- Openship is a visual reference only. Navigation labels and domain words must stay Household Treasurer (Budget vs Liquidity), never “project” or “deployment”.
