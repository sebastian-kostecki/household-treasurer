# ui-fixes-2 — plan implementacji

## Overview

Zbiór poprawek wyglądu z sesji `/10x-ui` (2026-11-07). Zmiana obejmuje:

- jedną zasadę kwot w każdej tabeli: liczba bez „zł”, jednostka w stopce „Kwoty w zł”;
- polską nazwę „Domowy Skarbnik” i nowy znak „dom z monetą”, także jako favicon;
- strzałkę kierunku zmiany planu w Budżecie i w Planie miesiąca (nowa kolumna „Zatwierdzony”);
- większy kafelek kategorii w Planie miesiąca;
- ikonę gotówki zamiast „zł”;
- kolumny, które rosną na szerokim ekranie (Dashboard i Budżet).

Najpierw decyzje trafiają do ADR-ów i design systemu, potem do kodu. Każda faza uzupełnia sinki swoich widoków.

## Current State Analysis

Pełne ustalenia: `context/changes/ui-fixes-2/research.md`. Najważniejsze:

- **Kwoty mają dziś trzy polityki.**
  - Stopka „Kwoty w zł”: Budżet, Plan miesiąca, Dashboard.
  - „zł” w komórce na mocy wyjątku z ADR-u: Operacje (ADR-0039), Konta (0044), Lokaty (0051), podgląd importu (0052).
  - „zł” w komórce bez żadnego ADR-u: `MonthPlanItemRow.vue:127,176`, `MonthYearSummary.vue:42,44,48`, `MonthSummaryCard.vue:159`.
  - `DashboardHoldings.vue` ma komórki bez jednostki i stopkę bez „Kwoty w zł”.
  - Formatery z „zł” w wierszach: `formatFinancialOperationAmount` i `formatOperationDaySum` (`lib/format.ts:182,212`). Wołają je tylko `FinancialOperationRow`, `OperationsDayRow` i `lib/import-row-amount.ts`.
- **Nazwa:** `APP_NAME` (`.env.example:1`) służy jednocześnie jako nazwa widoczna i jako identyfikator infrastruktury. Wyprowadzają się z niej:
  - ciasteczko sesji `household-treasurer-session` (`config/session.php:132`);
  - prefiks cache `household-treasurer-cache-` (`config/cache.php:121`);
  - prefiks Redis `household-treasurer-database-` (`config/database.php:152`);
  - nazwa kopii i jej monitora `Household Treasurer`, bez sluga (`config/backup.php:25,132`).

  Tytuły stron biorą `VITE_APP_NAME`, który wchodzi do bundla przy buildzie (`docker-compose.prod.yml:8`, `docker/8.5/Dockerfile.prod:47`). Nazwa jest też wpisana na sztywno w `lang/pl/ui.php:45,786`.
- **Znak:** `BrandMark.vue` to tekst „HT” w stylu `@utility brand-mark` (`app.css:300-304`), a ADR-0053 zapisuje „kafelek tekstowy, nie obraz”. `public/favicon.svg` to wciąż domyślny znak Laravela. `apple-touch-icon.png` ma 166×166 zamiast 180×180.
- **Zmiana planu:** `PlanChangedHint.vue` pokazuje ikonę `History` w `brand-text` i nie mówi, w którą stronę zmienił się plan. Jedyne użycie: `BudgetCategoryRow.vue:116`. W Planie miesiąca kwota zatwierdzona to podpis „Zatwierdzono: … zł” pod kwotą (`MonthPlanItemRow.vue:168-177`). Siatka `.month-plan-grid` ma od `xl` sześć kolumn (`app.css:875-879`).
- **Kolor:** w tabeli kolorów (`design-system.md:46-63`) nie ma znaczenia „kierunek zmiany”. `success` znaczy dochód, a `destructive` przekroczenie.
- **CategoryChip:** kafelek `size-8` jest wysoki tylko na nazwę. Podpisy (linia roczna, dostępny limit) leżą pod całym wierszem chipa (`MonthPlanItemRow.vue:79-129`).
- **Gotówka:** `accountShortMark` zwraca dla `cash` tekst „zł” (`lib/account-identity.ts:57-67`). `AccountAvatar.vue` ma już gałąź ikony (`Building2` dla „Inny”).
- **Szerokie ekrany:**
  - Dashboard: `--aside-width: 300px` (`app.css:167`). Tabela mierników ma kolumny kwot sztywne 5rem obok kategorii `1.4fr` i miernika `1fr` (`app.css:1574-1580`).
  - Budżet: `repeat(4, 6.5rem) minmax(6rem, 8rem)` (`app.css:1073-1082`), więc rośnie tylko kolumna kategorii.
  - Nigdzie nie ma reguły `2xl` ani `max-width` treści w układzie z sidebarem.

## Desired End State

- Każda tabela (`ListSurface` z wierszami, w tym wiersze dnia, sum i podpisy w wierszach) pokazuje kwoty bez „zł” i ma w stopce „Kwoty w zł”. „zł” zostaje w miarach (`StatPanel`), dialogach i polach `FormField`, kartach alertów, palecie poleceń i tekstach ciągłych. Wyjątki z ADR-0039/0044/0051/0052 przestają obowiązywać.
- Interfejs, tytuły kart przeglądarki, landing i stopka ekranu logowania mówią „Domowy Skarbnik”. Zmiana `APP_NAME` nie wylogowuje, nie zmienia prefiksu cache ani katalogu kopii.
- Znak to kafelek `brand` z rysunkiem domu z monetą w `brand-foreground`, zawsze z nazwą obok. Ten sam rysunek ma favicon (SVG i ICO) i `apple-touch-icon` 180×180.
- W Budżecie, w kolumnie „Plan początkowy”, zmieniony plan ma strzałkę ↑ lub ↓. Przy kategorii wydatków ↓ jest w `success`, a ↑ w `destructive`; przy dochodach odwrotnie. Wiersz zarchiwizowany ma strzałkę w `muted-foreground`.
- Plan miesiąca ma kolumnę „Zatwierdzony” przed „Plan”. Pokazuje wyciszoną kwotę zatwierdzoną i tę samą strzałkę, gdy plan różni się od zatwierdzonego, a „—” przed zatwierdzeniem. Podpis „Zatwierdzono:” znika.
- W Planie miesiąca kafelek kategorii ma wysokość nazwy razem z podpisem: ikona po lewej, nazwa obok, pod nazwą podpis roczny lub dostępny limit.
- Konto gotówkowe ma w `AccountAvatar` ikonę `Coins` we wszystkich miejscach.
- Kolumna Konta i Lokaty na Dashboardzie rośnie z oknem (`clamp`). Miernik w tabeli wykonania zajmuje wolną szerokość. Kolumny kwot i miernik w Budżecie rosną na dużym ekranie.
- Każdy z dziewięciu sinków pokazuje nowe stany. `composer ci:check`, `vendor/bin/sloppy ci` i `npm run check:ui` przechodzą.

### Key Discoveries:

