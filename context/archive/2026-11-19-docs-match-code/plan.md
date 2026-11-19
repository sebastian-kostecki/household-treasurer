# Dokumenty zgodne z kodem — plan implementacji

## Overview

Słownik (`context/foundation/glossary.md`) ma opisywać tylko to, co jest w kodzie, a numer ADR ma wskazywać jeden plik (S-46, MS-09 w `context/foundation/roadmap.md`). Plan:

- usuwa ze słownika sześć terminów, które zaprojektowano, ale nigdy nie zbudowano: Cash Expectation, Agenda, Settlement candidate, Year Close, Reserve i Earmark;
- usuwa zdania, które od nich zależą, w 24 żywych wpisach, także nazwy z list _Avoid_;
- zapisuje tę decyzję w nowym ADR-0104 i dopisuje notę w starych ADR;
- przenosi drugi ADR 0008 na 0103 i dodaje test, który pilnuje unikalnych numerów ADR.

Zachowanie aplikacji się nie zmienia. Jedyna zmiana w `app/` to usunięcie nawiasu z komentarza.

## Current State Analysis

- **Dwa pliki 0008.** `0008-user-domain-snapshots-via-artisan.md` jest cytowany jako ADR-0008 w `adr/0013-application-backups-via-spatie.md:5` i w trzech dokumentach archiwum (`2026-09-10-agenda-cash-expectations/spec.md:86`, `2026-11-18-year-turn-rehearsal/research.md:178`, `2026-11-09-category-list-domain-decisions/plan.md:92`). Każde z tych odwołań dotyczy snapshotów. `0008-cross-import-own-transfer-pair.md` nie jest cytowany numerem ani nazwą pliku nigdzie: w `context/`, `.ai/`, `AGENTS.md`, `app/`, `resources/js/` i `tests/` (`grep -rnw 0008`). Oba pliki weszły w jednym commicie `55a5330` (2026-11-06). Ostatni numer to 0102, więc wolne są 0103 i 0104.
- **Luki 0025–0028, 0036, 0046.** W historii gita nigdy nie było tych plików (`git log --diff-filter=D` pusty), więc nie ma czego przywracać.
- **Sześć terminów bez kodu.** Przegląd całego słownika wobec `app/`, migracji, tras, `resources/js`, `lang/` i `tests/` pokazał, że kodu nie mają tylko Cash Expectation, Agenda, Year Close, Reserve, Earmark i Settlement candidate. Każdy inny termin ma kotwicę w kodzie. Ślady w kodzie:
  - `app/Actions/ResetUserData.php:18` ma strażnika `Schema::hasTable('cash_expectations')`;
  - `app/Support/CategoryList/CategoryTypeChange.php:13` ma komentarz „(There is no Cash Expectation yet.)”.
- **Zdania w żywych wpisach mówią nieprawdę o kodzie.**
  - Category (glossary.md:52) każe odmówić usunięcia kategorii z Cash Expectation, a `DeleteCategory.php:22` i `CategoryTypeChange` tego nie sprawdzają.
  - Account (glossary.md:12) blokuje usunięcie konta wskazanego przez Cash Expectation i liczy datę otwarcia od Cash Expectation. `Account::earliestActivityDate()` (`app/Models/Account.php:118-132`) bierze pod uwagę tylko Financial Operation, Own Transfer i Capital Movement.
- **Stare ADR.** ADR 0017–0022 i 0029 opisują Agendę, Cash Expectation i Settlement candidate, a ADR 0030 opisuje Year Close, Reserve i Earmark. Konwencja repo to dopisek na końcu pliku: „Superseded by ADR-NNNN: …” (`adr/0014…:4`) albo „Amended by ADR-NNNN: …” (`adr/0030…:4-6`). Żywe ADR, których reguła wymienia usuwany termin:
  - 0031:3 (rok powłoki „keeps driving Budget and Agenda”);
  - 0063:5, 7, 13 i 34;
  - 0067:11;
  - 0078:23 i 25;
  - 0086:3, 5 i 12;
  - 0091:7 i 13.
  ADR 0024, 0066 i 0090 wymieniają terminy tylko wśród odrzuconych opcji albo zaprzeczeń.
