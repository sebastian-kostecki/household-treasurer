# Konta według strony design systemu (ADR-0045) — Plan Brief

> Full plan: `context/changes/accounts-design-system-page/plan.md`
> Research: `context/changes/accounts-design-system-page/research.md`

## What & Why

ADR-0045 przywrócił stronę `PageAccounts` design systemu jako wzorzec dla Kont, ale kod za nim nie nadąża. Switch „Pokaż zarchiwizowane” stoi pod panelem miar zamiast w wierszu tytułu, a kafelek banku pokazuje logo zamiast skrótu w Mono. Ta zmiana wyrównuje kod do dokumentacji i ustala skróty banków, które ADR-0045 zostawił zmianie wdrażającej.

## Starting Point

`Accounts.vue:142-157` ma Switch w osobnym rzędzie, a `AccountMarkCompact.vue` renderuje `<img>` z `mark_url` z obwódką `border-border/60`. Payload ma już `bank` przy każdym koncie, więc skrót nie wymaga zmian w PHP. Kafelek ma sześć użyć: tabela Kont, wiersze Operacji (28 px), wybór konta i wybór banku (20 px).

## Desired End State

Wiersz tytułu Kont ma Switch przed „Dodaj konto”, a całą grupę zawija przy 390 px. Kafelek banku wszędzie, gdzie jest `AccountMarkCompact`, ma tło `muted`, `radius-sm`, bez obwódki i skrót JetBrains Mono 9 px: ALI, BNP, ING, LF, mB, PEK, PKO, REV, SAN, a dla gotówki „zł”. „Inny bank” pokazuje ikonę `Building2`. Dashboard i Lokaty (`AccountIdentityBlock`) na razie zostają przy logo.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Źródło skrótu | `Record<AccountBank, …>` + `accountShortMark()` w `account-identity.ts`, bez propsa Inertii | `bank` już jest w payloadzie, a pełny `Record` łapie nowy bank w `types:check` | Research |
| Wartości skrótów | ALI, BNP, ING, LF, mB, PEK, PKO, REV, SAN; gotówka „zł”; „Inny” = ikona `Building2` | Jedna reguła (≤3 znaki), Alior czytelny bez legendy | Plan |
| Wariant `sm` | Zostaje 20 px, tylko w listach wyboru | Listy i wiersze nie zmieniają wysokości; 3 znaki Mono 9 px się mieszczą | Plan |
| Warunek Switcha | Widoczny przy zarchiwizowanych kontach albo włączonym filtrze (wbrew karcie) | Przełącznik bez archiwum nic nie robi; stan pusty sinka zostaje | Plan |
| Zakres komponentu | Skrót we wszystkich użyciach `AccountMarkCompact`; `AccountIdentityBlock` bez zmian | Polecenie zmiany; Dashboard i Lokaty to osobny widok | Research |
| Propsy kafelka | Tylko `type`, `bank`, `size`; `aria-hidden` na kafelku | Nazwa stoi obok, a `v-bind` całego konta przenosiło `id` do DOM | Plan |
| Styl 9 px | Nowy `@utility mark-label` w `app.css` | Literał `text-[9px]` łamie straż i `.ai/rules/css.md` | Research |
| Straż | `AccountMarkCompact.vue` dochodzi do `AccountsHardcodedUiTest` | Po usunięciu obwódki nic go już nie wyklucza | Plan |
| Numer ADR | 0047 | Wybrany w `change.md` | Research |

## Scope

**In scope:**
- ADR-0047, `design-system.md`, reguła w `.ai/rules/js.md`
- `account-identity.ts` (+ test), `app.css` (`mark-label`), `AccountMarkCompact`, jego konsumenci (`Accounts.vue`, `AccountBankSelect`, `AccountSelect`, `CompactAccountIdentity`)
- Switch w wierszu tytułu `Accounts.vue`
- Sekcja „Znaki banków” i macierz stanów w `/dev/ui/accounts`, zrzuty 390/1280 w obu motywach

**Out of scope:**
- `AccountIdentityBlock` (Dashboard, Lokaty) i usuwanie `mark_url`, `Bank::markPath()`, `public/marks/*`
- „Anuluj” w `AccountFormDialog`, README w artefakcie claude.ai, glossary
- Jeden rozmiar 28 px, Switch zawsze widoczny

## Architecture / Approach

Najpierw decyzja w ADR, potem kod od czystej funkcji w górę: `accountShortMark()` z testem Vitest, styl `mark-label`, kafelek, konsumenci i straż. Na końcu przeniesienie Switcha, sink i bramka wizualna. Payload Inertii i testy Feature się nie zmieniają.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzje i dokumenty | ADR-0047, `design-system.md`, reguła w `js.md` | Sprzeczność z README artefaktu (odnotowana w ADR) |
| 2. Skrót banku w `AccountMarkCompact` | Kafelek `ht-mark` ze skrótem we wszystkich użyciach, straż | Fallthrough atrybutów przy `v-bind`; 3 znaki w 20 px |
| 3. Switch w wierszu tytułu i bramka wizualna | Switch w tytule, sink ze znakami banków, zrzuty | Zawijanie akcji przy 390 px |

**Prerequisites:** stos Sail uruchomiony (testy, `ci:check`, E2E), `npm run build` przed podglądem sinka.
**Estimated effort:** ~1 sesja, 3 małe fazy.

## Open Risks & Assumptions

- Przejściowa niespójność: ten sam bank ma skrót w Kontach, Operacjach i dialogach, ale logo na Dashboardzie i w Lokatach, dopóki `AccountIdentityBlock` nie przejdzie na skrót.
- Zmieszczenie 3 znaków w 20 px wynika z metryki fontu (ok. 16 px), nie z pomiaru. Potwierdzają to sink i zrzuty.
- Kolor skrótu (`foreground`) przyjęto bez odczytu `ht-mark` z artefaktu. Jeśli karta mówi inaczej, zmienia się jedna klasa w kafelku.

## Success Criteria (Summary)

- Konta wyglądają jak `PageAccounts`: Switch w wierszu tytułu, skróty banków w tabeli, bez obwódki kafelka.
- Ten sam bank ma ten sam skrót w tabeli Kont, wierszach Operacji i listach wyboru konta i banku.
- `composer ci:check`, `sloppy ci` i E2E edycji konta przechodzą bez zmian w payloadzie i asercjach Feature.
