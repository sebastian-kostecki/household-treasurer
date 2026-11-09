<!-- PLAN-REVIEW-REPORT -->
# Plan Review: Category List Domain Decisions

- **Plan**: context/changes/category-list-domain-decisions/plan.md
- **Mode**: Deep
- **Date**: 2026-11-09
- **Verdict**: REVISE (after triage: SOUND — all 6 findings fixed in plan)
- **Findings**: 2 critical, 3 warnings, 1 observation

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| End-State Alignment | WARNING |
| Lean Execution | PASS |
| Architectural Fitness | PASS |
| Blind Spots | FAIL |
| Plan Completeness | WARNING |

## Grounding
Grounding: 15/15 paths ✓, 4/4 glossary phrases ✓, ADR 0063 free ✓, brief↔plan ✓, Progress↔Phase ✓, dev data claim ✗ (F1)

## Findings

### F1 — Reguła (d) archiwizuje 8 kategorii bieżącego budżetu 2026

- **Severity**: ❌ CRITICAL
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Blind Spots
- **Location**: Key Decision 4; Phase 1 Contract „Migracja” (d); weryfikacja 1.5
- **Detail**: Szkic 2027 (utworzony 2026-11-07) nie ma kategorii #40–#47 z 2026 (utworzone 2026-11-02, niezarchiwizowane, z operacjami i pozycjami Month Plan): Pensja 102 000 zł, Mieszkanie 30 000 zł, Żywność 18 000 zł, Transport, Zdrowie, Inne, Odsetki #41, Rozrywka #46. Reguła „aktywna tylko z pozycją w najnowszym budżecie, szkic też się liczy” archiwizuje je wszystkie: znikają z wyboru operacji na resztę 2026, a Budżet 2026 pomija archiwalne w sumach (`BudgetController.php:56-63`), co łamie FR-021. Weryfikacja 1.5 oczekuje, że archiwalne będą tylko „Wakacje”.
- **Fix A ⭐ Recommended**: aktywna = niezarchiwizowana pozycja w najnowszym budżecie w wykonaniu lub w najnowszym budżecie; migracja dodaje takim kategoriom pozycję w szkicu z planem 0 (krok d′)
  - Strength: Nic używanego w bieżącym roku nie wypada z selektorów.
  - Tradeoff: Szkic 2027 dostaje pozycje z planem 0 do ręcznego usunięcia.
  - Confidence: HIGH — wynika z danych na devie.
  - Blind spot: Produkcja może mieć więcej takich kategorii.
- **Fix B**: zostawić regułę, zapisać skutek wprost i poprawić przykład 1.5
  - Strength: Selektory pokazują tylko zawartość szkicu 2027.
  - Tradeoff: Wymaga F2; Pensji i Mieszkania nie wybierzesz w grudniu 2026 bez przywrócenia.
  - Confidence: MED — zależy, czy #40–#47 to dane testowe.
  - Blind spot: Nie wiadomo, czy szkic 2027 przycięto celowo.
