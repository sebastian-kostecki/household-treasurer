---
paths:
    - 'resources/js/pages/Welcome.vue'
    - 'resources/js/components/Landing*.vue'
---

# Welcome page

## Width, type and panels come from named classes

The page (`Welcome.vue`, `LandingAppPreview.vue`, `LandingFeatureCard.vue`) follows the `PageWelcome` card (ADR-0057). Every section sits in `.landing-wrap` (`--landing-width`, 1120 px), never `max-w-6xl`. Headings use the named styles: `display` for the hero `h1` (through `.landing-hero-title`), `heading` for a section `h2`, `lead` for the lead, `card-title` for a feature or step title, `label-caps` above a section heading; no Tailwind `text-*` size and no `tracking-tight`. Panels are `card` with a full `border` and `rounded-lg` (ADR-0037): no `rounded-xl|2xl`, no `border-border/N`, no `shadow-sm`, no `backdrop-blur`. The anchors `#features` and `#how` sit on `.landing-section`, which carries the `scroll-margin-top` under the 56 px bar.

## Hero and call-to-action buttons are h-control-lg

The buttons of the hero and of the call-to-action band carry the utility `h-control-lg` (36 px, `--control-height-lg`); the buttons in the bar keep the `default` size (32 px). Do not use `size="lg"` (40 px) or `size="sm"`, and do not add a size to `ui/button`. Check the computed height in the browser after a change: `Button` merges its classes with tailwind-merge. The band `.landing-cta` is the dark `--sidebar-*` panel with `Button variant="brand"`; never `bg-accent`.

## The preview is built from Dashboard blocks, aria-hidden and not focusable

`LandingAppPreview.vue` is `div.landing-preview` with `aria-hidden="true"` and holds nothing focusable: no `Link`, `a` or `button` (`CategoryMeterRow` is not used, its root is a `Link`; sidebar items and rows are `div`/`span`). It uses `StatPanel`, `ExecutionMeter`, `CategoryChip`, `ListSurface flush`, `Badge` and `BrandMark` (without the product name, ADR-0057), with money through `formatMoney` and `formatMoneyAmount`, and typed data in a local constant (`DashboardCategory`), never `pages/dev/dashboard-fixtures.ts`. No `text-success` (the measures are not income), no `min-h-[...]`; the over-limit row carries `Badge variant="destructive-soft"` with `t.budget.over_limit_badge`. The grid is `.landing-preview-row` (four columns); do not touch `.category-meter-row`.

## The only literal href is /register behind canRegister

Links come from Wayfinder (`@/routes`, `@/actions`). The one literal `href` is `/register` behind `canRegister` in `Welcome.vue`, because registration is off and Wayfinder has no route (ADR-0057); when registration is enabled the literal gives way to the route. `ui-contract.test.ts` fails on any other `href="/`.

## A new state goes to /dev/ui/welcome; WelcomeHardcodedUiTest guards the files

The three states (`guest`, `guest-register`, `authenticated`) are in the kitchen sink `/dev/ui/welcome?state=<state>` (`local` only), which renders the real `Welcome` with `auth` and `canRegister` overridden. Add a new state there in the same commit. `tests/Unit/WelcomeHardcodedUiTest.php`, `scripts/check-ui-literals.mjs` and `ui-contract.test.ts` guard the three files; a new landing file joins all three. Texts come from `t.landing.*` in `lang/pl/ui.php`, written for a guest: no jargon ("passkeys", "MVP").
