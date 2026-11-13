---
change_id: balance-reconciliation
title: Uzgodnienie salda rachunku z bankiem
status: archived
created: 2026-11-13
updated: 2026-11-13
archived_at: 2026-11-13T12:21:21Z
---

## Notes

1. Słownik i ADR-0091. Uzgodnienie to zapis: rachunek, dzień, saldo z banku. Stan „zgadza się / nie zgadza się” jest liczony na żywo. Bez blokady operacji i bez operacji korygującej.
2. Czerwony test. Odczyt „saldo rachunku na dzień D” z salda otwarcia, operacji, przelewów własnych i ruchów kapitału. Dziś istnieje tylko licznik current_balance_minor_units, więc tego odczytu nie ma. Ten sam odczyt posłuży później trendowi łącznych środków.
3. Kod. Migracja nowej tabeli, akcja, okno „Uzgodnij saldo…” na „Rachunkach” z różnicą i listą operacji od ostatniego uzgodnienia.
4. Koszt uboczny. Nowa tabela musi wejść do snapshotu użytkownika (wersja 12 → 13) i do user:restore.
