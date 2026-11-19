# Dokumenty zgodne z kodem — Plan Brief

> Full plan: `context/changes/docs-match-code/plan.md`

## What & Why

Słownik i ADR mają dwie pułapki. Po pierwsze opisują sześć terminów, których kod nie ma: Cash Expectation, Agenda, Settlement candidate, Year Close, Reserve i Earmark. Do tego 24 żywe wpisy stawiają oparte na nich reguły. Category każe odmówić usunięcia kategorii z Cash Expectation, a Account blokuje usunięcie konta, choć kod niczego takiego nie sprawdza. Po drugie numer ADR 0008 ma dwa pliki. Agent, który czyta słownik jako prawdę o kodzie, buduje na nieistniejących modułach albo cytuje niejednoznaczny ADR (S-46, MS-09).

## Starting Point

Przegląd słownika wobec kodu pokazał, że tylko tych sześć terminów nie ma żadnej kotwicy w `app/`, migracjach, `resources/js`, `lang/` ani `tests/`. ADR-0008 o snapshotach cytują ADR-0013 i archiwum. ADR-0008 o parze cross-Import nie cytuje nikt. Stare ADR zmienia się dopiskiem „Superseded/Amended by ADR-NNNN:” na końcu pliku.

## Desired End State

Słownik opisuje tylko to, co jest w kodzie, a ADR-0104 mówi, dlaczego sześć terminów zniknęło i od czego zaczyna się ich ewentualny powrót: od nowego wpisu i ADR. Numery ADR są unikalne, a test w `composer ci:check` łapie następną kolizję przy równoległych zmianach. AGENTS.md mówi agentowi, że ADR z dopiskiem „Superseded by ADR-0104” to niezbudowane projekty.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Terminy bez kodu | Usunąć wszystkie sześć ze słownika | Słownik ma mówić tylko prawdę o kodzie, a projekt zostaje w ADR 0017–0022, 0030 i w spec w archiwum. | Plan (użytkownik) |
| Year Close | Znika teraz, nie czeka na „Rok 2027” | Wynika z wyboru „usunąć wszystkie”; podsumowanie roku, jeśli powstanie, dostanie własny ADR. | Plan |
| Nazwy w listach _Avoid_ | Usunąć wszystkie wzmianki | Jedna reguła bez wyjątków i twardy dowód `grep`; słowa bez definicji nie mówią, z czym kolidują. | Plan (użytkownik) |
| Który ADR 0008 zmienia numer | Para cross-Import → 0103 | Nikt go nie cytuje, więc zmiana nie wymaga poprawiania żadnego odnośnika. | Plan |
| Stare ADR | „Superseded by ADR-0104” w 0017–0022, 0029, 0030; „Amended by ADR-0104” w 0031, 0063, 0067, 0078, 0086, 0091 | Konwencja repo (ADR-0014, ADR-0030), a treść decyzji zostaje jako zapis. | Plan |
| Strażnik numerów | Test Pest `tests/Unit/AdrNumberingTest.php` | M-5 ma kilka zmian z ADR idących równolegle; wzór testu czytającego plik już jest (`DesignTokensTest`). | Plan (użytkownik) |
| Kod | Tylko komentarz w `CategoryTypeChange.php:13`; strażnik `cash_expectations` w `ResetUserData` zostaje | Zmiana zachowania kodu wymaga stanu bazy produkcji, którego stąd nie widać. | Plan |

## Scope

**In scope:**
- `context/foundation/glossary.md`: 6 usuniętych wpisów i zdania w 24 żywych wpisach
- ADR-0104, dopiski w 14 starych ADR, zmiana numeru 0008 → 0103
- `tests/Unit/AdrNumberingTest.php`
- `AGENTS.md:10`, komentarz w `CategoryTypeChange.php`, dwa wiersze w `development-directions.md`

**Out of scope:**
- Strażnik `cash_expectations` w `ResetUserData.php`
- PRD, `shape-notes.md`, datowane raporty (`health-check.md`, `stack-assessment.md`) i `context/archive/`
- Luki 0025–0028, 0036, 0046 i numery innych ADR
- Polskie napisy ze słownika nieobecne w UI („saldo na dzień”, „pokrycie zebranych”)

## Architecture / Approach

Najpierw numeracja: test jest czerwony, potem `git mv` na 0103, potem test zielony. Następnie decyzja w ADR-0104 i dopiski w starych ADR. Na końcu słownik i dokumenty, które się na niego powołują. Taką kolejność wymaga AGENTS.md:9: ADR stoi przed zmianą reguły słownika. W słowniku usuwam zdania, a nie piszę nowych reguł. Jedyne nowe sformułowania to wyliczenia w Account i Category dopasowane do kodu (`Account.php:118-132`, `CategoryTypeChange`).

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Unikalne numery ADR | Test Pest i 0008-cross-import → 0103 | Inna zmiana M-5 weźmie 0103 wcześniej (`parallel-changes.md:27`) |
| 2. ADR-0104 i noty | Decyzja i dopiski Superseded/Amended w 14 ADR | Dopisek „Amended” wymienia klauzulę, której w ADR nie ma |
| 3. Słownik i ślady | 6 wpisów mniej, 24 wpisy bez reguł bez kodu, nowa reguła AGENTS.md | Urwane zdanie albo wyliczenie po usunięciu klauzuli |

**Prerequisites:** brak. Worktree `docs-match-code` jest zbudowany na lokalnym `development`.
**Estimated effort:** jedna krótka sesja, trzy commity.

## Open Risks & Assumptions

- M-5 i S-46 w `roadmap.md` są niezacommitowane w głównym checkoucie, więc status `planning` trzeba tam ustawić ręcznie.
- `development-directions.md` ma w głównym checkoucie niezacommitowane zmiany w wierszach ~43–73. Ta zmiana dotyka wierszy 134 i 145, więc scalenie powinno przejść bez konfliktu.
- Zakładam, że na produkcji nie ma danych w `cash_expectations`. Strażnik w `ResetUserData` zostaje, więc to założenie niczego nie psuje.

## Success Criteria (Summary)

- `grep` nazw sześciu terminów w słowniku zwraca zero, a każdy wpis Account i Category opisuje to, co sprawdza kod.
- „ADR-0008” wskazuje jeden plik, a `AdrNumberingTest` przechodzi w `composer ci:check`.
- Agent, który czyta AGENTS.md, wie, że ADR z dopiskiem „Superseded by ADR-0104” to niezbudowane projekty.
