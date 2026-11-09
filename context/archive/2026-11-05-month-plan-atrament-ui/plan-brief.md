# Plan miesiąca w systemie „C · Atrament i morski” — Plan Brief

> Full plan: `context/changes/month-plan-atrament-ui/plan.md`
> Research: `context/changes/month-plan-atrament-ui/research.md`

## What & Why

Widok `/month-plan` przechodzi na system „C · Atrament i morski” według mockupu `MockupCAtrament` → Plan miesiąca. Dziś wygląda jak stara aplikacja obok przestylowanego Budżetu i Operacji: kwoty nie są w mono, „zł” powtarza się w każdej komórce, a ekran odzwierciedla kolejność dodawania funkcji (karta Do wydania, tempo, karta odkładania, dwie tabele) zamiast hierarchii miary → ostrzeżenie → tabela.

## Starting Point

Skan literałów kolorów jest czysty, ale widok ma ok. 50 stylów sprzed Atramentu (`text-3xl`, `uppercase tracking-wider`, `rounded-2xl`, `border-border/60`, `opacity-60`, `.section-label`), których nie łapie żadna straż. Para To Spend zwraca tylko wynik netto, żaden props nie niesie stanu 12 miesięcy, a kitchen sink `/dev/month-plan` nie renderuje większości regionów widoku (zarzuty C1–C7 w researchu).

## Desired End State

Tytuł `page-title` z odznaką stanu (`brand` „Zatwierdzony”, `warning` „Niezatwierdzony”, „Końcowy”) i jedną akcją. Pasek miesięcy z kropką stanu przy każdym miesiącu. StatPanel z Do wydania, jego składnikami Dochody i Wydatki oraz Tempem, a pod nim notatka, gdy tempo wyprzedza dni. Jedna tabela z kolumną Status, wierszem „Odkładam / dobieram”, kwotami mono i stopką „Kwoty w zł”. W trybie zmiany pas `primary` przyklejony u dołu z „Zapisz zmiany” w kolorze `brand`. Miniony miesiąc ma ten sam układ do odczytu z panelem podsumowania. Sink pokazuje wszystko w macierzy 7 stanów.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Miary Dochody / Wydatki | Składniki Do wydania (rozbicie w parze PHP/TS, nowe propsy) | Tylko to daje prawdziwe równanie „dochody − wydatki − odkładanie”; reguła z `CONTEXT.md:80` bez zmian | Research D1 / Plan |
| Układ tabel | Jedna tabela z wierszami grup, odkładanie jako wiersz z `SegmentedControl` | Układ mockupu bez utraty kierunku, walidacji i ostrzeżenia o dobieraniu | Research D4 / Plan |
| Pas edycji | Pas `primary` przyklejony u dołu tylko w trybie zmiany; „Zatwierdź” w wierszu tytułu | Wygląd mockupu, a „Zapisz” zostaje w zasięgu przy długiej tabeli | Research D3 / Plan |
| Kolor `brand` | Badge `brand` „Zatwierdzony” i nowy wariant Button `brand` dla „Zatwierdź” / „Zapisz zmiany”, ADR-0042 | Stan dokumentu jak faza „Realizacja” Budżetu; przycisk czytelny na pasie `primary` | Research D2 / Plan |
| Kolumna Status | Wpłynęło/czeka, Zapłacone/do zapłaty, ponad plan; bez „N% planu” | Wszystkie dane już są w wierszu, zero nowej logiki | Plan |
| Kolejność statusów | „ponad plan” wygrywa z „Zapłacone” | `CONTEXT.md:80` traktuje każdą pozycję wydatkową ponad planem jako przekroczenie | Plan |
| Miniony miesiąc | Pełny widok do odczytu (miary, podsumowanie, tabela), odznaka „Końcowy” | Mockup pokazuje samo podsumowanie, ale to odebrałoby widok kategorii | Plan |
| Notatka tempa i dobierania | Nowy wariant Alert `note` (tło `warning-soft`) | Jeden prymityw ostrzeżeń, reguła „użyj Alert” zostaje | Plan |
| Kropki stanu miesięcy | Nowy props `month_states` z `MonthPlanProposal`, bez nowych zapytań | Pasek mówi, które miesiące mają plan; koszt to pętla w pamięci | Research D5 / Plan |
| `MonthSummaryCard` | Przebudowa na siatkę tabeli z „Kwoty w zł” | Trzeci, niezgodny wygląd tabeli (zarzut C6) | Research |
| `.section-label` | Usunąć klasę i regułę | Utrwala wersaliki jako nagłówek panelu (zarzut C5) | Research |
| Straż stylów | `MonthPlanHardcodedUiTest` odrzuca style sprzed Atramentu; pliki widoku w `check-ui-literals.mjs` | Dzisiejsze straże przepuściły starą typografię | Plan |
| „Dodaj operację” | Odroczone | Wciąga dialogi operacji do innego widoku | Research D6 |

