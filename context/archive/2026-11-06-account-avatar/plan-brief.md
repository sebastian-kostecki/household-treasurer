# Awatar konta z logo banku wszędzie — Plan Brief

> Full plan: `context/changes/account-avatar/plan.md`
> Research: `context/changes/account-avatar/research.md`

## What & Why

Logo banku przy koncie widać dziś tylko na Dashboardzie. Wszędzie indziej konto ma tekstowy skrót („mB”, „PKO”) albo samą nazwę, także na liście Operacji, gdzie pracuje się najwięcej. Zmiana wprowadza jeden znak konta, `AccountAvatar`, z logo banku w każdym miejscu, gdzie widać konto lub lokatę. Gdy logo brak, pokazuje kafelek ze skrótem.

## Starting Point

Są dwa niezależne kafelki: `AccountMarkCompact` (skrót, 20/28 px, w Kontach, Lokatach, selectach i przelewach) i `AccountIdentityBlock` (logo, 24/40 px, tylko Dashboard). Ten podział zapisał ADR-0047, a powtarzają go `.ai/rules/js.md` i `design-system.md`. Prawie wszystkie payloady niosą już `mark_url`. Brakuje go na stronie Lokat, w ruchach kapitału i u kandydatów na parę w imporcie.

## Desired End State

To samo konto wygląda tak samo na Dashboardzie, w Kontach, w wierszu operacji, w filtrze i chipie, w przelewie, w ruchu kapitału, w imporcie i w ostrzeżeniu Dashboardu. Lokaty mają logo w tabeli Lokat, na Dashboardzie i w ruchach kapitału. Gotówka („zł”), bank „Inny”, ING/Pekao/Santander i obraz, który się nie wczytał, pokazują kafelek skrótu. Reguły i ADR-0054 opisują już tylko ten jeden znak.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Jeden znak wszędzie | `AccountAvatar` z logo, fallback skrót/ikona; stare kafelki usunięte | Jedno konto, jeden znak, a reguły przestają sterować podziałem | Research (decyzja użytkownika) |
| Nowy ADR | ADR-0054 zastępuje w części ADR-0047, 0045, 0051 | Zmiana odwraca zapisaną decyzję „skrót zamiast logo” | Research |
| ING, Pekao, Santander | Traktowane jak brak logo: `markPath()` → null, pliki usunięte | Pliki były skrótem na kolorowym tle, nie logo | Plan |
| Skala rozmiarów | xs 16 (linie tekstu, chipy), sm 20 (listy wyboru), md 28 (tabele, wiersze, Dashboard) | Jedna skala, a 16 px mieści się w linii meta wiersza operacji | Plan |
| Natywne selecty | Filtr konta w Operacjach i kandydat na parę na reka `Select` z awatarem | Reguła „opcja ze znakiem → reka `Select`”; konto widać tam, gdzie się je wybiera | Plan |
| Lokaty | Awatar tam, gdzie lokata to wiersz (Lokaty, Dashboard, ruch kapitału); selecty lokat bez awatara | Dwie zmiany payloadu zamiast pięciu | Plan |
| Wiersz operacji | Awatar xs w linii meta przed nazwą konta, siatka bez zmian | ADR-0039 bez zmian, kafelek typu zostaje jedynym kafelkiem na początku | Plan |
| Dopasowanie logo | `object-contain` na `muted`, `radius-sm`, bez ramki, bez nowego tokenu | Logo nie jest przycinane i wygląda jak kafelek skrótu | Plan |
| Kandydat na parę | Serwer wysyła `account_type/bank/mark_url` przez `AccountIdentity::prefixedRowFields` | Bez szukania na froncie i bez luki „kandydat to konto importu” | Plan |
| Dostępność | `aria-hidden`, `alt=""` | Nazwa zawsze stoi obok; `alt=name` czytał ją dwa razy | Research (ADR-0047) |
| Błąd obrazu | `@error` → skrót banku, stan zerowany przy zmianie `mark_url` | Zepsuty plik nie zostawia pustego kafelka | Plan |