- Formatery z „zł” w wierszach nie mają innych wywołań poza wierszami tabel (grep w `resources/js`), więc mogą zmienić wynik bez wariantu z jednostką (`lib/format.ts:182,212`).
- Klucz stopki jest dziś w grupie budżetowej `budget.amounts_in_zl` (`lang/pl/ui.php:248`), a grupa `common` istnieje (`lang/pl/ui.php:777`).
- Zastąpienie ADR-u zapisuje się prozą na końcu starszego ADR-u („Superseded in part by ADR-NNNN: …”), jak w ADR-0044 → 0045/0047. Następny wolny numer to 0058.
- `ui-contract.test.ts:319-330` wymaga napisu `BrandMark` w czterech plikach i zabrania `Vault`. Komponent zachowuje więc nazwę.
- `PlanChangedHint.vue`, `CategoryChip.vue` i `BrandMark.vue` nie są na liście `scripts/check-ui-literals.mjs`.
- `.month-plan-grid` jest poniżej `xl` układem piętrowym z trzema kolumnami (`app.css:859-872`). Nowa kolumna potrzebuje obszaru także tam.
- Test, który dziś czeka „zł” w kwotach wierszy: `resources/js/lib/import-row-amount.test.ts:10,20,30,40`, `tests/e2e/seed.spec.ts:39,42`, `resources/js/lib/format.test.ts` (asercje formaterów operacji), `resources/js/lib/account-identity.test.ts:20-22,55-61`.

## What We're NOT Doing

- Błąd roku w sidebarze (trwały layout trzyma starą migawkę `shell`). Osobna zmiana z testem regresji.
- „Plan = wydatki” w Planie miesiąca (przycisk i automatyczne podniesienie planu). Osobna zmiana: najpierw glosariusz i ADR.
- Nowe propsy Inertii. Kolumna „Zatwierdzony” korzysta z istniejącego `approved_minor_units`; w wierszach sum i w wierszu odkładania zostaje pusta.
- `max-width` treści w układzie z sidebarem i nowy próg `2xl`. Szerokość daje `clamp()` i zakresy kolumn.
- Nowe tokeny koloru dla strzałki. Używa `success` i `destructive` z dopisanym znaczeniem.
- `site.webmanifest`, `theme-color`, wersja angielska nazwy.
- Zmiana identyfikatorów technicznych: baza `household_treasurer`, katalog `/storage/household-treasurer-backups`, tag obrazu, sieć Sail, nazwa pakietu, nazwy klas i plików w kodzie.
- Nazwa w `CHANGELOG.md` (historia) i w nazwie umiejętności `openship-ui-development`.
- Większy `CategoryChip` w Budżecie i na Dashboardzie. Rozmiar `lg` dostaje tylko Plan miesiąca.
- Stopka panelu wykonania na Dashboardzie (`dashboard.execution_footer*`). Już zawiera jednostkę i zostaje.

## Implementation Approach

Kolejność zgodna z AGENTS.md: najpierw ADR-y, strona design systemu i reguły agentów (Faza 1), potem kod. Każda faza kodu jest osobnym commitem, który:

- zaczyna od testu, który pada, gdy zmiana dotyczy logiki (konfiguracja, formatery, kierunek strzałki, znak gotówki);
- przechodzi przez sink widoku;
- kończy się bramką `composer ci:check`.

Fazy 3–5 są od siebie niezależne. Faza 4 usuwa podpis „Zatwierdzono:”, dlatego Faza 3 go nie rusza.

## Critical Implementation Details

**Przypięcie przed zmianą nazwy.** W Fazie 2 domyślne wartości `SESSION_COOKIE`, `CACHE_PREFIX`, `REDIS_PREFIX` i nazwy kopii muszą zostać przypięte w tym samym commicie, w którym zmienia się `APP_NAME` w przykładach. Przed wdrożeniem trzeba sprawdzić `APP_NAME` w produkcyjnym `.env`: przypięte wartości mają odpowiadać temu, co produkcja wylicza dziś. Jeśli produkcja ma inną nazwę niż „Household Treasurer”, ustaw tam jawnie `SESSION_COOKIE`, `CACHE_PREFIX` i `BACKUP_NAME`. Obraz produkcyjny trzeba przebudować, bo `VITE_APP_NAME` jest wypalany przy buildzie. Issuer 2FA zmieni się tylko dla nowych kodów QR; istniejące wpisy w aplikacjach uwierzytelniających działają dalej.

**Kierunek strzałki na żywo.** W trybie zmian Planu miesiąca strzałka porównuje kwotę zatwierdzoną z kwotą, którą wiersz właśnie pokazuje (wartość z pola). Odchylenie liczy się już tak samo (`rowState` na stronie). Strzałka nie może czytać samego `plan_minor_units` z serwera, bo nie reagowałaby na edycję.

---

## Faza 1: Decyzje na papierze

### Overview

Cztery nowe ADR-y, odwołania „Superseded in part” w zastępowanych ADR-ach, aktualizacja `design-system.md` i reguł w `.ai/rules`. Bez kodu aplikacji. ADR-y piszemy po angielsku, jak dotychczasowe.

### Changes Required:

#### 1. ADR-0058: jedna zasada kwot i ikona gotówki

**File**: `context/foundation/adr/0058-one-amount-rule-and-cash-icon.md`

**Intent**: Zastępuje wyjątki od ADR-0037, dzięki czemu ta sama kwota wygląda wszędzie tak samo.

**Contract**: Zakres zasady:

- „Tabela” to każda lista w `ListSurface` z wierszami na siatce: komórki, wiersze dnia, wiersze sum i podpisy w wierszu (dostępny limit, linia roczna, podsumowanie roczne `MonthSummaryCard`). Kwota w tabeli używa `formatMoneyAmount` lub wariantu ze znakiem bez jednostki, a stopka panelu mówi „Kwoty w zł”.
- Operacje mają stopkę w każdej z trzech sekcji. W transferach i ruchach kapitału jednostka i objaśnienie stoją w jednej linii `meta`, rozdzielone „ · ”.
- Konta, Lokaty, podgląd importu i `DashboardHoldings` dostają stopkę.
- „zł” zostaje w miarach, dialogach i `FormField`, w kartach alertów, palecie poleceń i tekstach ciągłych.
- Gotówka w `AccountAvatar` to ikona `Coins` (także przy `mark_url`), bo „zł” to jednostka, a nie znak konta.

ADR zastępuje częściowo: ADR-0039 („zł” w kwotach, brak stopki), ADR-0044, ADR-0051, ADR-0052 („zł” w wierszach), ADR-0047 i ADR-0054 („zł” dla gotówki). Każdy z nich dostaje na końcu zdanie „Superseded in part by ADR-0058: …”. Odrzucone warianty: „zł” w każdej komórce (zaśmieca kolumny i różni się od Budżetu) oraz stopka tylko w tabelach z wieloma kolumnami kwot (zostawia dwie polityki).

#### 2. ADR-0059: „Domowy Skarbnik” i znak „dom z monetą”

**File**: `context/foundation/adr/0059-domowy-skarbnik-name-and-house-coin-mark.md`

**Intent**: Polska nazwa produktu i graficzny znak zamiast tekstu „HT”. Zapisuje też, dlaczego identyfikatory infrastruktury nie wynikają już z `APP_NAME`.

**Contract**:

