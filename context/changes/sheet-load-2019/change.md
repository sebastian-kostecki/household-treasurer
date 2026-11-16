---
change_id: sheet-load-2019
title: Sheet load 2019
status: implementing
created: 2026-11-16
updated: 2026-11-16
archived_at: null
---

## Notes

### Decyzje użytkownika (2026-11-16, po research.md, przed planem)

1. **Prawda 2019:** poprawić `Podsumowanie` u źródła, jak plik 2021 (ADR-0096 decyzja 3): formuły `H3:H14` liczą wykonanie (C w I–VI, D w VII–XII), zapis w `workbook-fix.md` z SHA-256 przed i po oraz kopią oryginału. Kod dalej porównuje operacje + ruchy funduszu z komórkami `Podsumowanie!H16`/`I16` („Analiza jest prawdą” bez wyjątku w kodzie).
2. **Cele:** ~~ruchy funduszu~~ — zmienione przez użytkownika w trakcie /10x-plan („nie bierz pod uwagę ruchów funduszy jako wydatki”): „cele: Sebastian” (I–VI) i „Cele” (VII–XII), 6 144,80 zł, nie są wydatkami ani ruchem funduszu → pominięte (SKIP); poprawione `H` ich nie obejmuje (spodziewane `H16` = 67 286,75 = operacje). „Oszczędności (10%)” i „Oszczędności” jako wydatek → pominięte (SKIP).
3. **Marcowe „inne” 125 zł** (`marzec_19!B7`) to przychód: poprawka obejmuje je w `marzec_19!B3` / `Podsumowanie!I5`; spodziewane `I16` = 78 039,29. „z poprzedniego” (VI, 699,00) i „oszczędności” (VII, 5 420,59) → pominięte.
4. **Szczegółowość:** listy „Wydatki osobiste” (Sebastian, Jana) i „Dom i akcesoria” rozbite na pozycje nazwane z arkusza, w kategorii swojego wiersza (osobiste → 29, dom → 37); różnica lista/wiersz jako „reszta bez rozpisania” (znane: `marzec_19!G14` 10,00, `lipiec_19!D21` fryzjer 35,00). Pozycje przenoszone później przez „Zmień kategorię” (ADR-0089).
5. **Siłownia** → 49 „Sport” (odczyt z produkcji 2026-11-16, `production-categories.md`).
6. **Stypendium** → nowa kategoria przychodów „Stypendium”, tylko dla 2019, tworzona przez wczytanie jako zarchiwizowana (jak dawna „Kosmetyki”), nie dołącza do szkicu, usuwana przez `budget:unload-sheet`. Wymaga uogólnienia ścieżki NEW_CATEGORY (dziś jedna, sztywna „Kosmetyki”, `mapping.php` `new_category`).
7. **Mapowanie pozostałych etykiet:** prąd+gaz → 15; samochód: inne → 17; cinema city, rozrywka: inne, rozrywka - inne, wyjścia → 23; lekarze, zdrowie i uroda: inne → 20; apteka i kosmetyki → 12; akcesoria do domu → 37; osobiste (+ jana/sebastian) → 29; przychód inne → 7. Wpisy 2020–2025 bez zmian.
8. **Rachunki (prąd+telefon+internet)** (VII–XII, ok. 1 372 zł) rozbić na 15 Prąd, 14 Telefony, 44 Internet „według tego, jak jest teraz, orientacyjnie” — do ustalenia w planie, np. według bloku „Rachunki” (`K2`/`M2`) danego miesiąca, z różnicą do `D10`.
9. **Pozycje „do oddania”** („do oddania od U.” 180, „Urszula” 100, „oddać (alb+apt+l)” 850) wczytać jak są (29), ewentualne korekty po wczytaniu.
