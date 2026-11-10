# Próba migracji S-24 na kopii produkcji

**Stan: DO UZUPEŁNIENIA.** Próba z punktu 6.6 planu została potwierdzona bez zapisu (commit `7cbec3b`). Ten plik zawiera procedurę i pola na wyniki; pola z „…” czekają na przebieg na świeżym archiwum z VPS (ustalenie F1 z `impl-review.md`). Bez danych osobowych i bez kwot pojedynczych operacji.

Lokalne `storage/app/backups/prod-backup.zip` (2026-09-11) się nie nadaje: jest sprzed kolumny `month_item_kind` (2026-11-02), więc nie pokaże pozycji jednorazowa + rachunek.

## Przebieg

- **Data:** …
- **Kod:** … @ `…` (gałąź z S-24; stack Sail serwuje główny checkout, więc próba biegnie tam, gdzie ta gałąź jest wybrana)
- **Archiwum:** `….zip` (UTC), … B, zrobione na VPS przez `scripts/artisan.sh backup:run --disable-notifications`
- **Baza:** osobna `restore_trial` (README, „Próba lokalna”, kroki 1–5; `db:show` musi pokazać `restore_trial`)

| Krok | Komenda | Oczekiwany wynik | Wynik |
| --- | --- | --- | --- |
| 1. Restore archiwum | README „Próba lokalna” 1–5 | stary schemat, 3 migracje S-24 `Pending` (`2026_11_10_120000`, `…120001`, `…120002`) | … |
| 2. „Do wydania” przed | plan miesiąca każdego zatwierdzonego miesiąca, kod sprzed S-24 (`development` @ `eb2f66b`) na `restore_trial` | wartości zapisane w tabeli niżej | … |
| 3. Liczby kontrolne przed | `docker compose exec -e DB_DATABASE=restore_trial laravel.test php artisan app:control-numbers --output=storage/app/restore-trial/before.json` | plik zapisany; `meta.one_off_bill_lines` = liczba, `meta.category_rhythm_rows` = `null` | … |
| 4. Migracja | `docker compose exec -e DB_DATABASE=restore_trial laravel.test php artisan migrate --force` | dokładnie 3 migracje S-24 | … |
| 5. Porównanie po migracji | `… app:control-numbers --compare=storage/app/restore-trial/before.json` | „Control numbers match: no differences.”, kod 0 | … |
| 6. Liczby po | `… app:control-numbers --output=storage/app/restore-trial/after.json` | `meta.one_off_bill_lines` = `null`, `meta.category_rhythm_rows` = liczba | … |
| 7. „Do wydania” po | plan miesiąca tych samych miesięcy, kod S-24 | takie same co do grosza jak w kroku 2 | … |
| 8. Przejście przez aplikację | „Kategorie” (rytm każdej kategorii), plan bieżącego i następnego miesiąca, Budżet, Dashboard, Operacje, podgląd Importu | bez uwag | … |
| 9. Wycofanie | `… php artisan migrate:rollback --force` | dokładnie 3 migracje S-24; `budget_lines` znów ma `is_paced` i `month_item_kind`, tabeli `category_rhythms` nie ma | … |
| 10. Porównanie po wycofaniu | `… app:control-numbers --compare=storage/app/restore-trial/before.json` | bez różnic | … |
| 11. Flagi po wycofaniu | porównanie `is_paced` i `month_item_kind` każdej pozycji z archiwum | różnice tylko tam, gdzie ADR-0072 nazywa odtworzenie stratnym (jednorazowa + rachunek wraca jako równomierna + rachunek) | … |
| 12. Ponowna migracja i porównanie | kroki 4–5 | bez różnic | … |
| 13. Sprzątanie | README „Próba lokalna” 7 | `restore_trial` usunięta, lokalne `storage/app` odtworzone | … |

## Liczby

| Pomiar | Wartość |
| --- | --- |
| `meta.one_off_bill_lines` przed migracją (pozycje jednorazowa + rachunek, ADR-0066) | … |
| `meta.category_rhythm_rows` po migracji | … |
| kategorie listy: wydatki / przychody | … / … |
| wpisy rytmu: stała opłata / bieżąca / równomierna roczna | … / … / … |
| kategorie z więcej niż jednym wpisem (rytm różny między 2026 a szkicem 2027) | … |
| wydatki bez pozycji budżetu (bieżąca od miesiąca migracji) | … |
| zatwierdzone miesiące (`month_plans`) | … |

## „Do wydania” zatwierdzonych miesięcy

| Budżet / miesiąc | Przed (kod sprzed S-24) | Po migracji (kod S-24) | Po wycofaniu i ponownej migracji |
| --- | --- | --- | --- |
| … | … | … | … |

## Pozycje jednorazowa + rachunek

Jedyne dane, których zachowanie zmienia S-24 (stają się stałą opłatą: wchodzą do puli „Pozostało w miesiącu”, tracą powiadomienie ponad limitem roku, `down()` odtwarza je jako równomierna + rachunek). Jeśli `meta.one_off_bill_lines` > 0, wypisz nazwy kategorii i decyzję.

- …

## Uwagi

- …