- **AGENTS.md:10.** Twarda reguła „Docs describe more than the code: Agenda, Cash Expectation, Reserve and Year Close are not on `main`” pomija Earmark i Settlement candidate (`health-check.md`, poprawka 10).
- **`development-directions.md`.**
  - :145 („Rok 2027”) odkłada decyzję, czy Year Close powstaje, czy znika ze słownika.
  - :134 („Płynność w czasie”) uzasadnia ADR tym, że „Forecast” i „due date” są _Avoid_. Po zmianie „Forecast” zostaje na liście _Avoid_ tylko przy Current Plan (glossary.md:41), a „due date” znika razem z Cash Expectation.
- **Wzór testu.** Testy w `tests/Unit` czytają pliki źródłowe: `DesignTokensTest.php` czyta `resources/css/app.css`, są też `LandingPageSourceTest.php` i `*HardcodedUiTest.php`. Uruchamia je `composer ci:check` (`@test`).

## Desired End State

- `context/foundation/adr` nie ma dwóch plików z tym samym czterocyfrowym prefiksem. Test Pest to sprawdza i przechodzi w `composer ci:check`.
- ADR-0008 jednoznacznie oznacza snapshoty użytkownika. Decyzja o parze cross-Import ma numer 0103 i notę o zmianie numeru.
- ADR-0104 zapisuje, że sześć terminów opuściło słownik, dlaczego tak się stało i co przestało obowiązywać w ADR 0031, 0063, 0067, 0078, 0086 i 0091.
- Słownik nie zawiera nazw sześciu terminów. Żaden żywy wpis nie stawia reguły, której kod nie ma.
- AGENTS.md mówi agentowi, że słownik opisuje tylko kod, a ADR z dopiskiem „Superseded by ADR-0104” to projekty, których nie zbudowano.

### Key Discoveries:

- Przeniesienie pliku, którego nikt nie cytuje (cross-import), nie wymaga poprawiania żadnego odnośnika. Przeniesienie pliku snapshotów wymagałoby zmian w ADR-0013 i w archiwum (`adr/0013…:5`).
- Reguły, które zostają w Account i Category, mają odpowiadać kodowi: `Account.php:118-132` i `CategoryTypeChange.php:10-13` razem z `DeleteCategory.php:22`.
- „Forecast” zostaje na liście _Avoid_ przy Current Plan (glossary.md:41), więc kierunek „Płynność w czasie” dalej potrzebuje ADR dla tego słowa (`development-directions.md:134`).

## What We're NOT Doing

- Nie zmieniam strażnika `cash_expectations` w `ResetUserData.php:18`. To zachowanie kodu, a stanu tabeli na produkcji nie da się stąd sprawdzić. Lokalna baza była wyłączona przy planowaniu.
- Nie zmieniam treści ADR 0017–0022, 0029 i 0030 poza dopiskiem „Superseded by ADR-0104”. Zostają zapisem projektu.
- Nie zmieniam ADR 0024, 0066 i 0090, które wymieniają terminy tylko wśród odrzuconych opcji.
- Nie zmieniam PRD (`prd-mvp.md`, `mvp.md`, `prd.md`, `prd-v3.md`), `shape-notes.md`, ani datowanych raportów `health-check.md` i `stack-assessment.md`. To historia, a nie źródło reguł.
- Nie zmieniam dokumentów w `context/archive/`.
- Nie wypełniam luk 0025–0028, 0036 i 0046 i nie zmieniam numerów innych ADR.
- Nie poprawiam polskich napisów ze słownika, których nie ma w UI („saldo na dzień”, „pokrycie zebranych”, „wczytanie arkusza”, „stawka” jako etykieta). Przegląd je znalazł, ale nie wchodzą w zakres S-46.
- Nie dodaję testu, który pilnowałby, że słownik nie zawiera usuniętych nazw. Powrót terminu i tak wymaga wpisu w słowniku i ADR (AGENTS.md:9).

## Implementation Approach

Kolejność wynika z AGENTS.md:9: zmiana reguły słownika zaczyna się od ADR. Faza 1 jest niezależna i daje numer 0103. Faza 2 zapisuje decyzję w ADR-0104 i dopisuje noty w starych ADR. Faza 3 zmienia słownik i dokumenty, które się na niego powołują.

