<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Rytm kategorii w propozycji planu (S-24)

- **Plan**: context/changes/category-rhythm/plan.md
- **Mode**: Deep
- **Date**: 2026-11-10
- **Verdict**: REVISE → SOUND (po triage: wszystkie 6 ustaleń FIXED)
- **Findings**: 0 critical, 4 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | PASS |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | WARNING |
| Plan Completeness | WARNING |

## Grounding
121/121 paths ✓, 8/8 symbols ✓, ADR-0070 wolny ✓, Progress↔Fazy ✓, brief↔plan ✓ (poza F4)

Potwierdzone bez ustaleń: `user:backup` w `deploy.sh` działa w starym kontenerze (stary kod); średnia ADR-0033 = floor(plan / 12), reszta w grudniu; pula tempa już dziś wyklucza rachunki; `MonthCategoryActuals::hasPositiveAmount()` wystarcza dla stałej opłaty; liczby kontrolne nie czytają flag; żaden test E2E nie dotyka przełącznika rodzaju ani pól flag.

## Findings

### F1 — Zapis tego samego rytmu zostawia przyszły wpis

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Critical Implementation Details — Reguła zapisu; Faza 2 §1
- **Detail**: No-op „rytm bieżącego miesiąca równa się zapisywanemu” zostawia przyszłe wpisy z migracji (szkic 2027 z innymi flagami niż 2026: bieżąca od 01.2026, równomierna roczna od 01.2027). Lista pokazuje tylko bieżący miesiąc, widoku historii nie ma, więc zapis „bieżąca” w listopadzie niczego nie zmienia, a 2027 zostaje równomierną roczną bez śladu w UI.
- **Fix A ⭐ Recommended**: No-op tylko gdy rytm bieżącego miesiąca równy i brak wpisów ze startem > bieżący miesiąc; inaczej usuń wpisy ≥ bieżący i wstaw jeden. Nowy przypadek w `CategoryRhythmWriteTest`.
  - Strength: Zapis zawsze znaczy „od teraz tak”, zgodnie z „replaces all entries from the current month”.
  - Tradeoff: Może powstać wpis identyczny z poprzednim (dozwolone, scala tylko migracja).
  - Confidence: HIGH — prosta zmiana warunku, ADR to dopuszcza.
  - Blind spot: Nieznana liczba kategorii na produkcji z różnymi flagami w 2026 i 2027.
- **Fix B**: Zostaw regułę, pokaż w wierszu „Kategorii” przyszły rytm.
  - Strength: Nic nie znika bez wiedzy użytkownika.
  - Tradeoff: Nowy prop i stan UI; zbliża się do „widoku historii” poza zakresem.
  - Confidence: MED — wymaga decyzji o copy.
  - Blind spot: Okno nadal nie pozwala usunąć przyszłego wpisu wprost.
- **Decision**: FIXED (Fix A)

### F2 — Seeder demo tworzy wydatki bez wpisu rytmu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 2 §1 i §5
- **Detail**: `DemoHouseholdSeeder.php:267,277` (wołany przez `DatabaseSeeder` i `SeedDemoHouseholdCommand`) tworzy kategorie i pozycje poza `ChangeCategoryRhythm`. Łamie inwariant „każdy wydatek ma ≥ 1 wpis”, a restore v9 odrzuca „wydatek bez wpisu”, więc backup użytkownika demo się nie wczyta. To samo dotyczy `Category::factory()` w testach round trip.
- **Fix**: Faza 2 §1 dopisuje `DemoHouseholdSeeder` (wpis bieżącej przez `ChangeCategoryRhythm`) i `afterCreating` w `CategoryFactory` dla wydatku; test: seed demo → backup v9 → restore.
- **Decision**: FIXED

### F3 — Kroki ręczne na dev przeczą kryterium 4.5

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Fazy 2–4 Manual Verification; Manual Testing Steps 1–4
- **Detail**: 2.5 zmienia „Czynsz” na stałą opłatę od listopada, 3.6 dodaje operację 950,00 w listopadzie; obie zmieniają „do wydania” listopada, a 4.5 porównuje je z wartością sprzed fazy 1.
- **Fix**: Po 3.6 przywrócić „Czynsz” na bieżącą i usunąć operację 950,00 (Manual Testing Steps); albo 4.5 porównuje z wartością po tym sprzątaniu.
- **Decision**: FIXED

### F4 — Grep fazy 6 trafi w LegacyRhythmConversion i ControlNumbers

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Faza 6 Success Criteria 6.2; brief „Success Criteria”
- **Detail**: Kryterium oczekuje „tylko migracje i RestoreUserData”, ale w `app/` z założenia zostają `LegacyRhythmConversion` (wejście flag) i `ControlNumbers` (`meta.one_off_bill_lines`); migracje nie są w przeszukiwanych katalogach. Brief mówi „grep … pusty”.
- **Fix**: Wymienić oczekiwane trafienia (`RestoreUserData`, `LegacyRhythmConversion`, `ControlNumbers`) i poprawić zdanie w briefie.
- **Decision**: FIXED

### F5 — Y w „zostało X na Y mies.” nieokreślone dla przyszłego roku

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Blind Spots
- **Location**: Faza 3 §2
- **Detail**: Y zdefiniowane tylko „dla bieżącego roku”; budżet 2027 może być w realizacji w listopadzie 2026 (`StartBudgetExecution` sprawdza tylko `isDraft()`), a wtedy wszystkie jego miesiące są „przyszłe”.
- **Fix**: Dla roku późniejszego niż bieżący Y = 12 (X = plan aktualny − wykonanie tego roku); miesiące roku minionego → `null`; przypadek w `MonthPlanPageTest`.
- **Decision**: FIXED

### F6 — Pominięte fikstury kitchen sinków i reguła snapshotu

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Fazy 4–5 (pliki), Faza 6 §4
- **Detail**: `resources/js/lib/operation-dialogs-fixtures.ts` i `resources/js/lib/import-preview-fixtures.ts` niosą flagi w payloadach zmienianych w fazach 4–5, a nie figurują w planie; `.ai/rules/snapshot.md` opisuje każdą wersję, a Faza 6 §4 nie dopisuje v9.
- **Fix**: Dopisać oba pliki fikstur do faz 4 §5 / 5 §4, a `.ai/rules/snapshot.md` do Fazy 6 §4.
- **Decision**: FIXED
