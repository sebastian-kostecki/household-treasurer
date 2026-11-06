---
change_id: settings-atrament-ui
title: Settings atrament ui
status: implementing
created: 2026-11-06
updated: 2026-11-06
archived_at: null
---

## Notes

Zmiana `/10x-ui`, wariant kontraktu: istniejący system projektowy (Atrament).

- Widok: `resources/js/pages/settings/Profile.vue` razem z `layouts/settings/Layout.vue` (podnawigacja ustawień) i `components/DeleteUser.vue`; polskie teksty w `pages/settings/Security.vue` (nagłówek „Update password”) i `components/AppearanceTabs.vue` (Light/Dark/System).
- Źródło tokenów: `resources/css/app.css` (`:root` / `.dark`, publikowane przez `@theme inline`); komponenty w `resources/js/components/ui/*` i `resources/js/components/`.
- Wzorzec: karta `PageSettingsProfile` w design systemie Atrament (https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA) — podgląd, nie źródło wartości.
- Decyzja: ADR-0049.
- Wstępny audyt (skan literałów): 3 trafienia, wszystkie w `AppearanceTabs.vue:16-26`; 15 linii angielskich tekstów w ustawieniach; ustawień brak w `scripts/check-ui-literals.mjs`, brak kitchen sinka `/dev/ui/...`.