W słowniku obowiązuje jedna zasada: usuwam zdania i nazwy, a nie piszę nowych reguł. Gdy usunięcie psuje zdanie, przeformułowuję je najmniejszą zmianą. Jedyne nowe sformułowania to wyliczenia dopasowane do kodu: data otwarcia Account i warunek usunięcia Category.

## Critical Implementation Details

- **Numery ADR a równoległe zmiany.** F-08 (`app-day-decision`) i inne zmiany M-5 też mogą wziąć 0103 albo 0104. Według `context/foundation/agents/parallel-changes.md:27` numer zmienia ta zmiana, która scala się druga. Przed scaleniem tej zmiany sprawdź `ls context/foundation/adr | tail` na `development`; test z fazy 1 złapie kolizję.

## Phase 1: Unikalne numery ADR

### Overview

Test, który nie przechodzi, dopóki istnieją dwa pliki 0008. Potem zmiana numeru pliku cross-import na 0103.

### Changes Required:

#### 1. Test numeracji ADR

**File**: `tests/Unit/AdrNumberingTest.php` (nowy, `php artisan make:test --pest --unit AdrNumberingTest`)

**Intent**: Pilnuje, żeby każdy plik ADR miał numer i żeby żaden numer nie powtarzał się, bo zdublowany numer robi z „ADR-NNNN” odwołanie niejednoznaczne. Wzór: `tests/Unit/DesignTokensTest.php`, czyli plik czytany względem `__DIR__`.

**Contract**:
- Czyta pliki `*.md` z `context/foundation/adr`.
- Pierwszy test: każdy plik pasuje do `^\d{4}-[a-z0-9-]+\.md$`.
- Drugi test: żadne dwa pliki nie mają tego samego czterocyfrowego prefiksu. Komunikat porażki wymienia numer i obie nazwy plików, na przykład „0008: 0008-cross-import-own-transfer-pair.md, 0008-user-domain-snapshots-via-artisan.md”.
- Test musi nie przejść przed krokiem 2.

#### 2. Zmiana numeru ADR o parze cross-Import

**File**: `context/foundation/adr/0008-cross-import-own-transfer-pair.md` → `context/foundation/adr/0103-cross-import-own-transfer-pair.md` (`git mv`)

**Intent**: Numer 0008 zostaje przy snapshotach, które cytują ADR-0013 i archiwum. Plik bez odwołań dostaje następny wolny numer.

**Contract**: Na końcu pliku dopisz akapit: „Renumbered from 0008 on 2026-11-19: ADR-0008 is `0008-user-domain-snapshots-via-artisan.md`.” Treść decyzji się nie zmienia.

### Success Criteria:

#### Automated Verification:

- Przed zmianą numeru test nie przechodzi i wymienia oba pliki 0008: `docker run --rm -u 1000:1000 -e HOME=/tmp -v "$PWD":/var/www/html -w /var/www/html --entrypoint php sail-8.5/app artisan test --compact tests/Unit/AdrNumberingTest.php`
- Po zmianie numeru ten sam test przechodzi
- Pint bez uwag dla pliku testu: `docker run --rm -u 1000:1000 -e HOME=/tmp -v "$PWD":/var/www/html -w /var/www/html --entrypoint php sail-8.5/app vendor/bin/pint --format agent tests/Unit/AdrNumberingTest.php`
- `grep -rnw "0008" context/foundation/adr AGENTS.md .ai app resources/js tests` zwraca tylko `adr/0013…:5` i notę o zmianie numeru w `adr/0103…:5`

#### Manual Verification:

- Treść `0103-cross-import-own-transfer-pair.md` jest taka sama jak przed zmianą, plus jeden akapit o zmianie numeru

**Implementation Note**: Po zielonej weryfikacji automatycznej poczekaj na ręczne potwierdzenie przed fazą 2. Commit: `chore(docs-match-code): unikalne numery ADR, para cross-Import jako 0103 (p1)`.

---

## Phase 2: ADR-0104 i noty w starych ADR

### Overview

Nowy ADR zapisuje usunięcie sześciu terminów. Stare ADR dostają dopiski w konwencji repo.

### Changes Required:

#### 1. ADR-0104

**File**: `context/foundation/adr/0104-glossary-drops-terms-never-built.md` (nowy, po angielsku, jak pozostałe ADR)

