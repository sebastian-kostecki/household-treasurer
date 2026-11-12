# Restore snapshotu na istniejącego użytkownika: tożsamość importu — Plan Brief

> Full plan: `context/changes/restore-import-identity-accounts/plan.md`

## What & Why

`user:restore` na użytkownika, który ma już dane, pada na kluczu obcym (SQLSTATE 23503), bo id konta w tożsamości importu operacji i obu nóg przelewu idą do bazy surowo ze snapshotu. Wykryła to próba S-21 na kopii produkcji (O1): snapshot produkcji ma tę tożsamość w 1269 z 1280 operacji. To jedyna droga powrotu po scaleniu par kategorii, a `deploy.sh` też robi snapshoty, więc poprawka musi wejść przed wdrożeniem.

## Starting Point

`RestoreUserData` zawsze tworzy konta od nowa z nowymi id. Mapuje `account_id` wszędzie, a `import_identity_account_id` tylko w ruchach kapitału. Digest tożsamości to `sha256(user_id | account_id | data | kwota | surowy opis)` i kopiuje się bez zmian, więc po restore nie pasuje do żadnego nowo liczonego. Istniejący test restore przechodzi tylko dlatego, że konta użytkownika źródłowego nadal istnieją.

## Desired End State

Restore snapshotu na użytkownika z danymi, także na niego samego, przechodzi na PostgreSQL. Tożsamość importu wskazuje przywrócone konta, a ponowny import tego samego wyciągu oznacza wiersze jako duplikaty. Snapshot z rekordem z importu, któremu brakuje danych do przeliczenia, jest odrzucany w całości, a dane użytkownika zostają bez zmian.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Zakres pól | 3 niemapowane pola + digest wszystkich czterech nóg (operacja, source, destination, ruch kapitału) | Przegląd kluczy obcych pokazał, że inne tabele snapshotu nie mają id konta (niewiadoma z roadmapy zamknięta). | Plan |
| Digest | Mapuj id konta i licz digest od nowa z użytkownika docelowego | Sama naprawa klucza obcego po cichu wyłączyłaby wykrywanie duplikatów dla ~1269 operacji. | Plan |
| Brak wejścia albo nieznane konto | Odrzuć cały snapshot (`UserSnapshotException`, transakcja wycofana) | Tak samo restore odrzuca dziś nieznane konto ruchu kapitału, a błędna tożsamość nie wchodzi po cichu. | Plan |
| Noga bez digestu | Tylko mapowanie id konta, bez nowego digestu; nieznane konto to też odmowa | Brak digestu oznacza rekord spoza importu (istniejący test brakujących kluczy), ale surowe id obcego konta nie może wejść do bazy. | Plan |
| Dokumentacja | ADR-0079 + zdanie w glosariuszu „Import duplicate” + sekcja w `.ai/rules/snapshot.md` | Doprecyzowuje „niezmienny digest” z ADR-0007, a AGENTS.md wymaga ADR przed kodem. | Plan |
| Miejsce logiki | Nowa `app/Support/UserSnapshot/RestoredImportIdentity` | `RestoreUserData` jest już w baseline Sloppy jako duża klasa, a reguła powtarza się w trzech miejscach. | Plan |
| Format snapshotu | Bez zmian (wersja 10) | Wszystkie wejścia digestu już są w pliku. | Plan |

## Scope

**In scope:**
- ADR-0079, glosariusz, reguła snapshot
- Testy regresji na PostgreSQL (czerwone przed poprawką), w tym duplikat w podglądzie importu po restore i 20 przypadków odmowy (16 z digestem, 4 bez digestu z nieznanym kontem)
- Poprawka restore operacji, przelewów i ruchów kapitału
- README „Scalanie kategorii” i próba na kopii produkcji (powtórzenie kroku 6a z S-21)

**Out of scope:**
- Nowa wersja snapshotu i zmiany w `BackupUserData`
- Weryfikacja starego digestu przed przeliczeniem
- Zmiany w imporcie i w wykrywaniu duplikatów
- Wdrożenie, `user:backup` i scalenie par na produkcji (kroki użytkownika potem)
- `backup:restore` całej instancji

## Architecture / Approach

`RestoreUserData` po `restoreAccounts()` ma mapę id kont. Dla każdej nogi tożsamości (operacja, source, destination, ruch kapitału) woła `RestoredImportIdentity::forLeg()`. Bez digestu klasa tylko mapuje konto. Z digestem wymaga konta z mapy, daty, kwoty i surowego opisu, a potem liczy `ImportIdentityDigest::compute(user docelowy, nowe konto, data, kwota, opis)`. W pozostałych przypadkach rzuca `UserSnapshotException` z nazwą rekordu. Datę przekazuje jako ciąg `Y-m-d` ze snapshotu.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Decyzja w dokumentach | ADR-0079, glosariusz, reguła snapshot | Numer ADR zajęty przez równoległą zmianę |
| 2. Czerwony test → poprawka restore | Restore na użytkownika z danymi przechodzi, duplikaty wykrywane, odmowy | Inny format daty niż przy imporcie daje inny digest |
| 3. README i próba na kopii produkcji | Opisana droga powrotu i dowód na snapshocie produkcji | Rekord produkcji bez wejść blokuje restore (wtedy wraca decyzja) |

**Prerequisites:** snapshot produkcji (plik poza repozytorium) dla fazy 3. Sail i sieć `household-treasurer_sail` dla testów PostgreSQL na własnej bazie `testing_restore_import_identity_accounts`; próba w `preview_restore_import_identity_accounts`.
**Estimated effort:** ~1–2 sesje w 3 fazach.

## Open Risks & Assumptions

- Zakładam, że zapisany surowy opis równa się wejściu digestu: pole jest `prohibited` przy edycji, a wszystkie ścieżki importu zapisują ten sam ciąg. Jeśli próba wykaże inaczej, digesty mimo to będą spójne z nowymi importami, bo liczone są z tego samego zapisanego opisu.
- Gdyby na produkcji był rekord z digestem bez surowego opisu, restore odmówi. Faza 3 to sprawdza, zanim restore będzie potrzebny naprawdę.

## Success Criteria (Summary)

- Po scaleniu par na produkcji `user:restore` snapshotu sprzed scalenia przywraca dane bez błędu klucza obcego.
- Po takim powrocie ponowny import wyciągu nie dubluje operacji, bo wiersze są oznaczone jako duplikaty.
- Uszkodzony snapshot jest odrzucany w całości, a dane użytkownika nie zmieniają się częściowo.
