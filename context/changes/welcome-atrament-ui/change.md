---
change_id: welcome-atrament-ui
title: Strona powitalna według design systemu Atrament
status: impl_reviewed
created: 2026-11-07
updated: 2026-11-07
archived_at: null
---

## Notes

Widok (jeden, strona przed logowaniem): `resources/js/pages/Welcome.vue`, `resources/js/components/LandingAppPreview.vue` i `resources/js/components/LandingFeatureCard.vue`.

Źródło tokenów: istniejący design system Atrament — wartości w `resources/css/app.css`, komponenty w `resources/js/components/ui`, opis w `context/foundation/design-system.md`. Wzorzec: karta PageWelcome (`project/components/PageWelcome`, klasy `.ht-land*` w `bundle.css`) w artefakcie https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (podgląd, nie źródło wartości; karta ma status „Propozycja”).

Wariant kontraktu: istniejący system projektowy — rozszerzamy, bez `shadcn init` i drugiej palety. Klasy `ht-*` z artefaktu nie istnieją w repo; mapujemy je na tokeny i komponenty (jak w ADR-0056).

Decyzje do ADR-0057 (następny wolny numer): panele `radius-lg` zamiast `rounded-2xl`; pas wezwania na ciemnym panelu (`sidebar-background`) z przyciskiem `variant="brand"` (`ht-btn--brand`); podgląd aplikacji z klas Dashboardu (ADR-0045); szerokość treści 1120 px; przyciski hero 36 px. ADR powstaje w pierwszej fazie planu, przed kodem.

Teksty (`lang/pl/ui.php`, `landing`): `features_lead` bez „— bez zbędnego marketingu”; `feature_privacy_description` z „klucze dostępu” zamiast „passkeys” i bez „w MVP”.

Wstępny audyt (/10x-ui, 2026-11-07): skan wartości — 1 trafienie (`LandingAppPreview.vue:40`, `min-h-[22rem]`), bez hexów i klas palety; 43 trafienia wzorców sprzed Atramentu (`rounded-2xl`/`rounded-xl`, `border-border/60`, `shadow-sm`, `tracking-tight`, `max-w-6xl`, `size="lg"`). Kandydaci na zarzuty: pas wezwania na `accent` i `rounded-2xl` (`Welcome.vue:278`); `LandingAppPreview` jako szkic z kropek z obiema miarami w `text-success` (`success` = przychód); `LandingFeatureCard` na shadcn `Card` (`rounded-xl`); ręczne `href="/register"` zamiast Wayfindera (`Welcome.vue:138,184`); brak kitchen sinka `/dev/ui/welcome`, a `LandingAppPreview` i `LandingFeatureCard` poza `scripts/check-ui-literals.mjs`. Style `display` i `card-title` czekają w `app.css` na tę zmianę (ADR-0056). Do dodania jako nazwane klasy: szerokość treści 1120 px i przyciski hero 36 px, bez edycji `ui/button`. Uwaga na testy: `LandingPageSourceTest` czyta `Welcome.vue` jako tekst, `ui-contract.test.ts:325` wymaga `BrandMark`.
