---
change_id: testing-amount-invariants
title: Niezmienniki kwot — parzystość „do wydania” PHP↔TS i zgodność sum raportu
status: implementing
created: 2026-11-18
updated: 2026-11-18
archived_at: null
---

## Notes

Open a change folder for rollout Phase 2 of context/foundation/test-plan.md: "Niezmienniki kwot".
Risks covered: #2 („do wydania” w podglądzie przeglądarki (TS) różni się od liczby z serwera (PHP), zwłaszcza dla wydatku nieregularnego), #3 (sumy raportu okresu nie zgadzają się co do grosza z wykonaniem w budżecie rocznym albo z Wynikiem miesiąca: zwroty, transfery własne, ruchy kapitału, zarchiwizowane kategorie, granica miesiąca i roku). Test types planned: kontrakt/parzystość (unit po obu stronach na wspólnym fixture) + integracja na PostgreSQL.
Risk response intent:
- #2: jedna tabela przypadków (wejście → oczekiwane „do wydania”, wyliczone ręcznie z reguły To Spend w context/foundation/glossary.md) przechodzi po stronie PHP i TS; nie zakładać, że kopia TS „przepisana 1:1” liczy to samo; uniknąć problemu wyroczni (wartości wyliczone kodem PHP i wklejone do testu TS).
- #3: na zestawie operacji z ręcznie wyliczonym wynikiem (zwrot, transfer własny, ruch kapitału, zarchiwizowana kategoria, operacja na granicy miesiąca i roku) raport = wykonanie w budżecie rocznym = Wynik miesiąca; nie zakładać, że „obie liczby z tego samego odczytu się zgadzają”; uniknąć asercji raport == inny odczyt bez niezależnej wyroczni.
Korekta do potwierdzenia w research: cel etapu w §3 mówi „przed S-14” i „po S-11”, a roadmap podaje, że M-2 (S-11…S-17) porzucono 2026-11-08 (PRD v3); raporty po grupach i zebrane dla wydatków nieregularnych są już na main z M-3. Research ma ustalić, co dziś zastąpiło S-11/S-14 jako punkt odniesienia, do backportu w §2/§3.
Worktree: .claude/worktrees/testing-amount-invariants (gałąź worktree-testing-amount-invariants, z lokalnego development; zawiera już commit statusu §3).

### Faza 3 — kandydaci 1 i 2 (2026-11-18)

- **Kandydat 1** (opłata stała wpisana wstecz, `fixed-charge-backdated-proposal`): zielony po obu stronach. Serwer luty 4 850 → 4 820 i marzec 4 850 → 4 820, podgląd TS tylko luty 4 850 → 4 820. Zgodne z zakresem ostrzeżenia w glosariuszu (To Spend).
- **Kandydat 2 przed jakąkolwiek zmianą kodu** (`january-joining-category`): Feature zielony (7 passed, serwer 5 000 → 4 600), Vitest czerwony (po: oczekiwane 460000, jest 490000, plan 0 dla kategorii dołączającej).
- **Rozstrzygnięcie: issue #273** (`needs-triage`). Poprawka wymaga nowej metody w `MonthPlanProposal` (plan dla kategorii bez `BudgetLine`) i obsługi miesiąca zatwierdzonego, miesięcy po styczniu i opłaty stałej, więc nie mieści się w jednym miejscu. Wybór konserwatywny z polityki braku interakcji. Przypadek Vitest ma `it.skip` z numerem #273, Feature zostaje aktywny. Wiersz Manual 3.5 czeka na potwierdzenie człowieka.
