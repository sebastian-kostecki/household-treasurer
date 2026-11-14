# Poprawka `Budżet_2021.xlsx` (2026-11-13)

Plik leży poza repo: `storage/app/private/Budżet_2021.xlsx` w głównym checkoucie. Oryginał zachowany obok jako `Budżet_2021.przed-korekta.xlsx` (przywrócenie: skopiować go z powrotem).

## SHA-256

| Plik | SHA-256 |
| --- | --- |
| `Budżet_2021.przed-korekta.xlsx` (oryginał, identyczny z plikiem przed edycją) | `9396b6b6fa57c989be58c1f05cb8c27cce8d50c7e5d63916a996a5c86d8fc872` |
| `Budżet_2021.xlsx` (po poprawce) | `6c228b92db7931e60309cb1caac7987de480051638d548859ec38213393c0e18` |

## Błąd

Od lipca `E30` (suma wydatków miesiąca) miało formułę `SUM(D3:D20)+SUM(D24:D29)` i pomijało `D23` „Rozrywka i wyjścia” (styczeń–czerwiec: `SUM(D3:D20)+SUM(D23:D29)`). `Analiza!C16` była o 537,99 zł za niska (89 673,56 zamiast 90 211,55 = suma wierszy arkusza).

## Zmienione komórki (14)

| Komórka | Formuła | Zapisana wartość przed → po |
| --- | --- | --- |
| `lipiec!E30` | `SUM(D3:D20)+SUM(D24:D29)` → `SUM(D3:D20)+SUM(D23:D29)` | 7 010,86 → 7 163,35 |
| `sierpień!E30` | jw. | 5 862,20 → 5 910,10 |
| `wrzesień!E30` | jw. | 6 369,54 → 6 369,54 (`D23` = 0) |
| `październik!E30` | jw. | 8 079,56 → 8 176,46 |
| `listopad!E30` | jw. | 7 463,66 → 7 592,46 |
| `grudzień!E30` | jw. | 7 657,90 → 7 769,80 |
| `lipiec!D31` (notatka `…-sierpień!E30…`) | bez zmiany | −3 148,45 → −3 196,35 |
| `Analiza!C9` | bez zmiany (`=lipiec!E30`) | 7 010,86 → 7 163,35 |
| `Analiza!C10` | bez zmiany | 5 862,20 → 5 910,10 |
| `Analiza!C12` | bez zmiany | 8 079,56 → 8 176,46 |
| `Analiza!C13` | bez zmiany | 7 463,66 → 7 592,46 |
| `Analiza!C14` | bez zmiany | 7 657,90 → 7 769,80 |
| `Analiza!C15` | bez zmiany (`AVERAGE`) | 7 472,80 → 7 517,63 |
| `Analiza!C16` | bez zmiany (`SUM`) | **89 673,56 → 90 211,55** |

(`Analiza!C11` — wrzesień — bez zmiany wartości, bo `D23` = 0.) Dodatkowo `xl/workbook.xml`: `<calcPr calcId="152511" fullCalcOnLoad="1"/>`, żeby Excel przeliczył całość przy otwarciu.

## Metoda

Skrypty jednorazowe (poza repo) uruchomione w obrazie `sail-8.5/app` z `vendor` głównego checkoutu tylko do odczytu:

1. Kopia oryginału i SHA-256 obu plików (identyczne przed edycją).
2. Scratch: formuły `E30` lipiec–grudzień poprawione w XML. PhpSpreadsheet policzył cały skoroszyt przed i po; zbiór zależnych = formuły, których wynik silnika się różni (czyli wyznaczony skanem skoroszytu, nie z pamięci) — 14 komórek wyżej.
3. Plik właściwy: punktowa edycja XML (`<f>` w sześciu `E30`, `<v>` w 14 komórkach, `calcPr`); pozostałe wpisy archiwum zip bez zmian (różnią się tylko `sheet7`–`sheet13` i `workbook.xml`).
4. Dowód (`verify2021`): porównanie formuły i zapisanej wartości każdej komórki każdego arkusza oryginału i poprawki — różni się dokładnie 14 oczekiwanych komórek, liczba komórek ta sama; zapisane nowe wartości równają się wynikowi silnika dla tych komórek; `E30` lipiec–grudzień ma `SUM(D3:D20)+SUM(D23:D29)`; `Analiza!C16` = 90 211,55 (zapisana i z silnika); `unzip -t` bez błędów. Kod wyjścia 0.