- Znak: kafelek `brand` 26 px (`--brand-mark-size`), `radius-sm`, rysunek inline SVG w `currentColor` = `brand-foreground`. Obrys domu (dach i ściany) z kołem monety w środku, czytelny przy 16 px.
- „Zawsze z nazwą” z ADR-0057 zostaje.
- Pliki w `public/` (favicon SVG/ICO, apple-touch 180×180) mają ten sam rysunek z wartościami hex tokenów `brand` i `brand-foreground`. To statyczne zasoby poza widokami, więc nie czytają tokenów.
- Nazwa widoczna płynie z `APP_NAME` i `VITE_APP_NAME`. Ciasteczko sesji, prefiks cache, prefiks Redis i nazwa kopii mają stałe wartości domyślne w config, równe dzisiejszym.

Zastępuje częściowo: ADR-0053 („HT” w JetBrains Mono, kafelek tekstowy, nie obraz) i ADR-0057 (tylko tam, gdzie wymienia „HT”). Zakaz „ikon Lucide w roli znaku” zostaje, bo rysunek jest własny. Odrzucone: monogram „DS” (słabo czytelny przy 16 px, to nie ikona) i skarbonka (mniej mówi o domu, za dużo szczegółów przy 16 px).

#### 3. ADR-0060: strzałka kierunku zmiany planu i kolumna „Zatwierdzony”

**File**: `context/foundation/adr/0060-plan-change-direction-arrow.md`

**Intent**: Nowe znaczenie koloru: kierunek zmiany planu jest korzystny albo niekorzystny. Do tego układ Planu miesiąca z kwotą zatwierdzoną jako kolumną.

**Contract**:

- Strzałka `ArrowUp` / `ArrowDown` (`@lucide/vue`) stoi przy kwocie odniesienia: plan początkowy w Budżecie, plan zatwierdzony w Planie miesiąca.
- Kierunek: bieżący plan względem odniesienia.
- Ton zależy od typu kategorii. Przy wydatkach ↓ jest korzystna (`success`), a ↑ niekorzystna (`destructive`); przy dochodach odwrotnie.
- Wiersz zarchiwizowany ma `muted-foreground`.
- Strzałka ma podpowiedź i `aria-label` z kierunkiem („Plan zwiększony względem planu początkowego” / „…zatwierdzonego”), bo kolor nie może być jedynym nośnikiem.
- Plan miesiąca dostaje kolumnę „Zatwierdzony” przed „Plan”: wyciszona kwota, strzałka, „—” przed zatwierdzeniem, pusta w wierszach sum i odkładania. Podpis „Zatwierdzono:” znika.
- Poniżej `xl` strzałka stoi w linii podpisu mobilnego, a nie obok kwoty. Wariant awaryjny przy 390 px to siatka kwot 2×2 (Faza 4, §4).
- Kafelek kategorii w Planie miesiąca ma rozmiar `lg`: wysokość nazwy razem z podpisem, podpis wewnątrz chipa.

Zastępuje częściowo ADR-0042 (sześć kolumn i podpis kwoty zatwierdzonej). Odrzucone: nowe tokeny `trend-*`, bo dziś duplikowałyby wartości `success` i `destructive`, oraz kwota zatwierdzona w komórce planu, bo w trybie zmian tłoczy się z polem.

#### 4. ADR-0061: szerokie ekrany

**File**: `context/foundation/adr/0061-wide-screen-columns.md`

**Intent**: Kolumny rosną na szerokim ekranie zamiast zostawiać pustą przestrzeń.

**Contract**:

- `--aside-width: clamp(18.75rem, 22vw, 26rem)` (300 px przy 1280, maks. 416 px).
- Kolumny rosną przez wagi `fr`, a nie przez stałe maksima. Siatka najpierw rozciąga `minmax(a, b)` do `b`, a dopiero resztę oddaje `1fr`, więc stałe maksima zabrałyby szerokość kategorii przy 1280 px.
- Tabela wykonania od 38rem: kategoria `minmax(8rem, 1.4fr)`, trzy kolumny kwot `minmax(5rem, 0.5fr)`, miernik `minmax(9rem, 1.2fr)`. Na szerokim ekranie miernik rośnie najszybciej po kategorii.
- Budżet od `xl`: kategoria `minmax(0, 3fr)`, kolumny kwot `minmax(6.5rem, 1fr)`, miernik `minmax(6rem, 1.5fr)`. Szkic bez zmian.
- Wymóg: przy 1280 px kategoria nie jest węższa niż dziś (Budżet ok. 236 px, tabela wykonania ok. 176 px). Wagi to wartości startowe, do potwierdzenia na zrzutach.
- Bez `max-width` treści i bez progu `2xl`.

Zastępuje częściowo ADR-0043 („stała szerokość” `--aside-width`). Próg 38rem z ADR-0050 zostaje.

#### 5. Strona design systemu

**File**: `context/foundation/design-system.md`

**Intent**: Strona mówi to samo co nowe ADR-y.

**Contract**: Sekcje do zmiany:

- tytuł („# Domowy Skarbnik — Design system”);
- indeks ADR w linii 3 (dopisz 0058–0061 i ich zastąpienia);
- „Powierzchnie i rytm”: linia 18 (Konta „zł” i gotówka, Operacje „bez Kwoty w zł”, podgląd importu) i linia 21 (`--aside-width` jako `clamp`);
- Typografia: linia 28 („znaku HT”) i wiersz `brand-mark` w tabeli stylów (kafelek z rysunkiem, nie krój);
- tabela kolorów: wiersz `brand` (znak) oraz wiersze `success` i `destructive` z nowym znaczeniem „kierunek zmiany planu, korzystny / niekorzystny wg typu kategorii (ADR-0060)” i usuniętą sprzecznością w kolumnie „Nie używaj do”;
- „Kwoty”: linie 71 i 75 (jedna zasada, bez wyjątków);
- „Gotowe elementy”: `AccountAvatar` (`Coins` dla gotówki), `CategoryChip` (`lg` z podpisem) i nowy komponent strzałki;
- kitchen sink: nowe stany Budżetu i Planu miesiąca;
- Sidebar: linie 127 i 144 (znak z rysunkiem).

#### 6. Reguły agentów

**File**: `.ai/rules/js.md`, `.ai/rules/js-operations.md`, `.ai/rules/js-import.md`, `.ai/rules/js-month-plan.md`, `.ai/rules/js-sidebar.md` (jeśli wymienia „HT”), `.ai/rules/css.md` (`--aside-width`)

**Intent**: Reguły ładowane przy edycji plików nie mogą kazać wracać do „zł” w komórkach ani do „HT”.

**Contract**:

- `js.md`: sekcja „Amounts go through formatMoney” bez listy wyjątków; akapit `AccountAvatar` z `Coins` dla gotówki.
- `js-operations.md:15` i `js-import.md:30`: kwoty bez jednostki i stopka.
- `js-month-plan.md`: siatka z kolumną „Zatwierdzony” i `CategoryChip` `lg`.
- Grep `.ai/rules` po „HT”, „zł”, „ADR-0039”, „ADR-0044”, „ADR-0051”, „ADR-0052”, „aside-width” i poprawa trafień.
- Potem `php artisan boost:index-rules`.

### Success Criteria:

#### Automated Verification:

- Cztery ADR-y istnieją: `ls context/foundation/adr/0058-* context/foundation/adr/0059-* context/foundation/adr/0060-* context/foundation/adr/0061-*`
- Każdy z ADR-ów 0039, 0044, 0047, 0051, 0052, 0053, 0054, 0057, 0042 i 0043 kończy się odwołaniem do nowego ADR-u: `grep -L "ADR-00\(58\|59\|60\|61\)" <te pliki>` nic nie zwraca
- Na stronie design systemu i w regułach nie ma starych wyjątków: `grep -n "Wyjątek: lista Operacji\|gotówka „zł”\|„zł” dla gotówki\|except the Operations list" context/foundation/design-system.md .ai/rules/*.md` nic nie zwraca
- Indeks reguł przebudowany: `php artisan boost:index-rules` kończy się sukcesem

#### Manual Verification:

- Użytkownik czyta cztery ADR-y i zmienione sekcje `design-system.md` i potwierdza, że oddają decyzje z planu

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie ręcznej weryfikacji.

---

## Faza 2: Nazwa i znak

### Overview

Przypięcie identyfikatorów infrastruktury, nazwa „Domowy Skarbnik” w przykładach środowisk i tłumaczeniach, znak SVG w `BrandMark` i nowe ikony w `public/`.

### Changes Required:

#### 1. Przypięcie identyfikatorów (najpierw test)

**File**: `tests/Feature/AppNameIndependentConfigTest.php` (nowy), `config/session.php`, `config/cache.php`, `config/database.php`, `config/backup.php`

**Intent**: Zmiana `APP_NAME` nie może wylogować użytkownika, zmienić prefiksu cache ani Redis ani przenieść kopii do nowego katalogu. Test najpierw pada na obecnym configu.

**Contract**:

- Test ładuje każdy z czterech plików config z `APP_NAME` ustawionym na „Domowy Skarbnik” (nadpisanie `$_ENV`/`$_SERVER` i `require` pliku, przywrócenie w `afterEach`). Sprawdza:
  - `session.cookie` = `household-treasurer-session`;
  - `cache.prefix` = `household-treasurer-cache-`;
  - `database.redis.options.prefix` = `household-treasurer-database-`;
  - `backup.backup.name` i `backup.monitor_backups.0.name` = `Household Treasurer`.
- Config: domyślne wartości `env('SESSION_COOKIE', …)`, `env('CACHE_PREFIX', …)`, `env('REDIS_PREFIX', …)` i nowe `env('BACKUP_NAME', 'Household Treasurer')` w obu miejscach `backup.php` to literały, nie wyliczenie z `APP_NAME`.

#### 2. Nazwa w środowiskach i tłumaczeniach

**File**: `.env.example`, `.env.prod.example`, `docker-compose.prod.yml`, `docker/8.5/Dockerfile.prod`, `lang/pl/ui.php`, `README.md`, `AGENTS.md`

**Intent**: Wszędzie, gdzie użytkownik widzi nazwę, ma być „Domowy Skarbnik”.

**Contract**:

- `APP_NAME="Domowy Skarbnik"` w obu przykładach.
- Domyślne `VITE_APP_NAME` w `docker-compose.prod.yml:8` i `docker/8.5/Dockerfile.prod:47`.
- Pozycje `SESSION_COOKIE`, `CACHE_PREFIX` i `BACKUP_NAME` (zakomentowane, z dzisiejszymi wartościami) w obu przykładach `.env` z jednozdaniowym komentarzem.
- `lang/pl/ui.php:45` (`auth.aside.legal`) i `:786` (`landing.meta_description`).
- Nagłówek `README.md` i pierwszy wiersz `AGENTS.md`: „Domowy Skarbnik” (w AGENTS.md z angielską nazwą w nawiasie, bo dokumenty agentów są po angielsku).

#### 3. Znak

**File**: `resources/js/components/BrandMark.vue`, `resources/css/app.css` (`@utility brand-mark`), `scripts/check-ui-literals.mjs`

**Intent**: Kafelek z rysunkiem „dom z monetą” zamiast tekstu „HT”.

**Contract**:

- `BrandMark` bez propsów: `<span aria-hidden="true">` z klasami `brand-mark bg-brand text-brand-foreground` i inline `<svg>` (viewBox 24, `stroke="currentColor"`, bez hexów).
- `@utility brand-mark` traci krój mono i rozmiar 11 px, zachowuje rozmiar kafelka i wyśrodkowanie.
- `BrandMark.vue` trafia na listę `check:ui`.

#### 4. Ikony w `public/`

**File**: `public/favicon.svg`, `public/favicon.ico`, `public/apple-touch-icon.png`, `resources/views/app.blade.php`

**Intent**: Karta przeglądarki i ekran startowy telefonu pokazują nowy znak zamiast znaku Laravela.

**Contract**:

- `favicon.svg`: ten sam rysunek na kafelku `#5fd4c4` z rysunkiem `#062a26` (wartości `brand` i `brand-foreground`).
- `favicon.ico` 32×32 i `apple-touch-icon.png` 180×180 wyrenderowane z tego SVG dostępnym narzędziem (np. Playwright z repo albo ImageMagick na hoście), bez nowej zależności npm.
- Linki w `app.blade.php:36-38` zgodne z plikami (`sizes="180x180"` dla apple-touch).

#### 5. Sinki

**File**: `resources/js/pages/dev/SidebarKitchenSink.vue`, sink `/dev/ui/welcome`

**Intent**: Ramka `brand-mark` i wszystkie miejsca znaku pokazują nowy znak i nazwę.

**Contract**: Sinki renderują prawdziwe komponenty, więc zmiana wymaga najwyżej korekty opisów ramki `brand-mark` (`SidebarKitchenSink.vue:449-475`), które mówią o „HT”.

### Success Criteria:

#### Automated Verification:

- Test przypięcia najpierw pada, potem przechodzi na PostgreSQL: `docker compose exec -u sail -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing laravel.test php artisan test --compact tests/Feature/AppNameIndependentConfigTest.php`
- W UI i zasobach nie zostało „Household Treasurer” ani „HT”: `grep -rn "Household Treasurer" lang resources .env.example .env.prod.example docker-compose.prod.yml docker/8.5/Dockerfile.prod` zwraca tylko przypięte wartości domyślne i komentarze o nich
- `apple-touch-icon.png` ma 180×180, a `favicon.ico` 32×32 (`file public/apple-touch-icon.png public/favicon.ico`)
- `npm run check:ui` przechodzi z `BrandMark.vue` na liście
- Bramka: `docker compose exec -u sail laravel.test composer ci:check` i `vendor/bin/sloppy ci` na hoście; `vendor/bin/pint --dirty --format agent`

#### Manual Verification:

- Zrzuty `/dev/ui/sidebar` (ramka `brand-mark`, `mobile-bar`, oba motywy) i `/dev/ui/welcome?state=guest` pokazują znak i „Domowy Skarbnik”
- Znak czytelny przy 26 px w sidebarze i jako favicon w karcie przeglądarki (16 px), w jasnym i ciemnym motywie
- Tytuł karty przeglądarki po `npm run build` to „… - Domowy Skarbnik”
- Po zmianie `APP_NAME` w lokalnym `.env` zalogowana sesja przeżywa przeładowanie

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie ręcznej weryfikacji.

---

## Faza 3: Jedna zasada kwot

### Overview

Wszystkie tabele bez „zł” w kwotach, wspólna stopka „Kwoty w zł”, ikona gotówki `Coins` i strażnik, który nie pozwoli wrócić do „zł” w wierszach.

### Changes Required:

#### 1. Formatery i ich testy (najpierw test)

**File**: `resources/js/lib/format.ts`, `resources/js/lib/format.test.ts`, `resources/js/lib/import-row-amount.ts`, `resources/js/lib/import-row-amount.test.ts`

**Intent**: Kwota operacji, suma dnia i kwota wiersza podglądu importu bez jednostki. Znak i kolor bez zmian. Asercje testów zmieniają się, bo zmieniło się wymaganie (ADR-0058), i zostają czerwone do zmiany kodu.

**Contract**:

- `formatFinancialOperationAmount` → `"−1 234,56"` / `"+1 234,56"` (U+2212 bez zmian).
- `formatOperationDaySum` → `"+…"`, `"−…"`, `"0,00"`.
- `importRowAmount` zwraca tekst bez „zł”; transfer własny i ruch kapitałowy przez `formatMoneyAmount` ze znakiem z pliku.
- Komentarze JSDoc wskazują ADR-0058.

#### 2. Operacje

**File**: `resources/js/components/OwnTransferRow.vue`, `resources/js/components/CapitalMovementRow.vue`, panel listy Operacji (`resources/js/pages/Operations.vue` lub komponent stopki sekcji)

**Intent**: Wiersze transferów i ruchów kapitału bez „zł” (`:57`, `:79`). Stopka „Kwoty w zł” we wszystkich trzech sekcjach.

**Contract**: Stopka w stylu `meta`. W transferach i ruchach kapitału łączy się z dotychczasowym objaśnieniem w jedną linię („Kwoty w zł · …”).

#### 3. Podgląd importu, Konta, Lokaty, Dashboard

**File**: `resources/js/components/ImportPreviewRow.vue` (przez `import-row-amount`), strona podglądu importu, `resources/js/pages/Accounts.vue:229`, `resources/js/pages/TermDeposits.vue:261`, `resources/js/components/DashboardHoldings.vue`

**Intent**: Kwoty w wierszach bez „zł”, stopka „Kwoty w zł” w każdym z tych paneli.

**Contract**: Komórki używają `formatMoneyAmount` ze stylem `amount` (`amount-sum` w sumach). Miary w `StatPanel` tych stron zostają przy `formatMoney`. W `DashboardHoldings` stopka z sumą dostaje jednostkę w linii `meta`.

#### 4. Plan miesiąca: podpisy w wierszach

**File**: `resources/js/components/MonthPlanItemRow.vue:127`, `resources/js/components/MonthYearSummary.vue:42,44,48`, `resources/js/components/MonthSummaryCard.vue:159`

**Intent**: Podpisy wewnątrz tabeli bez „zł”. Stopka już istnieje.

**Contract**: `formatMoneyAmount` lub `formatSignedMoneyAmount`. Podpisu „Zatwierdzono:” (`:176`) nie ruszaj, usuwa go Faza 4.

#### 5. Wspólny klucz stopki

**File**: `lang/pl/ui.php`, wszyscy użytkownicy `t.budget.amounts_in_zl` (Budżet, `MonthPlanTable.vue:364`, `MonthSummaryCard.vue`, podgląd landingu)

**Intent**: Klucz stopki przestaje być budżetowy, bo używa go każda tabela.

**Contract**: `common.amounts_in_zl` = „Kwoty w zł”. Klucz `budget.amounts_in_zl` usunięty po przeniesieniu wszystkich wywołań. `UiTranslationKeysTest` przechodzi.

#### 6. Ikona gotówki (najpierw test)

**File**: `resources/js/lib/account-identity.ts`, `resources/js/lib/account-identity.test.ts`, `resources/js/components/AccountAvatar.vue`

**Intent**: Konto gotówkowe ma ikonę monet zamiast „zł” we wszystkich miejscach `AccountAvatar`.

**Contract**:

- Wariant ikony w unii `accountAvatarContent` dostaje nazwę: `{ kind: 'icon'; icon: 'cash' | 'other' }`.
- `cash` → `icon: 'cash'` niezależnie od `mark_url`.
- `AccountAvatar` mapuje `cash` → `Coins`, a `other` → `Building2`, z tymi samymi rozmiarami ikon.
- `accountShortMark` dla `cash` nie zwraca „zł”. Albo zwraca `null`, jeśli wołający to obsługuje, albo funkcja przestaje przyjmować `cash`. Wybór zależy od jedynego wołającego, `accountAvatarContent`.
- Testy `:20-22` i `:55-61` opisują nowe zachowanie.

#### 7. Strażnik regresji

**File**: `resources/js/ui-contract.test.ts`

**Intent**: Wiersz tabeli nie wróci do `formatMoney` z jednostką.

**Contract**: Nowy przypadek: komponenty, które są wyłącznie wierszami tabel (`FinancialOperationRow`, `OwnTransferRow`, `CapitalMovementRow`, `OperationsDayRow`, `MonthYearSummary`, `BudgetCategoryRow`, `BudgetAmountCell`), nie importują `formatMoney` ani `formatSignedMoney`. Lista może tylko rosnąć. Poza listą:

- `ImportPreviewRow`, bo ma Alert z kwotą w „zł” (`:708`). Jego komórki kwot idą przez `import-row-amount`, które ma własny test.
- `MonthPlanItemRow`, bo do Fazy 4 trzyma podpis „Zatwierdzono:”. Dopisuje go Faza 4 (§4).

#### 8. Test E2E i sinki

**File**: `tests/e2e/seed.spec.ts:39,42`, sinki `/dev/operations`, `/dev/import-preview`, `/dev/ui/accounts`, `/dev/ui/term-deposits`, `/dev/ui/dashboard`, `/dev/month-plan` (fiksury w `lib/operations-fixtures.ts`, `lib/import-preview-fixtures.ts`, `pages/dev/AccountsKitchenSink.vue`, `pages/dev/TermDepositsKitchenSink.vue`)

**Intent**: Oczekiwanie E2E bez „zł”. Każdy sink pokazuje stopkę i kwoty bez jednostki, a sink Kont pokazuje gotówkę z ikoną `Coins` w obu rozmiarach.

**Contract**: `toContainText('−12,34')` na wierszu operacji. Sink, który składa własną powłokę panelu zamiast prawdziwego komponentu, dostaje stopkę tak samo jak widok.

### Success Criteria:

#### Automated Verification:

- Testy formaterów, `import-row-amount`, `account-identity` i `ui-contract` najpierw padają, potem przechodzą: `npm test`
- W wierszach tabel nie ma „zł”: `grep -rn "formatMoney(\|formatSignedMoney(" resources/js/components/{FinancialOperationRow,OwnTransferRow,CapitalMovementRow,OperationsDayRow,MonthPlanItemRow,MonthYearSummary}.vue` zwraca najwyżej podpis „Zatwierdzono:” w `MonthPlanItemRow.vue` (`ImportPreviewRow` pominięty: jego jedyny `formatMoney` jest w Alercie)
- E2E: `npx playwright test tests/e2e/seed.spec.ts` przechodzi
- `npm run check:ui` i `npm run types:check` przechodzą
- Bramka: `docker compose exec -u sail laravel.test composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- Zrzuty przy 1280 i 390 px sinków `/dev/operations` (trzy zakładki), `/dev/import-preview`, `/dev/ui/accounts`, `/dev/ui/term-deposits`, `/dev/ui/dashboard` i `/dev/month-plan`: kwoty w tabelach bez „zł”, stopka „Kwoty w zł” pod każdą tabelą, „zł” w miarach
- Gotówka ma ikonę monet w Kontach, Dashboardzie, wyborze konta w dialogu operacji, chipie filtra i podglądzie importu

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie ręcznej weryfikacji.

---

## Faza 4: Strzałka zmiany planu

### Overview

Czysta funkcja kierunku i tonu, wspólny komponent strzałki, Budżet ze strzałką, Plan miesiąca z kolumną „Zatwierdzony” i chipem `lg`.

### Changes Required:

#### 1. Kierunek i ton (najpierw test)

**File**: `resources/js/lib/plan-change-direction.ts`, `resources/js/lib/plan-change-direction.test.ts`

**Intent**: Jedno miejsce, które rozstrzyga, w którą stronę zmienił się plan i czy to dobrze, osobno dla wydatków i dochodów.

**Contract**:

- `planChangeDirection(referenceMinorUnits: number, currentMinorUnits: number, categoryType: 'income' | 'expense'): { direction: 'up' | 'down'; tone: 'favorable' | 'unfavorable' } | null`. Wynik `null` przy równości.
- `planChangeToneClass(tone, muted: boolean)` → `text-success` / `text-destructive` / `text-muted-foreground`.
- Testy obejmują:
  - wydatek ↑ → `unfavorable`;
  - wydatek ↓ → `favorable`;
  - dochód ↑ → `favorable`;
  - dochód ↓ → `unfavorable`;
  - równość → `null`;
  - `muted` wygrywa z tonem.

#### 2. Komponent strzałki

**File**: `resources/js/components/PlanChangeArrow.vue` (zastępuje `PlanChangedHint.vue`, stary plik usunięty), `lang/pl/ui.php`, `scripts/check-ui-literals.mjs`

**Intent**: Wspólna strzałka dla Budżetu i Planu miesiąca z podpowiedzią i nazwą dostępną mówiącą o kierunku.

**Contract**:

- Props: `reference`, `current`, `categoryType`, `muted?`, `increasedLabel`, `decreasedLabel`.
- Renderuje się tylko przy wyniku różnym od `null`.
- Przycisk z `Tooltip` jak dotychczas, ikona `ArrowUp` / `ArrowDown` `size-3.5`.
- Klucze:
  - `budget.plan_increased` / `budget.plan_decreased` („Plan zwiększony/zmniejszony względem planu początkowego”) zastępują `budget.plan_changed`;
  - `month_plan.plan_increased` / `month_plan.plan_decreased` („…względem planu zatwierdzonego”).
- Plik trafia na listę `check:ui`.
- `planChanged` w `lib/dashboard-categories.ts` zostaje, jeśli ma inne wywołania; w przeciwnym razie usuń go razem ze starym komponentem.

#### 3. Budżet

**File**: `resources/js/components/BudgetCategoryRow.vue:116`

**Intent**: W komórce „Plan początkowy” strzałka zamiast ikony `History`.

**Contract**: `reference` = `initial_plan_minor_units`, `current` = `current_plan_minor_units`, `categoryType` z propsa wiersza, `muted` = `is_archived`.

#### 4. Plan miesiąca: kolumna „Zatwierdzony”

**File**: `resources/js/components/MonthPlanItemRow.vue`, `resources/js/components/MonthPlanTable.vue` (wiersz nagłówka, wiersze sum, wiersz odkładania, wiersze grup i bezczynne), `resources/css/app.css` (`.month-plan-grid`), `lang/pl/ui.php`, strona `resources/js/pages/MonthPlan.vue` (jeśli wiersz potrzebuje kwoty na żywo)

**Intent**: Kwota zatwierdzona jako osobna, wyciszona kolumna przed „Plan”, ze strzałką, jak „Plan początkowy” w Budżecie. Podpis „Zatwierdzono:” znika.

**Contract**:

- Siatka od `xl`: `'category kind approved plan actual deviation status'` z kolumną `approved` `7rem` (zakres zgodny z kolumną „Plan”). Wariant `-no-kind` bez zmian w zasadzie.
- Poniżej `xl` wiersz kwot ma cztery komórki (`approved plan actual deviation`), więc układ piętrowy przechodzi na cztery kolumny. Jeden markup, bez drugiego drzewa.
- Przy 390 px kolumna ma ok. 72 px, a „12 345,67” ok. 68 px. Dlatego poniżej `xl` strzałka stoi w linii podpisu mobilnego, obok „Zatwierdzony”, a nie obok kwoty. Od `xl` stoi obok kwoty, jak w Budżecie.
- Wariant awaryjny: jeśli zrzut przy 390 px pokaże ucięte kwoty lub pole, wiersz kwot poniżej `xl` przechodzi na siatkę 2×2 (`'approved plan'` / `'actual deviation'`). Wybrany wariant trafia do ADR-0060.
- Komórka `.month-plan-cell-approved`:
  - podpis mobilny `xl:sr-only`;
  - `amount text-muted-foreground` z `formatMoneyAmount(approved_minor_units)`;
  - „—” z tekstem dla czytnika przy `approved_minor_units === null`;
  - strzałka z `reference` = zatwierdzona i `current` = `rowState(row).planMinorUnits` (na żywo w trybie zmian).
- Źródło kwoty na żywo:
  - `MonthPlanRowState` (`MonthPlanTable.vue:26`) dostaje pole `planMinorUnits: number | null`;
  - `rowState` w `MonthPlan.vue` wylicza je jako `isEditable && isEditableRow(row) ? parsedAmount(row) : row.plan_minor_units`. Nie wolno brać samego `parsedAmount`, bo dla wiersza bez pola zwraca 0, co dałoby fałszywą strzałkę ↓;
  - przy `null` (błędny wpis) strzałka się nie pokazuje;
  - fabryka `rowState` w `resources/js/pages/dev/MonthPlanSink.vue:200` dostaje to samo pole w tej samej fazie.
- Nagłówek „Zatwierdzony” (`month_plan.approved_plan`). Klucz `month_plan.approved_amount` usunięty, jeśli nie ma innych wywołań.
- Po usunięciu podpisu „Zatwierdzono:” `MonthPlanItemRow` nie importuje `formatMoney` i trafia na listę strażnika w `ui-contract.test.ts` (Faza 3, §7).
- Wiersze sum, odkładania, grup i bezczynne mają pustą komórkę albo rozciągnięty obszar, tak żeby kolumny się nie przesunęły.

#### 5. `CategoryChip` w rozmiarze `lg`

**File**: `resources/js/components/CategoryChip.vue`, `resources/css/app.css`, `resources/js/components/MonthPlanItemRow.vue:77-129`, `scripts/check-ui-literals.mjs`, `tests/Unit/MonthPlanHardcodedUiTest.php`

**Intent**: Ikona kategorii wysoka na nazwę razem z podpisem: ikona po lewej, obok nazwa w obecnym rozmiarze, pod nią podpis roczny lub dostępny limit.

**Contract**:

- `size?: 'sm' | 'md' | 'lg'` i opcjonalny slot `subtitle`.
- `lg` to kafelek o wysokości linii nazwy (14/20) plus linii `meta` (11/14) i odstępu, jako nazwana klasa w `app.css` (np. `.category-chip-lg` z `size-9`), bez wartości arbitralnej.
- `MonthPlanItemRow` przenosi linię roczną i dostępny limit do slotu.
- Odznaki (jednorazowa, zarchiwizowana, poza planem) zostają w linii nazwy.
- `CategoryChip.vue` i `PlanChangeArrow.vue` trafiają na listę `check:ui` i do listy plików `MonthPlanHardcodedUiTest`.

#### 6. Sinki

**File**: `resources/js/pages/dev/BudgetKitchenSink.vue` (fiksury Budżetu), `resources/js/lib/month-plan-fixtures.ts`, `resources/js/pages/dev/MonthPlanSink.vue`

**Intent**: Każdy kierunek i ton widać w sinku.

**Contract**:

- Budżet: wydatek zwiększony, wydatek zmniejszony, dochód zwiększony, dochód zmniejszony, zarchiwizowany ze zmianą, bez zmiany.
- Plan miesiąca, w odczycie i w edycji: zatwierdzony bez zmiany, zwiększony i zmniejszony wydatek, zwiększony dochód, propozycja („—”), wiersze z podpisem rocznym i z dostępnym limitem w chipie `lg`.

### Success Criteria:

#### Automated Verification:

- `plan-change-direction.test.ts` najpierw pada, potem przechodzi: `npm test`
- Testy sinków przechodzą na PostgreSQL: `docker compose exec -u sail -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing laravel.test php artisan test --compact tests/Feature/BudgetKitchenSinkTest.php tests/Feature/DevMonthPlanSinkTest.php`
- `MonthPlanHardcodedUiTest` z nowymi plikami przechodzi, a `npm run check:ui` i `npm run types:check` są zielone
- `PlanChangedHint` i `History` zniknęły: `grep -rn "PlanChangedHint\|History" resources/js` nic nie zwraca (poza niezwiązanymi trafieniami, jeśli są)
- Bramka: `docker compose exec -u sail laravel.test composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- Zrzuty `/dev/kitchen-sink/budget` i `/dev/month-plan` przy 1280 i 390 px w obu motywach: strzałki we właściwym kierunku i kolorze, wiersz zarchiwizowany wyciszony, kolumna „Zatwierdzony” wyrównana z nagłówkiem
- W trybie zmian Planu miesiąca strzałka zmienia kierunek podczas wpisywania kwoty
- Kafelek kategorii ma wysokość nazwy razem z podpisem
- Podpowiedź i nazwa strzałki czytane przez czytnik ekranu mówią „zwiększony” albo „zmniejszony”

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie ręcznej weryfikacji.

---

## Faza 5: Szerokie ekrany

### Overview

Płynna szerokość kolumny Konta i Lokaty na Dashboardzie, miernik zajmujący wolne miejsce w tabeli wykonania i rosnące kolumny Budżetu.

### Changes Required:

#### 1. Dashboard

**File**: `resources/css/app.css:167` (`--aside-width`), `resources/css/app.css:1567-1581` (`.category-meter-*` od 38rem)

**Intent**: Na szerokim ekranie Konta i Lokaty dostają więcej miejsca, a w tabeli wykonania nie zostaje pusta przestrzeń, bo zajmuje ją miernik.

**Contract**:

- `--aside-width: clamp(18.75rem, 22vw, 26rem)` (token wymiaru, bez `.dark`).
- Od 38rem: `minmax(8rem, 1.4fr) repeat(3, minmax(5rem, 0.5fr)) minmax(9rem, 1.2fr)`. Przy progu (ok. 608 px) kwoty zostają na 5rem, a kategoria i miernik mają tyle co dziś. Przy ok. 1100 px miernik ma ok. 19rem.
- Komentarz nad regułą opisuje ADR-0061.
- Układ poniżej 38rem bez zmian.

#### 2. Budżet

**File**: `resources/css/app.css:1073-1082` (`.budget-category-grid` od `xl`, oba warianty uchwytu)

**Intent**: Kolumny kwot i postęp rosną na dużym ekranie.

**Contract**: `2.5rem minmax(0, 3fr) repeat(4, minmax(6.5rem, 1fr)) minmax(6rem, 1.5fr) 4.5rem` (wariant bez uchwytu bez `2.5rem`). Przy 1280 px kwoty zostają na 6.5rem, a kategoria ma ok. 243 px (dziś ok. 236). Przy 1920 px kwoty mają ok. 10rem. Stałe maksima (`minmax(6.5rem, 8.5rem)`) są wykluczone, bo przy 1280 px zostawiłyby kategorii ok. 12 px. Wiersz nagłówka i wiersz sumy używają tej samej klasy, więc zostają wyrównane. Szkic bez zmian.

#### 3. Sinki i zrzuty

**File**: `context/changes/ui-fixes-2/screenshots/`

**Intent**: Dowód, że kolumny rosną bez pustych pasów i nie łamią układu na wąskich ekranach.

**Contract**: Jednorazowy skrypt Playwright (niecommitowany) robi pełne zrzuty `/dev/ui/dashboard` i `/dev/kitchen-sink/budget` przy 390, 1024, 1280, 1920 i 2560 px.

### Success Criteria:

#### Automated Verification:

- `npm run check:ui` przechodzi, a `DashboardHardcodedUiTest` jest zielony
- Bramka: `docker compose exec -u sail laravel.test composer ci:check` i `vendor/bin/sloppy ci`

#### Manual Verification:

- Przy 1920 i 2560 px miernik w tabelach Dochody i Wydatki wypełnia wolną szerokość, a kolumna Konta i Lokaty jest szersza niż 300 px
- Przy 1024 i 1280 px tabela wykonania przełącza się jak dotąd (próg 38rem), a kolumna boczna ma 300 px
- W Budżecie przy 1920 i 2560 px kolumny kwot i postęp są szersze, a nazwa kategorii nie jest ucinana przy 1280 px
- Przy 390 px oba widoki wyglądają jak przed zmianą

**Implementation Note**: Po fazie zatrzymaj się na potwierdzenie ręcznej weryfikacji.

---

## Testing Strategy

### Unit Tests:

- `plan-change-direction.test.ts`: cztery kombinacje kierunku i typu, równość, `muted`.
- `format.test.ts`, `import-row-amount.test.ts`: kwoty wierszy bez „zł”, znak U+2212 i kolor bez zmian, zero jako `0,00`.
- `account-identity.test.ts`: gotówka → ikona `cash` także z `mark_url`; „Inny” → `other`; banki bez zmian.
- `ui-contract.test.ts`: wiersze tabel nie importują `formatMoney`; `BrandMark` w czterech plikach (bez zmian).

### Integration Tests:

- `AppNameIndependentConfigTest`: identyfikatory infrastruktury nie zależą od `APP_NAME`.
- Testy sinków (`BudgetKitchenSinkTest`, `DevMonthPlanSinkTest`, `DevOperationsSinkTest`) i `MonthPlanHardcodedUiTest`.
- E2E `seed.spec.ts`: wiersz operacji z kwotą bez „zł”.

### Manual Testing Steps:

1. Zmień lokalnie `APP_NAME` na „Domowy Skarbnik”, przeładuj zalogowaną stronę: sesja trwa, nazwa i znak widoczne w sidebarze, na pasku mobilnym, w logowaniu i na landingu.
2. Przejdź Operacje, Konta, Lokaty, import (krok 2), Dashboard, Budżet i Plan miesiąca: w żadnej tabeli nie ma „zł”, pod każdą jest „Kwoty w zł”.
3. W Planie miesiąca wejdź w tryb zmian, podnieś i obniż plan kategorii wydatków i dochodów: strzałka zmienia kierunek i kolor.
4. Obejrzyj Dashboard i Budżet przy 1920 i 2560 px.

## Performance Considerations

Brak. Zmiany to CSS, formatowanie i statyczne zasoby. `clamp()` i `minmax()` nie dodają pracy po stronie JS.

## Migration Notes

- Bez migracji bazy.
- Wdrożenie Fazy 2 według „Critical Implementation Details”: sprawdź produkcyjne `APP_NAME` przed wdrożeniem, przebuduj obraz (`VITE_APP_NAME`), a zmianę `APP_NAME` w produkcyjnym `.env` zrób dopiero po wdrożeniu kodu z przypięciem.
- Wycofanie: przywrócenie `APP_NAME` w `.env` nic nie psuje, bo przypięte wartości są stałe.

## References

- Research: `context/changes/ui-fixes-2/research.md`
- Decyzje z sesji `/10x-ui` (2026-11-07): zakres tylko wizualny, kolor strzałki wg typu kategorii, sidebar i „plan = wydatki” jako osobne zmiany
- ADR-0037, 0039, 0042, 0043, 0044, 0047, 0050, 0051, 0052, 0053, 0054, 0057: `context/foundation/adr/`
- Strona design systemu: `context/foundation/design-system.md`
- Wzorzec kolumny planu początkowego: `resources/js/components/BudgetCategoryRow.vue:110-120`
- Gałąź ikony w znaku konta: `resources/js/components/AccountAvatar.vue`

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles. See `references/progress-format.md`.

### Phase 1: Decyzje na papierze

#### Automated

- [x] 1.1 Cztery ADR-y istnieją: `ls context/foundation/adr/0058-* context/foundation/adr/0059-* context/foundation/adr/0060-* context/foundation/adr/0061-*`
- [x] 1.2 Każdy z ADR-ów 0039, 0044, 0047, 0051, 0052, 0053, 0054, 0057, 0042 i 0043 kończy się odwołaniem do nowego ADR-u
- [x] 1.3 Na stronie design systemu i w regułach nie ma starych wyjątków (grep zwraca pusto)
- [x] 1.4 Indeks reguł przebudowany: `php artisan boost:index-rules` kończy się sukcesem

#### Manual

- [x] 1.5 Użytkownik czyta cztery ADR-y i zmienione sekcje `design-system.md` i potwierdza, że oddają decyzje z planu

### Phase 2: Nazwa i znak

#### Automated

- [ ] 2.1 Test przypięcia najpierw pada, potem przechodzi na PostgreSQL
- [ ] 2.2 W UI i zasobach nie zostało „Household Treasurer” ani „HT” poza przypiętymi wartościami domyślnymi
- [ ] 2.3 `apple-touch-icon.png` ma 180×180, a `favicon.ico` 32×32
- [ ] 2.4 `npm run check:ui` przechodzi z `BrandMark.vue` na liście
- [ ] 2.5 Bramka: `composer ci:check` przez Sail, `vendor/bin/sloppy ci` i `vendor/bin/pint --dirty --format agent`

#### Manual

- [ ] 2.6 Zrzuty `/dev/ui/sidebar` i `/dev/ui/welcome?state=guest` pokazują znak i „Domowy Skarbnik”
- [ ] 2.7 Znak czytelny przy 26 px w sidebarze i jako favicon 16 px, w jasnym i ciemnym motywie
- [ ] 2.8 Tytuł karty przeglądarki po `npm run build` to „… - Domowy Skarbnik”
- [ ] 2.9 Po zmianie `APP_NAME` w lokalnym `.env` zalogowana sesja przeżywa przeładowanie

### Phase 3: Jedna zasada kwot

#### Automated

- [ ] 3.1 Testy formaterów, `import-row-amount`, `account-identity` i `ui-contract` najpierw padają, potem przechodzą: `npm test`
- [ ] 3.2 W wierszach tabel nie ma `formatMoney(` ani `formatSignedMoney(` poza podpisem „Zatwierdzono:”
- [ ] 3.3 E2E: `npx playwright test tests/e2e/seed.spec.ts` przechodzi
- [ ] 3.4 `npm run check:ui` i `npm run types:check` przechodzą
- [ ] 3.5 Bramka: `composer ci:check` przez Sail i `vendor/bin/sloppy ci`

#### Manual

- [ ] 3.6 Zrzuty przy 1280 i 390 px sześciu sinków: kwoty w tabelach bez „zł”, stopka „Kwoty w zł” pod każdą tabelą, „zł” w miarach
- [ ] 3.7 Gotówka ma ikonę monet w Kontach, Dashboardzie, wyborze konta, chipie filtra i podglądzie importu

### Phase 4: Strzałka zmiany planu

#### Automated

- [ ] 4.1 `plan-change-direction.test.ts` najpierw pada, potem przechodzi: `npm test`
- [ ] 4.2 Testy sinków Budżetu i Planu miesiąca przechodzą na PostgreSQL
- [ ] 4.3 `MonthPlanHardcodedUiTest` z nowymi plikami, `npm run check:ui` i `npm run types:check` są zielone
- [ ] 4.4 `PlanChangedHint` i `History` zniknęły z `resources/js`
- [ ] 4.5 Bramka: `composer ci:check` przez Sail i `vendor/bin/sloppy ci`

#### Manual

- [ ] 4.6 Zrzuty `/dev/kitchen-sink/budget` i `/dev/month-plan` przy 1280 i 390 px w obu motywach: strzałki we właściwym kierunku i kolorze, kolumna „Zatwierdzony” wyrównana
- [ ] 4.7 W trybie zmian Planu miesiąca strzałka zmienia kierunek podczas wpisywania kwoty
- [ ] 4.8 Kafelek kategorii ma wysokość nazwy razem z podpisem
- [ ] 4.9 Podpowiedź i nazwa strzałki mówią „zwiększony” albo „zmniejszony”

### Phase 5: Szerokie ekrany

#### Automated

- [ ] 5.1 `npm run check:ui` przechodzi, a `DashboardHardcodedUiTest` jest zielony
- [ ] 5.2 Bramka: `composer ci:check` przez Sail i `vendor/bin/sloppy ci`

#### Manual

- [ ] 5.3 Przy 1920 i 2560 px miernik wypełnia wolną szerokość, a kolumna Konta i Lokaty jest szersza niż 300 px
- [ ] 5.4 Przy 1024 i 1280 px tabela wykonania przełącza się jak dotąd, a kolumna boczna ma 300 px
- [ ] 5.5 W Budżecie przy 1920 i 2560 px kolumny kwot i postęp są szersze, nazwa kategorii nieucinana przy 1280 px
- [ ] 5.6 Przy 390 px oba widoki wyglądają jak przed zmianą
