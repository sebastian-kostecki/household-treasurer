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

## Produkcja po porządkach (2026-11-12)

- **Data odczytu:** 2026-11-12, zaraz po wdrożeniu `1976a57` (S-20–S-31, F-06, S-23) przez `scripts/deploy.sh`.
- **Polecenie:** `scripts/artisan.sh import:suggestion-accuracy 1 --output=storage/app/private/suggestion-accuracy/2026-11-12-production.json` → kod wyjścia 0; plik JSON tylko na serwerze.
- **Dane:** użytkownik 1, rok 2026, 1268 zaimportowanych wierszy (438 w okresie sierpień–listopad), czyli o 259 więcej niż kopia z 11.09.
- **Scalenia przed odczytem:**
  - „Eksploatacja” → „Utrzymanie domu”: wykonane (na produkcji nie ma już kategorii „Eksploatacja”).
  - „Jedzenie poza domem” → „Restauracje”: **jeszcze nie** (obie kategorie aktywne).
- **Liczby kontrolne:** `app:control-numbers` przed odczytem i `--compare` po nim → „Control numbers match: no differences.” Deploy osobno porównał je przed i po migracjach, też bez różnic.

| Okres | Poziom | Wiersze | Z podpowiedzią | Trafne wśród podpowiedzianych | Top 3 |
| --- | --- | --- | --- | --- | --- |
| cały rok | model | 1268 | 77,8% (987/1268) | 92,9% (917/987) | 73,8% (936/1268) |
| cały rok | suggestion | 1268 | 77,7% (985/1268) | 92,9% (915/985) | - |
| sierpień–listopad | model | 438 | 80,1% (351/438) | 92,9% (326/351) | 76,5% (335/438) |
| sierpień–listopad | suggestion | 438 | 80,1% (351/438) | 92,9% (326/351) | - |

**Odczyt względem fazy 3 (informacyjnie, bez progu):** pokrycie wzrosło (cały rok 76,9% → 77,7%, sierpień–listopad 79,3% → 80,1%). Trafność cały rok 93,6% → 92,9% (−0,7 pkt), sierpień–listopad 96,5% → 92,9% (−3,6 pkt). To nie są te same wiersze: okres sierpień–listopad ma teraz 438 wierszy zamiast 179, w większości importy z września–listopada, których kopia z 11.09 nie miała. „Nie gorzej” rozstrzyga faza 3 (te same dane, 0 różniących się wierszy). Spadek w sierpniu–listopadzie to sygnał do obejrzenia po scaleniu „Jedzenie poza domem” → „Restauracje”, nie blokada.