- **Decision**: FIXED (Fix A, bez kroku d′: szkic 2027 ma dokładnie aktywne #1–#39, więc #40–#47 usunięto z niego ręcznie; migracja nie dodaje pozycji do szkiców)

### F2 — Nieokreślone, co archiwum globalne robi z budżetami lat

- **Severity**: ❌ CRITICAL
- **Impact**: 🔬 HIGH — architectural stakes; think carefully before deciding
- **Dimension**: Blind Spots
- **Location**: Phase 1 Contract „Archiwum globalne”, krok (e); Phase 2 wpis Category
- **Detail**: Dziś `archived_at` jest per budżet i czytają go sumy Budżetu i Dashboardu, sekcja archiwum Budżetu oraz propozycja Month Plan (`MonthPlanProposal.php:140`, `archived_at < pierwszy dzień miesiąca`). Plan nie mówi: (1) czy Budget Line ma własny stan archiwum (krok (e) o nim milczy), (2) czy archiwizacja kategorii zmienia sumy 2025/2026 (FR-021), (3) co się dzieje z pozycją w szkicu przy archiwizacji, (4) jaką datę archiwum dostaje kategoria zarchiwizowana przez migrację.
- **Fix**: Budget Line przenosi `archived_at` z migracji; sumy, sekcja archiwum i propozycja Month Plan czytają stan pozycji; archiwizacja kategorii listy zdejmuje jej pozycje w szkicach i oznacza pozycje w budżetach w wykonaniu datą archiwum; archiwum globalne decyduje tylko o wyborze i nowym budżecie.
  - Strength: Sumy żadnego roku nie zmieniają się przez migrację ani późniejsze archiwum.
  - Tradeoff: Dwa poziomy archiwum do wyjaśnienia w słowniku.
  - Confidence: MED — „rok zamknięty” to dziś faza budżetu; Year Close nie istnieje.
  - Blind spot: Raporty roku a kategoria archiwalna z wykonaniem 0.
- **Decision**: FIXED (Key Decision 8: archiwum listy i Budget Line; bieżący rok = budżet w wykonaniu bieżącego roku kalendarzowego)

### F3 — Zmiana typu: luźniejsza niż dziś i bez kolizji

- **Severity**: ⚠️ WARNING
- **Impact**: 🔎 MEDIUM — real tradeoff; pause to reason through it
- **Dimension**: Blind Spots
- **Location**: Key Decisions „Ustalone przez agenta” → Zmiana typu; wpis Category
- **Detail**: Dziś w wykonaniu typu nie da się zmienić (`UpdateCategoryRequest.php:74-76`). Plan blokuje tylko przy operacji lub Cash Expectation, więc kategoria z planem w 2026 bez operacji przesunęłaby sumy roku. Brak reguły dla kolizji klucza (wydatek „Zwrot” → przychód przy istniejącym przychodzie „Zwrot”) i dla grupy innego typu.
- **Fix**: blokada tym samym predykatem co usuwanie (operacja, Cash Expectation lub pozycja w budżecie w wykonaniu); zmiana na zajęty klucz odrzucana; zmiana typu zdejmuje kategorię z grupy.
  - Strength: Jeden predykat „kategoria używana”; zachowuje dzisiejszą ochronę.
  - Tradeoff: Typu nie poprawisz w kategorii z samym planem w 2026; zostaje nowa + scalenie.
  - Confidence: HIGH — reguła z kodu i ADR-0018.
  - Blind spot: Czy FR-002 zakładał swobodną zmianę typu.
- **Decision**: FIXED (predykat usuwania + kolizja + grupa; nowa weryfikacja ręczna 2.7)

### F4 — Dopisek „(n)”: niejasne, kiedy nazwa jest „zajęta”

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Key Decision 2; krok (b)
- **Detail**: „Zajęta” nie ma zakresu (ten budżet, dowolny budżet tego typu, nazwy wygenerowane), więc nr 2 z różnych lat może trafić do różnych kategorii. Nazwa 252–255 znaków + „ (2)” przekracza `max:255`.
- **Fix**: „zajęta” = klucz dowolnego oryginalnego rekordu użytkownika tego typu w dowolnym budżecie; nr n dostaje (n−1)-ty wolny numer ≥ 2, ten sam w każdym budżecie; nazwa skracana, by całość ≤ 255 znaków.
- **Decision**: FIXED

### F5 — Komendy 1.3 i 2.4 przechodzą lub padają z niewłaściwego powodu

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 1 weryfikacja 1.3; Phase 2 weryfikacja 2.4
- **Detail**: `git diff development` na gałęzi development po commicie jest pusty (1.3 zawsze przechodzi, 2.4 nic nie zwraca); przed commitem 2.4 nie widzi nieśledzonego `0063-…`.
- **Fix**: baza `8637bbb`; po commicie `git diff 8637bbb HEAD -- …` (1.3) i `git diff --name-only 8637bbb HEAD -- . ':!context/changes' ':!context/foundation/roadmap.md'` (2.4).
- **Decision**: FIXED

### F6 — „Categories of that Budget” poza listą dopisków

- **Severity**: OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Completeness
- **Location**: Phase 2 §2
- **Detail**: Month Remaining (glossary:56), Available Limit (:52) i inne wpisy mówią o kategoriach budżetu, a nie ma ich w dopiskach; niejednoznaczne, nie sprzeczne.
- **Fix**: zdanie we wpisie Budget Line: „A Category of a Budget elsewhere in this glossary means a Category with a Budget Line on that Budget.”
- **Decision**: FIXED
