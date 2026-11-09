---
change_id: account-avatar
title: Account avatar
status: archived
created: 2026-11-06
updated: 2026-11-06
archived_at: 2026-11-06T23:13:58Z
---

## Notes

Zgłoszenie (`/10x-ui`): „account-avatar widzę ze na widokach tam gdzie jest avatar konta prawie nigdzie nie ma ikony konta (poza dashboardem), trzeba to poprawić aby te avatary były, takze na design system”.

**Wariant kontraktu:** istniejący system projektowy (Atrament, `context/foundation/design-system.md`, tokeny w `resources/css/app.css`). Rozszerzamy go, bez drugiej palety.

**Widok wiodący:** lista Operacji (`resources/js/pages/Operations.vue`, `FinancialOperationRow`, filtry i chipy). Zmiana dotyka też wspólnego komponentu znaku konta, więc dochodzą miejsca z nazwą konta: import (podgląd, podsumowanie), selecty, Konta, Lokaty, Dashboard.

**Zakres:** konta finansowe (Account). Avatar użytkownika w sidebarze (`UserInfo`, ADR-0053) bez zmian.

**Decyzja użytkownika:** jeden znak konta wszędzie, z logo banku (`mark_url`, `public/marks/*`). Gdy logo brak (gotówka, bank „Inny”, błąd obrazu), kafelek ze skrótem lub ikoną. Wymaga nowego ADR (kolejny wolny numer), który zastąpi część ADR-0047 („skrót zamiast logo”, logo tylko na Dashboardzie).

**Wstępny audyt (stan na 2026-11-06):**

- Dashboard: logo banku przez `AccountIdentityBlock` (`DashboardHoldings.vue:109`).
- Konta, Lokaty, selecty, wiersze przelewów i ruchów kapitału: kafelek ze skrótem tekstowym (`AccountMarkCompact`, `CompactAccountIdentity`), `mark_url` ignorowany.
- Bez znaku: wiersz operacji (`FinancialOperationRow.vue:49-50`), filtry (`OperationsFilters.vue:370`), chipy filtrów (`OperationsActiveFilterChips.vue:135`), import (`OperationsImportPreview.vue:256`, `ImportPreviewRow.vue:240`).
- Skan wartości zakodowanych na sztywno na tych plikach: 0 trafień.
- Do ustalenia w planie: które payloady z PHP niosą `type`, `bank`, `mark_url`; zmiana propów Inertia idzie w jednym commicie z `defineProps<>` i testem `assertInertia`.
- Kitchen sinki do rozszerzenia: `/dev/ui/accounts`, `/dev/ui/dashboard`, Operacje, podgląd importu; nowy komponent do listy w `scripts/check-ui-literals.mjs`.