**Intent**: Zapisuje decyzję z 2026-11-19, żeby agent i użytkownik wiedzieli, dlaczego terminów brakuje i od czego zaczyna się ich ewentualny powrót.

**Contract**: Tytuł w stylu repo (np. „The glossary drops terms that were designed but never built”) i akapity:
- **Decyzja.** Cash Expectation, Agenda i Settlement candidate (ADR 0017–0022, 0029) oraz Year Close, Reserve i Earmark (ADR 0030) opuszczają słownik razem z regułami w innych wpisach i nazwami z list _Avoid_. Nigdy nie były na `main`.
- **Co przestaje obowiązywać.**
  - Account: blokada usunięcia konta wskazanego przez Cash Expectation i Cash Expectation w dacie otwarcia (zostaje kod `Account::earliestActivityDate()`).
  - Category: Cash Expectation w warunku usunięcia i zmiany typu (zostaje `CategoryTypeChange`).
  - Budget Line: Cash Expectation jako pierwsze użycie.
  - Financial Operation, Import i Import pair candidate: rozliczenie Cash Expectation.
  - Current Plan, Align, Budget, Available Limit i Year Result: Year Close, Reserve i Earmark.
  - Klauzule z Cash Expectation, Agendą, Year Close, Reserve i Earmark w ADR 0031, 0063, 0067, 0078, 0086 i 0091.
- **Powrót terminu.** Funkcja z tego obszaru zaczyna się od nowego wpisu w słowniku i nowego ADR (AGENTS.md:9), a nie od przywrócenia starych reguł. Dotyczy to na przykład płynności w czasie albo podsumowania roku. Stare ADR zostają zapisem projektu.
- **Odrzucone.**
  - Sekcja „Designed, not built” w słowniku: reguły zostałyby tam, gdzie agent je czyta.
  - Oznaczenia w miejscu: reguła dalej stoi w żywym wpisie.
  - Zostawienie nazw w listach _Avoid_ jako zwykłych słów: bez definicji nie mówią, z czym kolidują.
  - Usunięcie tylko Agendy i Cash Expectation, z zostawieniem Year Close do decyzji o podsumowaniu roku: dwa sposoby w jednej zmianie.
- **Year Close.** Decyzja odłożona w `development-directions.md` („Rok 2027”) zapada tutaj: Year Close znika. Podsumowanie roku, jeśli powstanie, będzie odczytem z własnym ADR.

#### 2. „Superseded by ADR-0104”

**File**: `context/foundation/adr/0017…`, `0018…`, `0019…`, `0020…`, `0021…`, `0022…`, `0029…`, `0030…`

**Intent**: Czytelnik starego ADR od razu wie, że decyzja nie obowiązuje, a funkcja nie istnieje.

**Contract**: Na końcu każdego pliku, w osobnym akapicie po istniejących dopiskach: „Superseded by ADR-0104: never built; <termin lub terminy> left the glossary.” Wzór: `adr/0014…:4`.

#### 3. „Amended by ADR-0104”

**File**: `context/foundation/adr/0031…`, `0063…`, `0067…`, `0078…`, `0086…`, `0091…`

**Intent**: Żywe decyzje przestają podawać za obowiązujące klauzule z usuniętymi terminami.

**Contract**: Na końcu każdego pliku jeden akapit „Amended by ADR-0104: …”, który wymienia klauzule, które nie obowiązują:
- 0031: Agenda w zdaniu o roku powłoki (:3).
- 0063: Cash Expectation w modelu (:5), w pierwszym użyciu (:7), w warunku usunięcia i zmiany typu (:13) oraz punkt o ADR-0030 (:34).
- 0067: „which is added with Cash Expectation itself” (:11). Warunek usunięcia jest ostateczny.
- 0078: zdanie o Cash Expectation, Earmark i Reserve (:23) i „a Cash Expectation” w „Usuń” (:25).
- 0086: lista _Avoid_ Year Close (:3, :5, :12). „Zakończ” zostaje na liście _Avoid_ przy Align.
- 0091: zdania o Cash Expectation (:7, :13).

Treść decyzji nad dopiskiem się nie zmienia.

### Success Criteria:

#### Automated Verification:

- `tests/Unit/AdrNumberingTest.php` przechodzi po dodaniu 0104
- `grep -L "Superseded by ADR-0104" context/foundation/adr/00{17,18,19,20,21,22,29,30}-*.md` nic nie zwraca
- `grep -L "Amended by ADR-0104" context/foundation/adr/00{31,63,67,78,86,91}-*.md` nic nie zwraca

#### Manual Verification:

- ADR-0104 czyta się jako samodzielna decyzja: co, dlaczego, co przestało obowiązywać, co odrzucono
- Każdy dopisek „Amended” wymienia klauzule, które rzeczywiście są w tym ADR

**Implementation Note**: Po zielonej weryfikacji automatycznej poczekaj na ręczne potwierdzenie przed fazą 3. Commit: `docs(docs-match-code): ADR-0104, terminy bez kodu opuszczają słownik (p2)`.

---

## Phase 3: Słownik, AGENTS.md i ich ślady

### Overview

Słownik zostaje oczyszczony według ADR-0104. Dokumenty, które powołują się na usunięte terminy, dostają aktualną treść.

### Changes Required:

#### 1. Usunięte wpisy

**File**: `context/foundation/glossary.md`

**Intent**: Znikają wpisy, którym nie odpowiada żaden kod.

**Contract**: Usuń w całości, z nagłówkiem, treścią i _Avoid_, wpisy **Year Close** (:183-185), **Reserve** (:187-189), **Earmark** (:191-193), **Cash Expectation** (:203-205), **Agenda** (:248-250) i **Settlement candidate** (:276-278).

#### 2. Zdania i nazwy w żywych wpisach

**File**: `context/foundation/glossary.md`

**Intent**: Żaden żywy wpis nie stawia reguły bez kodu ani nie odsyła do usuniętego terminu.

**Contract**: Numery wierszy podaję według stanu przed edycją.
- **Account** (:12): usuń zdanie „An Account named by a Cash Expectation cannot be deleted; archive is allowed (…)”. Datę otwarcia ogranicz do „the earliest Financial Operation, Own Transfer, or Capital Movement on that Account”, czyli tego, co liczy `Account.php:118-132`.
- **Budget** (:32): usuń „Year Close does not end execution: later Financial Operations dated in that year remain allowed.”
- **Current Plan** (:40): „it is not Year Close and not a month-scoped close of Month Remaining” → „it is not a month-scoped close of Month Remaining”. Usuń zdanie „Year Close Aligns…” i zdanie „The User may later opt in to add an Earmark…”.
- **Align** (:44): „Not Year Close, not a month close.” → „Not a month close.”
- **Budget Line** (:48): z listy pierwszego użycia usuń „a Cash Expectation”.
- **Category** (:52): z warunku usunięcia usuń „no Cash Expectation (on Agenda)” i popraw gramatykę wyliczenia.
- **Execution** (:81-82): usuń „Not unpaid Cash Expectations.” Z _Avoid_ usuń „Cash Expectation, Agenda (as this plane)”.
- **Available Limit** (:85-86): usuń „Unpaid Cash Expectations do not reduce it.”, „A positive leftover … candidate Earmark …” i „A negative leftover is not an Earmark …”. Z _Avoid_ usuń „using this figure as Reserve or as an Earmark”.
- **Set-aside** (:122), **Default Set-aside Percent** (:126), **Collected** (:134), **Opening Collected** (:138): z _Avoid_ usuń „Reserve” i „Earmark”.
- **Collected Coverage** (:141-142): „not a transfer, a block or a Reserve” → „not a transfer or a block”. Z _Avoid_ usuń „Reserve, Earmark”.
- **Month Summary** (:154): z _Avoid_ usuń „Year Close”.
- **Month Result** (:161): usuń „not unpaid Cash Expectations,”.
- **Year Result** (:165-166): usuń „Year Close may post it into Reserve.” i zdanie „At Year Close, Earmarks …”. Z _Avoid_ usuń „Reserve (as if this reading were the stock)”.
- **Financial Operation** (:196-197): usuń zdanie „May settle a Cash Expectation …”. Z _Avoid_ usuń „Cash Expectation”.
- **Expense refund** (:200): zostaw tylko połowę o Financial Operation („A negative amount on an expense Category, as a Financial Operation, …”) i usuń „; as a Cash Expectation it does not move …”.
- **Counterparty** (:208): „of a Financial Operation or a Cash Expectation” → „of a Financial Operation”.
- **Balance on a Date** (:228): usuń „A Cash Expectation is not in it.”
- **Liquidity** (:236-237): „distinct from Budget Execution and from Agenda” → „distinct from Budget Execution”. Z _Avoid_ usuń „Agenda (as this plane)”.
- **First Run** (:257): „A Cash Expectation and a Term Deposit are not start conditions.” → „A Term Deposit is not a start condition.”
- **Import** (:261): „Import does not create a Term Deposit or a Cash Expectation.” → „Import does not create a Term Deposit.” Usuń zdanie „A preview row may settle an existing Cash Expectation …”.
- **Import pair candidate** (:273): usuń „and from a Settlement candidate” oraz zdanie „A selected pair and a settlement opt-in cannot both apply to the same preview row.”

