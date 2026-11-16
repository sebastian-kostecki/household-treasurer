# Poprawka `Budżet_2019.xlsx` (2026-11-16)

Plik leży poza repo: `storage/app/private/Budżet_2019.xlsx` w głównym checkoucie. Oryginał zachowany obok jako `Budżet_2019.przed-korekta.xlsx`; przywrócenie to skopiowanie go z powrotem. Decyzje: `change.md` (1, 2, 3), plan Faza 1 pkt 4.

## SHA-256

| Plik | SHA-256 |
| --- | --- |
| `Budżet_2019.przed-korekta.xlsx` (oryginał, identyczny z plikiem przed edycją) | `b99e9217bc4b4bfa075e7a25e815598118c16e50c94e000a2b5a499b35fbf0f6` |
| `Budżet_2019.xlsx` (po poprawce) | `73fc70cf915fbc623e18089b8a74219e35fa6975aad02190d84fb3ade4464c86` |

## Błędy

- `Podsumowanie!H3:H14` (wydatki miesiąca) nie liczyło jednej rzeczy:
  - styczeń–czerwiec: plan z kolumny B (`=styczeń_19!B10`, `=marzec_19!B8`…); w maju razem z „cele: Sebastian”;
  - lipiec: dwie pensje (`=lipiec_19!C3+lipiec_19!C4`);
  - sierpień–listopad: wykonanie razem z „Cele”;
  - grudzień: wykonanie bez „Cele”.

  `H16` wynosiło 64 035,95.
- `marzec_19!B3 =B4+B5+B6` pomijało `B7` „inne” 125,00, które blok „Podział” (`L6 =B4+B6+B7`) liczy.

## Reguła po poprawce

`H` miesiąca = wykonanie wierszy wydatków **bez** „cele: Sebastian” / „Cele” i **bez** „Oszczędności” / „Oszczędności (10%)”. Wykonanie to:
- styczeń–maj: kolumna C wierszy stałych i B wierszy list („akcesoria do domu”, „osobiste: Jana”, „osobiste: Sebastian”), bo te nie mają C;
- czerwiec: C;
- lipiec–grudzień: D.

`H14` (grudzień, `SUM(grudzień_19!D8:D23)`) już spełniało tę regułę i się nie zmieniło.

## Zmienione komórki (16)

| Komórka | Formuła przed → po | Zapisana wartość przed → po |
| --- | --- | --- |
| `Podsumowanie!H3` | `styczeń_19!B10` → `SUM(styczeń_19!C11:C23)+SUM(styczeń_19!B24:B26)` | 4 178,83 → 3 687,64 |
| `Podsumowanie!H4` | `luty_19!B10` → `SUM(luty_19!C11:C23)+SUM(luty_19!B24:B26)` | 4 619,19 → 4 622,88 |
| `Podsumowanie!H5` | `marzec_19!B8` → `SUM(marzec_19!C9:C21)+SUM(marzec_19!B22:B24)` | 4 842,71 → 4 796,91 |
| `Podsumowanie!H6` | `kwiecień_19!B8` → `SUM(kwiecień_19!C9:C21)+SUM(kwiecień_19!B22:B24)` | 4 597,57 → 4 684,13 |
| `Podsumowanie!H7` | `maj_19!B8` → `SUM(maj_19!C9:C21)+SUM(maj_19!B22:B24)` | 5 264,80 → 4 838,06 |
| `Podsumowanie!H8` | `czerwiec_19!B8` → `SUM(czerwiec_19!C9:C23)` | 5 436,06 → 5 531,82 |
| `Podsumowanie!H9` | `lipiec_19!C3+lipiec_19!C4` → `SUM(lipiec_19!D8:D22)` | 5 273,20 → 10 995,52 |
| `Podsumowanie!H10` | `SUM(sierpień_19!D8:D24)` → `SUM(sierpień_19!D8:D23)` | 5 230,85 → 4 987,85 |
| `Podsumowanie!H11` | `SUM(wrzesień_19!D8:D24)` → `SUM(wrzesień_19!D8:D23)` | 5 550,57 → 5 120,57 |
| `Podsumowanie!H12` | `SUM(październik_19!D8:D24)` → `SUM(październik_19!D8:D23)` | 6 950,10 → 6 300,10 |
| `Podsumowanie!H13` | `SUM(listopad_19!D8:D24)` → `SUM(listopad_19!D8:D23)` | 5 579,36 → 5 208,56 |
| `marzec_19!B3` | `B4+B5+B6` → `B4+B5+B6+B7` | 5 917,61 → 6 042,61 |
| `marzec_19!D1` | bez zmiany (`B8/B3`) | 0,8184 → 0,8014 |
| `Podsumowanie!I5` | bez zmiany (`marzec_19!B3`) | 5 917,61 → 6 042,61 |
| `Podsumowanie!H16` | bez zmiany (`SUM(H3:H14)`) | **64 035,95 → 67 286,75** |
| `Podsumowanie!I16` | bez zmiany (`SUM(I3:I14)`) | **77 914,29 → 78 039,29** |

Dodatkowo w `xl/workbook.xml` jest `<calcPr calcId="152511" fullCalcOnLoad="1"/>`, żeby arkusz kalkulacyjny przeliczył całość przy otwarciu. `Podsumowanie` nie ma `AVERAGE` w kolumnach H i I (wiersz 15 liczy tylko B–E), więc żadna średnia się nie zmienia.

## Metoda

Skrypty jednorazowe uruchomione na hoście (php 8.4, `vendor` głównego checkoutu tylko do odczytu); kopie robocze w katalogu tymczasowym zadania, poza repo:

1. Kopia oryginału i SHA-256 obu plików (identyczne przed edycją).
2. Silnik PhpSpreadsheet policzył wszystkie 818 formuł skoroszytu przed i po zmianie 12 formuł (w pamięci). Zbiór komórek zależnych to formuły, których wynik się różni, czyli wyznaczony skanem, nie z pamięci. To 4 komórki: `marzec_19!D1`, `Podsumowanie!I5`, `H16`, `I16`. Komórki zależne od `TODAY()` mają w obu przebiegach ten sam dzień, więc nie weszły do różnicy.
3. Właściwy plik: punktowa edycja XML.
   - `<f>` i `<v>` 12 komórek oraz `<v>` 4 zależnych w `xl/worksheets/sheet15.xml` (`Podsumowanie`) i `sheet4.xml` (`marzec_19`);
   - `calcPr` w `xl/workbook.xml`;
   - każda podmiana sprawdzona na dokładnie jedno wystąpienie;
   - żadna z tych komórek nie jest częścią formuły współdzielonej (jedyna współdzielona w `marzec_19` to `D9:D14`);
   - pozostałe wpisy archiwum bez zmian.
4. Dowód: `context/archive/2026-11-16-sheet-load-2019/workbook-fix/verify.php`, kod wyjścia 0, wynik „PASS”:
   - zmieniły się dokładnie 3 wpisy archiwum (`xl/workbook.xml`, `sheet4.xml`, `sheet15.xml`);
   - formuła lub zapisana wartość różni się w dokładnie 16 komórkach z tabeli, a liczba komórek jest ta sama;
   - zapisane nowe wartości równają się wynikowi silnika na poprawionym pliku;
   - `Podsumowanie!H16` = 67 286,75, `I16` = 78 039,29;
   - `unzip -t` bez błędów.

   Uruchomienie z katalogu repo:

   ```bash
   php context/archive/2026-11-16-sheet-load-2019/workbook-fix/verify.php "$PWD" storage/app/private/Budżet_2019.przed-korekta.xlsx storage/app/private/Budżet_2019.xlsx
   ```
