# Podpowiedzi Importu na liście kategorii — Plan Brief

> Full plan: `context/changes/import-suggestions-on-category-list/plan.md`

## What & Why

S-23 (FR-020): import ma podpowiadać kategorię listy co najmniej tak trafnie jak przed zmianą listy kategorii. Dziś podpowiedź milczy dla aktywnej kategorii bez pozycji budżetu w roku wiersza, choć selektor ją oferuje, a ADR-0063 na nią pozwala. Do tego nikt jeszcze nie zmierzył trafności po S-18, a stare narzędzie pomiaru przestało działać.

## Starting Point

Model już głosuje po id kategorii listy (S-18, test równoważności ze starym głosem po nazwie). Cel podpowiedzi wymaga jednak istniejącej pozycji w roku wiersza (`ImportCategorySuggester::lineIdsByYearAndCategory()`). Przed S-18 cel brał się z oferty selektora, a ta obejmowała tylko kategorie z rekordem w roku. S-18 poszerzyło selektor o kategorie bez pozycji, a cel podpowiedzi za nim nie poszedł. `eval_service.php` z poprzedniej zmiany wywala się na nowym schemacie. Została tylko kopia produkcji z 11.09 w starym schemacie.

## Desired End State

Podpowiedź trafia do każdej kategorii, którą selektor oferuje w roku wiersza, a zatwierdzenie dołącza brakującą pozycję. `php artisan import:suggestion-accuracy {user}` mierzy pokrycie i trafność w każdej chwili, także na produkcji (tylko czyta). `measurements.md` pokazuje na tych samych danych (11.09, stary i nowy kod), że „po” nie jest gorsze, plus odczyt produkcyjny po scaleniach par.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Reguła celu | cel = oferta `CategoryChoiceOptions` w roku wiersza (bez wymogu pozycji) | ADR-0063:32 i słownik już to mówią, a przed S-18 tak było; jedno źródło wyklucza rozjazd z selektorem | Plan (z kodu i ADR) |
| Dane i moment pomiaru (PRD, pytanie 4) | C: kopia z 11.09 na starym (`bbf49ba^`) i nowym kodzie + odczyt na produkcji po scaleniach | tylko ta sama baza pod dwoma kodami dowodzi „nie gorzej”; świeża kopia pokazuje stan po porządkach | Użytkownik |
| Kryterium „nie gorzej” | reguła 1: pokrycie i trafność ≥ „przed” w każdym okresie i na każdym poziomie; każdy spadek = stop z listą wierszy | wprost FR-020, każda różnica zostaje obejrzana | Użytkownik |
| Narzędzie pomiaru | komenda artisan tylko do odczytu + test Pest, `--output` / `--compare` jak `app:control-numbers` | stary skrypt zestarzał się po cichu; komenda z testem pęknie głośno i działa na produkcji | Użytkownik |
| Porównanie przed/po | per wiersz po `operation_id`, wartości logiczne zamiast id kategorii, ułamki zamiast procentów | id kategorii różnią się między schematami, a id operacji przeżywają migrację | Plan |
| Strojenie modelu | żadne: przy spadku stop i raport | jak w poprzedniej zmianie; strojenie pod jeden zbiór danych zaciera dowód | Plan |

## Scope

**In scope:**
- Reguła celu podpowiedzi i testy:
  - kategoria bez pozycji;
  - kategoria po scaleniu;
  - zgodność z selektorem;
  - `assertInertia` podglądu;
  - NFR na PostgreSQL.
- `ImportCategorySuggestionAccuracy` + komenda `import:suggestion-accuracy` + test + README.
- Skrypt „przed” na `bbf49ba^`, pomiar na kopii z 11.09, `measurements.md`.
- Odpowiedź na pytanie 4 w PRD i roadmapie.
- Odczyt produkcyjny po scaleniach.

**Out of scope:**
- Strojenie stałych modelu, świeżość, uczenie z odrzuceń i wpisów ręcznych.
- Zmiany UI i propsów.
- `onTypeChange` nadpisujące ręczny wybór.
- Próg trafności w CI.
- Nowy ADR.
- Same scalenia par na produkcji (robi je użytkownik przez S-21).

## Architecture / Approach

`CategoryChoiceOptions::forType()` → nowe `ImportCategorySuggestionTargets` (rok → kategorie oferowane), z którego korzysta suggester. Nowy odczyt `ImportCategorySuggestionAccuracy` używa historii suggestera, modelu i tych samych celów: odtwarza historię chronologicznie i liczy dwa poziomy (model, podpowiedź) w dwóch okresach (cały rok, sierpień–listopad). Komenda drukuje wynik, zapisuje JSON bez opisów i porównuje z plikiem „przed”, który skrypt na starym commicie zapisuje w tym samym formacie.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Cel = oferta selektora | podpowiedź dla kategorii bez pozycji; test czerwony → zielony | zmiana asercji testu `:74` musi stać na ADR-0063, nie na wygodzie |
| 2. Komenda pomiaru | `import:suggestion-accuracy` z `--output` / `--compare` i testem | format pliku musi dać się wytworzyć też na starym kodzie |
| 3. Pomiar na kopii z 11.09 | „przed” i „po” na tej samej bazie, `measurements.md` | archiwum może wymagać `CREATE ROLE treasurer`; `DROP SCHEMA` w złej bazie |
| 4. Odczyt na produkcji | liczby po wdrożeniu i scaleniach par | czeka na wdrożenie F-06/S-23 i scalenia |

**Prerequisites:**
- Gałąź na `development` (zrobione: rebase na 9b7f1bd).
- Hasło archiwum `prod-backup.zip` od użytkownika w fazie 3.
- Dostęp do serwera w fazie 4.

**Estimated effort:** ~2 sesje na fazy 1–2, 1 sesja z użytkownikiem na fazę 3, krótki odczyt w fazie 4 po wdrożeniu.

## Open Risks & Assumptions

- Zakładam, że `prod-backup.zip` z 11.09 to archiwum Spatie, które da się przywrócić `backup:restore` na `bbf49ba^`. Jeśli nie, faza 3 staje i wraca do użytkownika.
- Na danych z 11.09 każda kategoria miała pozycję w 2026, więc spodziewane „przed” ≈ „po”. Różnice mogą dać nazwy złożone przy migracji (wielkość liter, spacje, sufiks „(k)”). Reguła 1 każe je obejrzeć.
- Dane do 11.09: okres „sierpień–listopad” obejmuje w praktyce sierpień i część września.

## Success Criteria (Summary)

- Kategoria aktywna bez pozycji w roku wiersza dostaje podpowiedź „✦” i zatwierdza się z dołączeniem pozycji.
- Na kopii z 11.09 `--compare` kończy się kodem 0: żaden okres ani poziom nie spadł.
- Komenda działa na produkcji i jej odczyt po scaleniach jest w `measurements.md`.