## Scope

**In scope:**
- ADR-0054, `.ai/rules/js.md`, `design-system.md`
- `Bank::markPath()`, `mark_url` Lokat, bank i logo lokaty w ruchach kapitału, tożsamość kandydata na parę, typy filtra konta (z `assertInertia`)
- `AccountAvatar` i czysta funkcja `accountAvatarContent` z testem; podmiana w 8 komponentach; usunięcie `AccountMarkCompact` i `AccountIdentityBlock`
- Nowe miejsca: wiersz operacji, filtr i chip konta, lokata w ruchu kapitału, kandydat na parę, ostrzeżenia Dashboardu
- Kitchen sinki (macierz 7 stanów, oba motywy), `check:ui`, strażnicy `*HardcodedUiTest.php`

**Out of scope:**
- Logo w `AccountBankSelect` (wybiera bank, nie konto, i nie ma URL-i)
- Selecty lokat, zdanie podsumowania importu, `AccountFormDialog`
- Prawdziwe logo ING/Pekao/Santander i licencje plików
- Awatar użytkownika w sidebarze (ADR-0053), nowe tokeny, glossary

## Architecture / Approach

Serwer pozostaje źródłem `mark_url` (`AccountIdentity`, `Bank::markPath()`). Klient dostaje wąskie propsy `type/bank/mark_url`, a `accountAvatarContent` wybiera logo, skrót albo ikonę. `AccountAvatar` renderuje kafelek i obsługuje błąd obrazu. `CompactAccountIdentity` łączy awatar z nazwą dla wierszy, a `AccountSelect` (rozszerzony o opcję „wszystkie” i dopisek „zarchiwizowane”) obsługuje listy wyboru.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Kontrakt | ADR-0054 i przepisane reguły | Pominięta linia w `design-system.md` zostawi starą regułę |
| 2. Dane | `mark_url`/`bank` we wszystkich potrzebnych payloadach, 3 SVG usunięte | Nowy N+1 w detektorze par; zmiana asercji w `OperationsIdentityTest` |
| 3. Komponent | `AccountAvatar` zamiast obu starych kafelków | Skrót 3 znaków ciasny w xs 16 px; Dashboard 24 → 28 px |
| 4. Brakujące miejsca | Awatar w wierszu operacji, filtrze, chipie, ruchu kapitału, parze, ostrzeżeniach | Zmiana zachowania klawiatury i mobilnej listy w dwóch selectach; payload formularza importu |
| 5. Stany i bramka | Macierz 7 stanów, oba motywy, pełny gate na PostgreSQL | Kontrast przezroczystego logo w trybie ciemnym |

**Prerequisites:** stos Sail z PostgreSQL; `npm run build` po zmianach typów (Wayfinder).
**Estimated effort:** ~3–4 sesje w 5 fazach (fazy 1–2 krótkie, 3–4 główna praca).

## Open Risks & Assumptions

- Ciemne logo z przezroczystym tłem (Revolut) może mieć słaby kontrast na `muted` w trybie ciemnym. Faza 5 to sprawdza; nowy token wymagałby osobnej decyzji.
- Trzy banki (ING, Pekao, Santander) do czasu dostarczenia prawdziwych plików pokazują skrót zamiast koloru.
- Zakładamy, że reka `Select` w filtrze i parze nie psuje istniejących testów E2E. Lista e2e dotyka tylko `AccountBankSelect` i formatu importu.

## Success Criteria (Summary)

- To samo konto ma ten sam znak w każdym widoku, z logo, jeśli bank je ma.
- Brak logo albo błąd obrazu zawsze daje czytelny kafelek skrótu, nigdy pusty kwadrat.
- Następny agent czyta w regułach o jednym `AccountAvatar`, a `check:ui` i strażnicy go pilnują.