#### 3. Twarda reguła w AGENTS.md

**File**: `AGENTS.md` (:10)

**Intent**: Reguła ma opisywać stan po zmianie i wskazywać, gdzie leżą niezbudowane projekty.

**Contract**: Zastąp wiersz treścią w tym duchu: „The glossary describes only what the code has. ADRs marked „Superseded by ADR-0104” (0017–0022, 0029, 0030: Agenda, Cash Expectation, Year Close, Reserve, Earmark) are designs that were never built; building in that area starts with a new glossary entry and ADR. Confirm a term exists in code before building on it.” `CLAUDE.md` tego nie powtarza, więc go nie ruszasz.

#### 4. Komentarz w CategoryTypeChange

**File**: `app/Support/CategoryList/CategoryTypeChange.php` (:13)

**Intent**: Komentarz przestaje zapowiadać termin, który opuścił domenę.

**Contract**: Usuń nawias „(There is no Cash Expectation yet.)” z docblocka klasy. Kod się nie zmienia.

#### 5. Kierunki rozwoju

**File**: `context/foundation/development-directions.md`

**Intent**: Odłożona decyzja o Year Close jest podjęta, a uzasadnienie ADR dla „Płynności w czasie” odpowiada nowemu słownikowi.

**Contract**:
- :145 („Rok 2027”): dopisz, że decyzja zapadła 2026-11-19 (ADR-0104): Year Close znika ze słownika, a podsumowanie roku, jeśli powstanie, jest odczytem z nowym ADR.
- :134: „bo „Forecast” i „due date” są _Avoid_” → „bo „Forecast” jest _Avoid_ przy Current Plan, a nowy termin wymaga wpisu”.
- Sekcja „Stan wyjściowy (fakty, 2026-11-17)” (:27, :38) zostaje bez zmian, bo jest datowana.

### Success Criteria:

#### Automated Verification:

- W słowniku nie ma nazw usuniętych terminów: `grep -nE "Cash Expectation|Agenda|Year Close|Reserve|Earmark|Settlement candidate|settlement opt-in|Zamknięcie roku|Rezerwa" context/foundation/glossary.md` zwraca 0 wierszy
- `grep -c '^\*\*.*\*\*:$' context/foundation/glossary.md` daje liczbę wpisów sprzed zmiany minus 6
- `grep -n "not on \`main\`" AGENTS.md` nic nie zwraca, a `grep -n "ADR-0104" AGENTS.md` zwraca jeden wiersz
- `grep -n "Cash Expectation" app/Support/CategoryList/CategoryTypeChange.php` nic nie zwraca
- Bramka: `docker run --rm -u 1000:1000 -e HOME=/tmp -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing_docs_match_code -e DB_USERNAME=sail -e DB_PASSWORD=password --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html --entrypoint composer sail-8.5/app ci:check` przechodzi (najpierw `npm ci` i `npm run build`)
- Sloppy w obrazie Sail: `docker run --rm -u 1000:1000 -e HOME=/tmp -v "$PWD":/var/www/html -w /var/www/html --entrypoint php sail-8.5/app vendor/bin/sloppy ci`

#### Manual Verification:

- Każdy z 24 edytowanych wpisów czyta się poprawnie gramatycznie i nie ma urwanego wyliczenia
- Account i Category opisują warunki, które sprawdza kod (`Account.php:118-132`, `CategoryTypeChange.php`, `DeleteCategory.php:22`)
- Nowa reguła w AGENTS.md jest zrozumiała bez czytania ADR-0104

