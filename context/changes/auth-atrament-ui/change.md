---
change_id: auth-atrament-ui
title: Ekrany wejścia według design systemu Atrament
status: implemented
created: 2026-11-07
updated: 2026-11-07
archived_at: null
---

## Notes

Widok (jeden, wejście do aplikacji): `resources/js/layouts/auth/AuthCardLayout.vue` oraz `resources/js/pages/auth/*` (Login, Register, ForgotPassword, ResetPassword, VerifyEmail, ConfirmPassword, TwoFactorChallenge) i `resources/js/components/PasskeyVerify.vue`.

Źródło tokenów: istniejący design system Atrament — wartości w `resources/css/app.css`, komponenty w `resources/js/components/ui`, opis w `context/foundation/design-system.md`. Wzorzec: karty PageLogin, PageRegister, PageForgotPassword, PageResetPassword, PageVerifyEmail, PageConfirmPassword i PageTwoFactorChallenge w artefakcie https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (podgląd, nie źródło wartości).

Wariant kontraktu: istniejący system projektowy — rozszerzamy, bez `shadcn init` i drugiej palety. Klasy `ht-*` z artefaktu nie istnieją w repo; mapujemy je na tokeny i komponenty.

Decyzje do ADR-0056: układ `ht-auth` z ciemnym panelem, `ht-btn--block` 34 px, `ht-link`, `ht-alert--ok` zamiast `text-success`, style tekstu display/heading/lead/card-title.

Teksty: angielskie napisy z VerifyEmail, TwoFactorChallenge i PasskeyVerify przenieść do `lang/pl/ui.php` (najpierw istniejące klucze `auth.verify_email`, `auth.two_factor`).

Wstępny audyt (/10x-ui, 2026-11-07): kandydaci na zarzuty — `text-success` jako goły tekst statusu; zduplikowane klasy linku z paletą `neutral` (`TextLink.vue:21`, `TwoFactorChallenge.vue:92,124`); układ karty zamiast panelu `ht-auth`; teksty angielskie mimo istniejących kluczy; `href="/register"` i fallback `'/dashboard'` zamiast Wayfindera, zdublowany `tabindex="5"`. Brak kitchen sinka auth w `/dev/...`; `check:ui` obejmuje tylko `AuthCardLayout.vue`.
