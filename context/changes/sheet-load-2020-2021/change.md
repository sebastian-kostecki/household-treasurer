---
change_id: sheet-load-2020-2021
title: Wczytanie lat 2020 i 2021 ze starszych arkuszy budżetu
status: implementing
created: 2026-11-13
updated: 2026-11-13
archived_at: null
---

## Notes

Rozszerzenie Sheet Load (ADR-0085, `budget:load-sheet`) o lata 2020 i 2021 z plików `storage/app/private/Budżet_2020.xlsx` i `Budżet_2021.xlsx`. Rok 2019 porzucony (zupełnie inny układ: `styczeń_19`, `grudzień_18`, `Podsumowanie`). Obecny loader odrzuca oba pliki: „Ustawienia: brak arkusza”.