**Implementation Note**: Commit: `docs(docs-match-code): słownik bez terminów, których nie ma w kodzie (p3)`.

---

## Testing Strategy

### Unit Tests:

- `AdrNumberingTest`: każdy plik w `adr/` ma prefiks `NNNN-`, a prefiksy są unikalne. Najpierw czerwony na obecnych dwóch 0008, potem zielony.

### Integration Tests:

- Brak. Zachowanie aplikacji się nie zmienia, a `ci:check` potwierdza, że zmiana komentarza w `app/` niczego nie psuje.

### Manual Testing Steps:

1. Przeczytaj ADR-0104 i jeden dopisek „Amended” (np. 0063) i sprawdź, że wymieniona klauzula jest w tym pliku.
2. Przeczytaj wpisy Account, Category, Current Plan i Import w słowniku po zmianie.
3. Przeczytaj nowy wiersz AGENTS.md:10.

## Migration Notes

Brak migracji. Po scaleniu do `development` w głównym checkoucie zmień S-46 w `roadmap.md` z `ready` na `planning` (dalej `/10x-implement`), a w tym samym przejściu przepisz `roadmap.md:184` („Year Close i Reserve — Why parked: niezaimplementowane na `main` (AGENTS.md)”) na „usunięte ze słownika (ADR-0104)” (impl-review F2). M-5 jest tam niezacommitowany, więc worktree tej zmiany go nie ma.

## References

- Roadmap: `context/foundation/roadmap.md` S-46 i MS-09 (główny checkout, niezacommitowane 2026-11-19)
- Kierunki: `context/foundation/development-directions.md:27, :73, :145`
- Health-check: `context/foundation/health-check.md` poprawki 9 i 10
- Konwencja dopisków ADR: `context/foundation/adr/0014-copy-categories-as-new-records-on-budget-create.md:4`, `0030-year-close-posts-year-result-into-reserve.md:4-6`
- Numery przy równoległych zmianach: `context/foundation/agents/parallel-changes.md:27`
- Wzór testu czytającego plik: `tests/Unit/DesignTokensTest.php`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Unikalne numery ADR

#### Automated

- [x] 1.1 Przed zmianą numeru test nie przechodzi i wymienia oba pliki 0008 — df449e2
- [x] 1.2 Po zmianie numeru ten sam test przechodzi — df449e2
- [x] 1.3 Pint bez uwag dla pliku testu — df449e2
- [x] 1.4 grep 0008 zwraca tylko adr/0013 i nazwę pliku snapshotów — df449e2

#### Manual

- [ ] 1.5 Treść 0103-cross-import-own-transfer-pair.md bez zmian poza akapitem o numerze

### Phase 2: ADR-0104 i noty w starych ADR

#### Automated

- [x] 2.1 AdrNumberingTest przechodzi po dodaniu 0104 — 694a5e0
- [x] 2.2 Każdy z ADR 0017–0022, 0029, 0030 ma „Superseded by ADR-0104” — 694a5e0
- [x] 2.3 Każdy z ADR 0031, 0063, 0067, 0078, 0086, 0091 ma „Amended by ADR-0104” — 694a5e0

#### Manual

- [ ] 2.4 ADR-0104 czyta się jako samodzielna decyzja
- [ ] 2.5 Każdy dopisek „Amended” wymienia klauzule obecne w tym ADR

### Phase 3: Słownik, AGENTS.md i ich ślady

#### Automated

- [x] 3.1 W słowniku nie ma nazw usuniętych terminów — 0dde48e
- [x] 3.2 Liczba wpisów słownika spadła o 6 — 0dde48e
- [x] 3.3 AGENTS.md bez „not on `main`”, z jednym wierszem ADR-0104 — 0dde48e
- [x] 3.4 CategoryTypeChange.php bez „Cash Expectation” — 0dde48e
- [x] 3.5 Bramka composer ci:check przechodzi — 0dde48e
- [x] 3.6 Sloppy w obrazie Sail przechodzi — 0dde48e

#### Manual

- [ ] 3.7 24 edytowane wpisy czytają się poprawnie
- [ ] 3.8 Account i Category opisują warunki, które sprawdza kod
- [ ] 3.9 Nowa reguła AGENTS.md jest zrozumiała bez ADR-0104
