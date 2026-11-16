# Kategorie użytkownika 1 na produkcji (2026-11-16)

Źródło: odczyt tylko do odczytu na `halina175` (`/srv/apps/household-treasurer`, wdrożony commit `33e0997b`), `scripts/artisan.sh tinker` z listą `categories` i `category_rhythms` użytkownika 1. Bez `user:backup` (nic nie zapisano na serwerze).

Stan lat: Budżety 2020–2026 `execution`, 2027 `draft`; konta arkusza 12–15 (2022–2025), 17 (2020), 18 (2021). Brak Budżetu i konta arkusza 2019. W `storage/app/private/sheet-load/` leżą pliki 2020–2025, bez 2019. Nie ma kategorii „Stypendium”.

**Kategoria sportowa: 49 „Sport” (expense, aktywna, ikona `dumbbell`, Rytm od 2026-11 `even_annual`).**

Id spoza `mapping.php` (`categories`):

| Id | Nazwa | Typ | Zarchiwizowana | Rytm |
| --- | --- | --- | --- | --- |
| 13 | Narzędzia AI | expense | nie | 2026-1 running, 2026-11 running |
| 26 | Prowizje i opłaty bankowe | expense | nie | 2026-1 running |
| 38 | Rekolekcje | expense | nie | 2026-1 even_annual, 2026-11 irregular |
| 43 | Zajęcia pozalekcyjne | expense | nie | 2026-11 fixed_charge |
| 46 | Hosting i domeny | expense | nie | 2026-11 even_annual |
| 47 | Ubezpieczenie mieszkania | expense | nie | 2026-11 even_annual |
| 48 | Subskrypcje | expense | nie | 2026-11 running |
| 49 | Sport | expense | nie | 2026-11 even_annual |
| 50 | Optyk | expense | nie | 2026-11 even_annual |

Id 40 i 41 nie istnieją. Nazwy id z `mapping.php` (1–12, 14–25, 27–37, 39, 42, 44, 45) zgadzają się z sekcją `categories` mapowania.
