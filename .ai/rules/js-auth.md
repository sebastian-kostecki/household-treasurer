---
paths:
    - 'resources/js/pages/auth/**'
    - 'resources/js/layouts/auth/**'
    - 'resources/js/layouts/AuthLayout.vue'
    - 'resources/js/components/PasskeyVerify.vue'
    - 'resources/js/components/PasswordInput.vue'
    - 'resources/js/components/TextLink.vue'
    - 'resources/js/lib/auth-*.ts'
---

# Entry screens

## The layout is .auth-shell

Every entry screen sits in `AuthCardLayout` (through `AuthLayout`): `.auth-shell` with the dark panel `.auth-aside` (the `--sidebar-*` tokens) and the card `.auth-card`, widths from `--auth-aside-width` and `--auth-card-width`, switching at `lg`, not at a literal 860 px (ADR-0056). The card has a `page-title` `h1`, a `meta` description and an optional `.auth-card-icon`; no shadcn `Card`, no `rounded-xl`. The product mark is `BrandMark` with the name. The year block comes from `authYearProgress` in `resources/js/lib/auth-year-progress.ts`, not from an Inertia prop.

## A confirmation is Alert ok, never text-success

A confirmation without an amount ("link sent") is `<Alert variant="ok">` with an icon. `text-success` means income and is not a status colour; `accent` is allowed here only through that variant (ADR-0056). Errors stay `InputError` and `AlertError`.

## The main action is .auth-submit

The main action of a card is a `Button` with the class `auth-submit` (full width, 34 px); the secondary one (the passkey, "resend" on VerifyEmail) is `variant="outline"` with the same class. Do not add a size or a variant to `ui/button` for it. Links are `TextLink` (`as="button"` for a mode switch), never a hand-copied class list; the separator is `.auth-or`; the one-time code is `InputOTP` in `.auth-otp`.

## Texts come from t.auth.*, routes from Wayfinder, no tabindex

Every text is a key of `t.auth.*` in `lang/pl/ui.php` (also the labels of `PasskeyVerify` and the toggle of `PasswordInput`); no English literal and no default label in a prop. Routes and links come from Wayfinder (`@/routes`, `@/actions`), never `href="/..."` or a literal `'/dashboard'`; the registration link exists only behind `canRegister`. No `tabindex` on any entry screen: the keyboard order is the DOM order (ADR-0056), so the "Nie pamiętasz hasła?" link stands in the label row before the password field. The page sets its title and description through `setLayoutProps`, not `defineOptions`.

## A new state goes to /dev/ui/auth

The seven screens and their states (default, error, status, no-email, recovery) are in the kitchen sink at `/dev/ui/auth?screen=<screen>&state=<state>` (`local` only), which renders the real page with fixture props. Add a new screen or state there in the same commit, and add a new entry file to `scripts/check-ui-literals.mjs`.
