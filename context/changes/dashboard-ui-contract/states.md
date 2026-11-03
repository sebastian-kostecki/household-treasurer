# Dashboard: macierz 7 stanów (po fazie 4)

Zrzuty leżą w `screenshots/after-phase-4/`. Nazwa: `<stan>-<scenariusz>-<szerokość>-<motyw>.png`, szerokość `1440` lub `390`, motyw `light` lub `dark`. Kolumna „Zrzut” ma zostać uzupełniona nazwami plików albo `N/A` z powodem. Wiersz bez pliku ani powodu nie jest gotowy (krok 4.8).

Stan zapisany jako **TODO** czeka na zrzut. Stanów zależnych od interakcji (hover, focus-visible, disabled) kitchen sink nie pokaże, więc zrzuty robimy z działającej aplikacji `/dashboard` (demo użytkownik z `DemoHouseholdSeeder`).

| Stan | Gdzie pokazany | Jak go wywołać | Zrzut |
| --- | --- | --- | --- |
| default | `/dev/ui/dashboard`: scenariusze `default`, `zero-amounts`, `negative` | otworzyć stronę, przewinąć do scenariusza | TODO |
| empty | `/dev/ui/dashboard`: scenariusze `empty-new-user`, `no-budget`, `draft`, `no-categories` | jak wyżej | TODO |
| loading | N/A | — | N/A: strona nie ma deferred props i `<Deferred>`, a skeletony wyklucza `.docs/specs/dashboard-hierarchy.md:78`. Jedyny „loading” to `processing`, pokazany jako disabled. |
| error | `/dev/ui/dashboard`: przycisk „Pokaż błąd wyboru roku” (toast). `/dashboard`: `InputError` w `CreateBudgetDialog` | toast: kliknąć przycisk. `InputError`: „Utwórz budżet” dla roku, który już ma budżet | TODO |
| disabled | `/dashboard` przy spowolnionej sieci: przyciski z `processing` i karty powiadomień miesięcznych podczas POST | DevTools → Network → „Slow 3G”, kliknąć „Plan miesiąca” albo kartę miesięczną | TODO |
| hover | `/dashboard`: wiersz kont, wiersz kategorii, karta powiadomienia | najechać kursorem | TODO |
| focus-visible | `/dashboard`: karta powiadomienia i przycisk „Otwórz budżet” obok siebie | Tab (pierścień 3 px, `ring/50` na obu) | TODO |

## Ręczna kontrola błędu wyboru roku (krok 4.6)

Toast przy prawdziwym błędzie 4xx/5xx i offline nie był sprawdzony w przeglądarce. Do obejrzenia:

1. Otwórz `/dashboard` w dwóch kartach, w drugiej usuń budżet, w pierwszej kliknij „Plan miesiąca”. Powinien pojawić się toast „Nie udało się otworzyć wybranego roku…”.
2. To samo offline (DevTools → Offline).
3. Zwróć uwagę, czy obok toasta nie pojawia się domyślny modal błędu Inertia (listener nie anuluje zdarzenia). Jeśli tak, wpisz to tutaj jako ustalenie.
4. Udany POST działa jak dotąd.

## Ustalenia

_(puste; uzupełnij po oględzinach)_
