---
change_id: import-category-suggestions
title: Import category suggestions
status: impl_reviewed
created: 2026-11-04
updated: 2026-11-04
archived_at: null
---

## Notes

Podczas importu, przy przypisywaniu kategorii, wiersz podglądu dostaje podpowiedź opartą na wcześniejszych wyborach i danych z wiersza importu.

Ustalenia z rozmowy (2026-11-04):
- Podejście: wagi słów z historii operacji, bez Typesense i bez nowej usługi. Waga słowa zależy od tego, przy ilu różnych kategoriach występowało: szum (imię i nazwisko, miasto, `POL`, `PLN`) ~0, nazwa sklepu (`STOKROTKA` przy 1 kategorii) ~1. Cyfry, daty i kwoty odpadają przy normalizacji. Liczenie w PHP na operacjach w Postgresie.
- Dlaczego nie Typesense ani samo `pg_trgm`: dopasowanie całego tekstu jest zdominowane przez szum płatności kartą, więc dwa różne sklepy wyglądają podobniej niż dwa zakupy w tym samym sklepie.
- Podpowiedź jest wstępnie wybrana w wierszu podglądu i oznaczona „✦ podpowiedź”.
- Bez ani jednego wspólnego słowa o dużej wadze nie ma podpowiedzi (pole puste jak dziś). Dotyczy nowych sklepów i BLIK-ów typu „Payment for order ID …”.
- Kategorie należą do roku budżetu (`CreateBudget::copyCategories` nie zapisuje źródła), więc historię mapujemy na bieżący rok po nazwie i typie. Brak kategorii lub zarchiwizowana: brak podpowiedzi.
- Potrzebne nowe hasło w `CONTEXT.md`, bo przy Settlement candidate jest „_Avoid_: … similar transaction”.
- Pomiar na lokalnych danych (1009 wierszy z importu, odtworzenie chronologiczne, tylko wcześniejsze wpisy): sierpień–listopad 79% wierszy z podpowiedzią, 95% trafnych, 99% w top 3; od stycznia 78% i 92%. Wyniki jako próg do testów.
- Prototyp pomiaru: `prototype/eval_suggest.php` (tylko odczyt, uruchamiany `docker exec -i household-treasurer-laravel.test-1 php < …`). To nie jest kod aplikacji.

Przegląd implementacji (2026-11-04): pomiar `eval_service.php` na 1009 wierszach: od stycznia 77,1% z podpowiedzią, 93,6% trafnych, 95,2% w top 3; sierpień–listopad 79,3% / 96,5% / 99,3%. Próg „od stycznia" (78%) niespełniony o 0,9 pp; zaakceptowane odstępstwo (hipoteza: reguła kandydatów `w(t) ≥ WEIGHT_FLOOR` wprowadzona dla wydajności, nieprzetestowana). Stałych nie stroimy.
