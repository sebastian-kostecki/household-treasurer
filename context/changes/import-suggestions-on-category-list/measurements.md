# Pomiar trafności podpowiedzi Importu (S-23, FR-020)

## Faza 3: przed/po na kopii z 11.09

- **Data pomiaru:** 2026-11-12.
- **Źródło danych:** `storage/app/backups/prod-backup.zip` z 11.09.2026 (stary schemat), przywrócone do osobnej bazy `suggestion_trial`; użytkownik 1, rok 2026, 1009 zaimportowanych wierszy (179 w okresie sierpień–listopad).
- **Zakres dowodu:** na tych danych każda aktywna kategoria ma pozycję w 2026, więc pomiar porównuje głos po nazwie z głosem po id i celem z listy kategorii; regułę „cel bez pozycji” z fazy 1 dowodzą testy, nie ten pomiar.

### Przed (`bbf49ba^`, `measure/before.php`, głos po nazwie, stary cel)

| Okres | Poziom | Wiersze | Z podpowiedzią | Trafne wśród podpowiedzianych | Top 3 |
| --- | --- | --- | --- | --- | --- |
| cały rok | model | 1009 | 77,1% (778/1009) | 93,6% (728/778) | 73,5% (742/1009) |
| cały rok | suggestion | 1009 | 76,9% (776/1009) | 93,6% (726/776) | - |
| sierpień–listopad | model | 179 | 79,3% (142/179) | 96,5% (137/142) | 78,8% (141/179) |
| sierpień–listopad | suggestion | 179 | 79,3% (142/179) | 96,5% (137/142) | - |

### Po (ta gałąź, `import:suggestion-accuracy`, głos po id, cel z `CategoryChoiceOptions`)

| Okres | Poziom | Wiersze | Z podpowiedzią | Trafne wśród podpowiedzianych | Top 3 |
| --- | --- | --- | --- | --- | --- |
| cały rok | model | 1009 | 77,1% (778/1009) | 93,6% (728/778) | 73,5% (742/1009) |
| cały rok | suggestion | 1009 | 76,9% (776/1009) | 93,6% (726/776) | - |
| sierpień–listopad | model | 179 | 79,3% (142/179) | 96,5% (137/142) | 78,8% (141/179) |
| sierpień–listopad | suggestion | 179 | 79,3% (142/179) | 96,5% (137/142) | - |

### Wynik `--compare`

`import:suggestion-accuracy 1 --year=2026 --output=…-after.json --compare=…-before.json` → **kod wyjścia 0**: „No drop and no differing row.”

- Zbiory `operation_id` w obu plikach są równe (inaczej komenda zgłosiłaby błąd danych).
- Wierszy różniących się: **0** (żaden wiersz nie zmienił flag `suggested` / `correct` / `top3` na żadnym poziomie).
- Wynik „przed” zgadza się z punktem odniesienia z `import-category-suggestions` (77,1% / 93,6% oraz 79,3% / 96,5%).

Pliki JSON (`2026-09-11-before.json`, `2026-09-11-after.json`) leżą w `storage/app/private/suggestion-accuracy/` (poza gitem, bez opisów).

## Faza 4: odczyt na produkcji po porządkach

_Do uzupełnienia po scaleniach par kategorii przez S-21._