## Scope

**In scope:**
- ADR-0042 i reguły (`js.md`, `design-system.md`)
- Warianty Button `brand` i Alert `note`, nowe klasy w `app.css`
- Rozbicie To Spend w parze PHP/TS z testami, propsy składników i `month_states`
- Przebudowa `MonthPlan.vue`; nowe `MonthPlanStatPanel` i `MonthPlanTable`; zmiany w `MonthPlanItemRow`, `BudgetMonthPicker`, `MonthPlanEditBar`, `MonthSummaryCard`; usunięcie `MonthPaceMeter`
- Kitchen sink z macierzą 7 stanów, rozszerzone straże, zrzuty

**Out of scope:**
- „Dodaj operację” w wierszu tytułu, status „N% planu”, osobny komponent Notice
- Zmiana reguły To Spend lub progu tempa
- Pas edycji u góry (mockup) i zmiany innych widoków poza addytywnymi wariantami

## Architecture / Approach

Decyzje (ADR-0042) idą przed kodem. Potem addytywna warstwa wspólna bez konsumentów. Następnie logika i kontrakt propsów po obu stronach w jednym commicie: `ToSpendCalculator::breakdown` i `toSpendBreakdown` w TS, z których wynik netto jest wyprowadzony, a do tego `month_states` w `monthPlan`. Dopiero na tym widok: stan edycji zostaje w `MonthPlan.vue`, `<Form>` obejmuje cały widok (dzięki temu „Zatwierdź” w wierszu tytułu ma `processing`), a wydzielone `MonthPlanStatPanel` i `MonthPlanTable` dostają odczyty przez propsy i oddają zmiany zdarzeniami. Na koniec sink renderuje te same komponenty z fikstur.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzje i dokumenty | ADR-0042, reguły `brand` i Alert, bez `.section-label` | Użytkownik zmieni regułę `brand` |
| 2. Warstwa wspólna | Button `brand`, Alert `note`, klasy `cell-input`, pas, kropki, siatka podsumowania | Kontrast `brand` / pasa w ciemnym motywie |
| 3. Logika i kontrakt propsów | Rozbicie To Spend (PHP+TS+testy), propsy składników i `month_states` | Rozjazd kopii PHP i TS; konsumenci ostrzeżeń |
| 4. Widok Planu miesiąca | Nowy układ, Status, odkładanie w tabeli, pas, podsumowanie, straż stylów | Regres zachowania formularza (walidacja, ukryte pola, auto-otwarcie jednorazowych) |
| 5. Stany i bramka wizualna | Sink z macierzą 7 stanów, reguła sinka, zrzuty | Stany zależne od nawigacji (loading paska) bez propsa |

**Prerequisites:** Sail z PostgreSQL uruchomiony; worktree po krokach „Fresh checkout” z AGENTS.md; osobna baza i podgląd na :8090 do zrzutów i kontroli ręcznych.
**Estimated effort:** ~3–4 sesje w 5 fazach (największa Faza 4).

## Open Risks & Assumptions

- Siatka 6 kolumn z Rodzajem (kontrolka) i Statusem musi zmieścić się na 390 px bez poziomego przewijania; plan zakłada układ przez `grid-template-areas` jak w Budżecie.
- Stan `loading` paska miesięcy może nie dać się pokazać w sinku bez propsa tylko dla sinka; wtedy komórka dostaje „N/A” z powodem.
- Rozszerzona straż stylów może trafić w `SegmentedControl` lub `MonthPlanKindCell` (współdzielone); wtedy trzeba je oczyścić w Fazie 4.

## Success Criteria (Summary)

- Plan miesiąca wygląda jak mockup w obu motywach przy 1280 i 390 px, a zatwierdzanie, zmiana planu, odkładanie i zmiana rodzaju działają jak przedtem.
- Dochody − Wydatki − odkładanie w panelu zawsze równa się Do wydania, także w podglądzie na żywo.
- Sink pokazuje każdy stan widoku, a CI odrzuca powrót stylów sprzed Atramentu.
