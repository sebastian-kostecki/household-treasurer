---
change_id: mcp-read-only-server
title: MCP server for asking Claude about the budget, read-only first
status: archived
created: 2026-11-19
updated: 2026-11-19
archived_at: 2026-11-19T20:55:54Z
---

## Notes

Serwer MCP tylko do odczytu, żebym mógł pytać Claude'a o stan budżetu bez otwierania aplikacji.

Cel: pytam Claude'a (najpierw Claude Code i desktop, docelowo aplikacja na telefonie) o stan budżetu i dostaję odpowiedź z moich danych na produkcji. Pierwsza wersja nie zapisuje niczego: żadnych operacji, zmian planu ani importu.

Zakres odczytu, w pojęciach ze słownika (context/foundation/glossary.md):
- salda kont, Spendable Liquidity i Total funds,
- „do wydania” w bieżącym miesiącu,
- plan kontra wykonanie dla kategorii i grup w miesiącu i w roku,
- ostatnie operacje z filtrem po kategorii i okresie.

Fakty sprawdzone 2026-11-19, potwierdź je w kodzie:
- laravel/mcp v1.1.2 jest w composer.lock tylko jako zależność deweloperska Laravel Boost, więc na produkcji go nie ma,
- uwierzytelnianie to wyłącznie Fortify (sesja); nie ma Sanctum ani Passport,
- nie ma routes/ai.php ani app/Mcp,
- każdy użytkownik widzi wyłącznie własne dane i serwer MCP musi tę zasadę zachować.

Pytania do zbadania:
1. Które istniejące zapytania, Resources i klasy liczą powyższe wartości dla Dashboardu, planu miesiąca i raportów? Narzędzia MCP mają z nich korzystać, a nie liczyć drugi raz po swojemu.
2. Logowanie: token Sanctum czy OAuth przez Passport? Sprawdź w aktualnej dokumentacji laravel/mcp i w dokumentacji konektorów Claude, czego wymaga aplikacja mobilna, i podaj koszt obu dróg. Czy da się zacząć od tokenu i dołożyć OAuth później bez przepisywania narzędzi?
3. Wariant lokalny (bez sieci, na danych deweloperskich) jako pierwszy krok do wypróbowania narzędzi w Claude Code: czy warto go wydzielić jako osobny etap?
4. „Dzisiaj” i bieżący rok w narzędziach muszą być zgodne z jednym dniem aplikacji (ADR-0103), także wokół przełomu roku.
5. Testy: jak w Pest pokryć każde narzędzie, w tym próbę odczytu danych innego użytkownika?
6. Produkcja: ścieżka endpointu za Traefikiem i Cloudflare, nagłówki bezpieczeństwa, limit zapytań, logowanie wywołań, limit pamięci kontenera (1 GB).

Ograniczenia:
- M-5 to kamień jakości, a nowe funkcje są w roadmapie odłożone do stycznia (decyzja z 2026-11-18). Nie zmieniaj roadmap.md. W wynikach napisz, co z tej zmiany można bezpiecznie zrobić przed zamknięciem M-5 (np. wariant lokalny), a co powinno poczekać.
- Bez kodu produkcyjnego na tym etapie. Wynik to research z rekomendacją i listą decyzji, które muszę podjąć sam.

Added 2026-11-19 during research, user's words: „przez mcp chciałbym też wrzucać operacje gotówkowe”. The research covers the write path for cash operations; whether it joins the first version or comes as a stage after reading is the user's decision.
