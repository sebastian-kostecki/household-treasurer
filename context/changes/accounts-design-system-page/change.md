---
change_id: accounts-design-system-page
title: Accounts design system page
status: implemented
created: 2026-11-06
updated: 2026-11-06
archived_at: null
---

## Notes

Zmiana UI przez `/10x-ui`, jeden widok: `resources/js/pages/Accounts.vue` (oraz `AccountMarkCompact` i lista banków w `app/Enums/Bank.php` i `resources/js/lib/account-identity.ts`).

- **Wariant kontraktu:** istniejący system projektowy. Rozszerzamy go, bez drugiej palety i bez `shadcn init`.
- **Źródło tokenów:** `resources/css/app.css` (`:root` / `.dark`, `@theme inline`, `@utility`); wartości z design systemu C · Atrament i morski: https://claude.ai/artifact/9gJSdtfpFgUAa8oEMgR4HA (podgląd, nie źródło wartości).
- **Wzorzec:** karta `PageAccounts` (tytuł z `Switch` „Pokaż zarchiwizowane” w akcjach przed „Dodaj konto”) i `ht-mark` z `CategoryChip` (28 px, `radius-sm`, tło `muted`, bez obwódki, JetBrains Mono 9 px / 600; skrót banku, gotówka „zł”), zgodnie z ADR-0045.
- **Nowy ADR:** 0047 (skróty banków, zakres `AccountMarkCompact`, warunkowy `Switch`).
- **Poza zakresem:** „Anuluj” w `AccountFormDialog` zostaje bez zmian.
- **Kitchen sink:** `/dev/ui/accounts` (`resources/js/pages/dev/AccountsKitchenSink.vue`, `resources/js/pages/dev/accounts-fixtures.ts`).

Wstępny audyt (2026-11-06): skan literałów 0 trafień w `Accounts.vue`, `AccountMarkCompact.vue`, `AccountsKitchenSink.vue` (wszystkie w `scripts/check-ui-literals.mjs`); jeden styl sprzed Atramentu: `border-border/60` w `AccountMarkCompact.vue:42`. Zarzuty do potwierdzenia w researchu:

1. `Switch` pod panelem miar zamiast w wierszu tytułu: `Accounts.vue:142–157` osobny rząd (ADR-0044); ADR-0045 i `PageAccounts` stawiają go w akcjach tytułu (`:128–138`) przed „Dodaj konto”. Do rozstrzygnięcia: warunek `archived_accounts_count > 0 || show_archived` (wzorzec pokazuje przełącznik zawsze; propozycja: zostawić warunek i zapisać w ADR-0047) oraz zawijanie akcji tytułu przy 390 px jako jednej grupy.
2. Logo zamiast skrótu banku: `AccountMarkCompact.vue:45–62` (`<img>` logo, `Coins`, `Building2`); ADR-0045 chce skrótu Mono (`mB`, `PKO`, `ING`, gotówka „zł”) jako pola listy banków. Lista żyje dwa razy: `app/Enums/Bank.php` (`markPath()`) i `lib/account-identity.ts:32–56`. Do rozstrzygnięcia: nowy prop Inertii (PHP + typ + `assertInertia` w jednym commicie) czy skrót liczony na kliencie z `bank` już obecnego w payloadzie. Wzorzec podaje tylko mB, PKO, ING, „A” (Alior); skróty BNP Paribas, Pekao, Revolut, Santander, Lokaty Facto i „Inny bank” ustala ADR-0047.
3. Kafelek poza przepisem `ht-mark`: `AccountMarkCompact.vue:21–23,42` `rounded-lg` (zamiast `radius-sm`) i `border-border/60`; 9 px Mono nie może być `text-[9px]` (literał), potrzebny nazwany `@utility` w `app.css` (`.ai/rules/css.md`).
4. Przypadkowa architektura, zakres współdzielonego komponentu: `AccountMarkCompact` używają też `AccountBankSelect` (wybór banku w dialogu konta), `AccountSelect` (dialogi operacji, import) i `CompactAccountIdentity` (wiersze Operacji); Dashboard i Lokaty mają `AccountIdentityBlock` z logo 40 px. README `CategoryChip` w design systemie mówi „gdy jest logo banku, logo zamiast skrótu”, sprzecznie z ADR-0045. ADR-0047 rozstrzyga: skrót wszędzie, gdzie `AccountMarkCompact`, czy wariant tylko w Kontach; oraz los `mark_url`, `public/marks/*` i `AccountIdentity::markUrl`.
5. Dostępność: `AccountMarkCompact.vue:48` `<img :alt="name">` obok nazwy (`Accounts.vue:216`), czytnik ekranu czyta nazwę dwa razy; skrót tekstowy z `aria-hidden`.

Bez zarzutu: wejście bez logowania (`auth`), dwa stany puste (`IndexEmptyState`), „Anuluj” w dialogu (poza zakresem). Odroczone: README `PageAccounts` w design systemie ma nieaktualne „Stan” i „Różnice” (sprzed ADR-0044, „Anuluj” `outline`); to treść artefaktu, nie kod.
