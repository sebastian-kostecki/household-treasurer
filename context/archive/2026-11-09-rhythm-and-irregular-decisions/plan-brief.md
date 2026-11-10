# Rhythm and Irregular Decisions — Plan Brief

> Full plan: `context/changes/rhythm-and-irregular-decisions/plan.md`

## What & Why

F-05 zapisuje przed kodem decyzje domenowe rytmu kategorii i wydatku nieregularnego: cztery rytmy zamiast flag Paced / One-off i Month Item Kind, stawkę i „zebrane”, regułę „do wydania” dla nieregularnej oraz przejście zebranego przez 31 grudnia. Wymaga tego AGENTS.md, bo to jedyna w kamieniu milowym zmiana reguły „do wydania”. Rozstrzyga też Otwarte pytania 1 (mapowanie flag) i 2 (nazwy) z PRD v3.

## Starting Point

Dwie flagi na Budget Line (ADR-0063) działają w dwóch planach: `month_item_kind` steruje „do wydania”, odchyleniem i tempem, a `is_paced` propozycją (jednorazowa = 0), Month Remaining, znacznikiem roku i sygnałem rocznego limitu. Propozycje planu nie są zapisywane, a zmiana rodzaju pozycji dziś przelicza też minione miesiące. Na devie 29 wydatków ma „równomierna + bieżąca”, 2 „jednorazowa + bieżąca”, a żaden nie jest „rachunkiem”.

## Desired End State

ADR-0066 i słownik mówią jednoznacznie, co każdy rytm robi w każdym odczycie, gdzie rytm mieszka, od kiedy działa i jak liczą się stawka i zebrane, z przykładami na Prądzie i danych z deva. ADR-0024, 0030, 0033, 0043, 0050, 0062 i 0063 odsyłają do ADR-0066. S-24, S-26 i S-27 wdrażają to bez nowych decyzji.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Rytmy | stała opłata / bieżąca / równomierna roczna / nieregularna; przychód bez rytmu | Scalają dwie flagi w jedną decyzję kategorii. | PRD v3 |
| Mapowanie flag | równomierna + bieżąca → bieżąca; jednorazowa → równomierna roczna; rachunek → stała opłata; żadna nieregularna | „Do wydania” zatwierdzonych miesięcy zostaje co do grosza, jednorazowa zachowuje sygnał wobec roku. | Plan |
| Nazwy | „stawka”, „zebrane”, „zebrane startowe”; EN Irregular Rate, Collected, Opening Collected | „Odkładam” zostaje wyłącznie przy Set-aside. | Plan |
| Stawka | kwota pozycji w planie miesiąca; propozycja floor(plan roczny / 12), reszta w grudniu | Zebrane i „do wydania” zawsze zgodne, zmiana planu nie przelicza minionych miesięcy. | Plan |
| Propozycja stałej opłaty | ostatnia zapłacona kwota, bez zapłaty plan poprzedniego miesiąca | Nadąża za ratą i rachunkiem jak w Excelu; różni się od bieżącej. | Plan |
| Zmiana rytmu | historia (rytm, miesiąc początku) na kategorii; działa od bieżącego miesiąca; zdjęcie „nieregularnej” kończy zebrane | Skutek widać od razu, minione miesiące bez zmian. | Plan |
| Zebrane | odczyt miesiąc po miesiącu: max(0, początek + stawka − rachunki netto); nadwyżka obniża „do wydania” | Nadwyżka nie jest liczona dwa razy, a korekta przelicza się sama. | PRD v3 + Plan |
| 31 grudnia | łańcuch idzie dalej; zebrane startowe nowego roku = odczyt na 31 grudnia | Budżet 2027 powstaje w grudniu, gdy zebrane jeszcze się zmienia. | PRD v3 |
| Ponad plan | stała i bieżąca wobec miesiąca, równomierna wobec roku, nieregularna przy nadwyżce > 0 | Sygnał mówi o faktycznym przekroczeniu w sensie rytmu. | PRD v3 + Plan |
| Tempo / Month Remaining | tempo tylko bieżąca; Month Remaining: stała + bieżąca | Zachowuje dzisiejsze pule na istniejących danych. | Plan |

## Scope

**In scope:**
- ADR-0066 z tabelą rytm × odczyt, migracją flag i odrzuconymi opcjami
- Odsyłacze w ADR-0024, 0030, 0033, 0043, 0050, 0062, 0063
- Słownik: Rhythm, Irregular Rate, Collected, Opening Collected; przepisane To Spend i trzy wpisy flag; dopiski w zależnych wpisach

**Out of scope:**
- Kod, migracje, testy, `lang/`, UI (S-24, S-26, S-27)
- Grupy w planie (S-25), domyślny procent „odkładam” (S-33), scalanie (S-21)
- Zmiany w PRD, `.ai/rules`, archiwalnych specyfikacjach

## Architecture / Approach

Najpierw decyzja, potem słownik, oba po angielsku z polskim copy. Rytm to historia wpisów na kategorii listy: rytm miesiąca m to wpis o największym miesiącu początku ≤ m. Stawka to kwota pozycji w planie miesiąca, a zebrane to łańcuch odczytów od miesiąca oznaczenia, z progiem 0 w każdym miesiącu. Te same frazy predykatów stoją w ADR i słowniku, a weryfikacja ich szuka.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. ADR-0066 i odsyłacze | Decyzja z tabelą rytm × odczyt i przykładami; linie „Amended by” w siedmiu ADR | Niejednoznaczny predykat utrwali się w migracji S-24 |
| 2. Słownik | Cztery nowe wpisy, przepisane To Spend i flagi, dopiski | Wpis sprzeczny z ADR-0066 albo pomylenie zebranego z Set-aside |

**Prerequisites:** brak (F-05 jest równoległy z S-18; ADR-0063 i ADR-0065 już są)
**Estimated effort:** ~1 sesja w 2 fazach

## Open Risks & Assumptions

- Pula Month Remaining „stała + bieżąca” jest dokładna tylko bez pozycji „jednorazowa + rachunek”. Na devie ich nie ma; S-24 liczy je na kopii produkcji.
- Miesiąc bez zatwierdzonego planu liczy stawkę z bieżącego planu rocznego, więc późniejsza zmiana planu go przelicza. Dashboard ostrzega o braku zatwierdzenia od 3. dnia.
- Po migracji Rekolekcje i Ubezpieczenie samochodu proponują 198,33 i 45,08 zł miesięcznie, dopóki użytkownik nie oznaczy ich jako nieregularne.

## Success Criteria (Summary)

- US-02 (rachunek 400 i 600), podniesienie planu w lipcu i 31 grudnia przeliczają się według samego słownika na te same liczby co w ADR-0066.
- Żaden wpis słownika ani stary ADR nie mówi już, że flagi Paced / One-off i Month Item Kind obowiązują bez rytmu.
- S-24, S-26 i S-27 mają komplet decyzji domenowych.
