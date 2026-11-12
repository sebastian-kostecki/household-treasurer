# Próba restore na użytkownika z danymi (F-06)

Data: 2026-11-12. Kod: gałąź `worktree-restore-import-identity-accounts`, HEAD `0fa6d21` (poprawka z fazy 2, `f852cf8`). PostgreSQL 18.6, obraz `sail-8.5/app`.

## Odstępstwa od planu

- **Snapshot nie pochodzi z produkcji.** Zrobiłem go przez `user:backup 1` z lokalnej bazy deweloperskiej (kopia danych użytkownika, plik poza repozytorium). Ma 1033 operacje (1031 z digestem), 46 przelewów (po 46 digestów w nodze source i destination), 5 ruchów kapitału (5 digestów), 9 kont. Rozmiar i kształt są zbliżone do produkcji, ale próby na prawdziwym snapshocie produkcji to nie zastępuje.
- **Bez serwera `php -S` i bez zmiany `.env`.** Wszystkie polecenia szły przez `docker run … --entrypoint php sail-8.5/app` z `-e DB_DATABASE=preview_restore_import_identity_accounts`. `db:show` pokazał tę bazę przed pierwszym zapisem. Nie było cache'u konfiguracji.
- **Krok 5 bez UI.** Zamiast podglądu importu w przeglądarce wywołałem `ImportDuplicateDetector::annotateRows()` (ten sam kod co podgląd) na wierszach z najnowszego miesiąca snapshotu. Nie zatwierdzałem żadnego importu.

## Przebieg

1. `migrate --force` na pustej bazie próby, użytkownik próby, `user:restore` snapshotu, `app:control-numbers --output` („przed”).
2. Zmiana danych: opis jednej operacji i nazwa jednego konta.
3. `user:restore` tego samego snapshotu na tego samego użytkownika (z danymi): przeszedł. Zmiana z kroku 2 została cofnięta.
4. Porównanie z „przed”: `--compare` pokazuje różnice wyłącznie w kluczach zależnych od id (każdy restore nadaje nowe id). Po odrzuceniu pól `*_id` wszystkie sekcje liczb kontrolnych są identyczne: `meta`, `row_counts`, `budgets`, `lines`, `own_transfers_total`, `capital_movements_by_direction`, `accounts`, `term_deposits`.
5. Tożsamość importu po restore:

| Noga | rekordy z digestem | wskazuje konto spoza użytkownika |
| --- | --- | --- |
| `financial_operations` | 1031 (snapshot: 1031) | 0 |
| `own_transfers.source_*` | 46 (46) | 0 |
| `own_transfers.destination_*` | 46 (46) | 0 |
| `capital_movements` | 5 (5) | 0 |

6. Zapisany digest jednej operacji równa się `ImportIdentityDigest::compute()` z id użytkownika, nowym id konta i danymi tożsamości.
7. Wykrywanie duplikatów: wszystkie 22 wiersze z najnowszego miesiąca snapshotu (2026-11) są oznaczone `import_duplicate = true`.
8. Sprzątanie: baza `preview_restore_import_identity_accounts` usunięta, `.env` worktree bez zmian, baza deweloperska nietknięta (1047 operacji przed i po). Jedyny zapis poza bazą próby to plik snapshotu `storage/app/private/user-snapshots/1-20261112070312.json` w głównym checkoucie (ignorowany przez git).

## Uwaga z przebiegu

Pierwsze uruchomienie kroku 7 dało 0 duplikatów, bo mój skrypt przekazał datę jako `(string) CarbonImmutable` (`2026-07-09 00:00:00`) zamiast `Y-m-d`. To błąd skryptu próby, nie restore: zapisany digest zgadzał się z przeliczonym. Potwierdza to zasadę z ADR-0079, że format daty wchodzi do hasha.
