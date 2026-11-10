# Grupy kategorii — Plan Brief

> Full plan: `context/changes/category-groups/plan.md`

## What & Why

S-22 (FR-004, FR-019, FR-021, FR-022): użytkownik grupuje kategorie listy (przychodów albo wydatków) i widzi je w grupach w „Kategoriach”, w Budżecie rocznym z sumą grupy i w selektorze kategorii operacji. Grupy z Excela („Opłaty stałe”, „Zdrowie”, „Wydatki nieregularne”) dostają odpowiednik w aplikacji, a plan miesiąca (S-25) i raporty (S-28) budują na tym modelu.

## Starting Point

Po S-18 kategoria należy do listy użytkownika przez lata, a plan roku jest na pozycji budżetu. Grup nie ma w kodzie, ale ADR-0063 i słownik opisują je w całości. „Kategorie” są tylko do odczytu, kolejność to jeden `sort_order` listy typu, a Budżet przeciąga wiersze i liczy sumy na serwerze.

## Desired End State

W „Kategoriach” dodajesz grupę w sekcji typu, zmieniasz jej nazwę, przesuwasz ją i usuwasz. Kategorię przypisujesz przez „Grupa…” albo przeciągnięciem, także w Budżecie. Budżet pokazuje zwijane wiersze grup z sumami ich aktywnych pozycji i z sygnałem „ponad plan”. Okna operacji i podgląd Importu pokazują grupy jako podpisy w selektorze. Snapshot v7 niesie grupy, a sumy Budżetu i liczby kontrolne się nie zmieniają.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Model grupy (typ, bez planu, unikalna nazwa, usunięcie rozgrupowuje, zmiana typu kategorii zdejmuje z grupy) | Bez zmian | Rozstrzygnięte w F-03 | ADR-0063 |
| Gdzie zarządzać grupami i przypisaniem | Wszystko w „Kategoriach”: „Dodaj grupę”, menu „⋯” grupy, „Grupa…” w wierszu | Grupa to atrybut listy przez lata; nie zależy od S-19 | Plan |
| Kolejność | Jedna wspólna kolejność typu dla grup i kategorii bez grupy | Użytkownik chce, by „wszystko było przesuwalne”, jak w arkuszu | Plan |
| Przeciąganie | W „Kategoriach” i w Budżecie, także między grupami i poza grupę; „Grupa…” jako droga z klawiatury | Porządki jednym ruchem w obu widokach | Plan |
| Wiersz grupy w Budżecie | Te same kolumny co „Razem” (z aktywnych pozycji grupy), zwijany (domyślnie rozwinięty), „ponad plan” przy przekroczeniu którejkolwiek kategorii | Zgodne z regułą PRD o sygnale na wierszu grupy i z przyszłym S-25 | Plan |
| Puste grupy i archiwum | Grupa bez aktywnej pozycji nie ma wiersza w Budżecie; suma grupy liczy tylko aktywne pozycje grupy, archiwalne zostają w sekcji archiwum | Suma grupy = suma jej widocznych wierszy, a grupy + bez grupy + archiwum = „Razem” zawsze co do grosza (plan-review F1) | Plan |
| Selektory | `CategorySelect` w oknach operacji i w Imporcie; filtr Operacji i paleta bez zmian | Jeden komponent obejmuje każdy wybór kategorii operacji | Plan |
| Typ grupy | Stały od utworzenia, nadawany przez sekcję | Grupa nigdy nie zawiera kategorii innego typu, bez kaskad | Plan |
| Zapis kolejności | Jeden ciąg `sort_order` na typ dla grup i kategorii (grupa przed swoimi kategoriami), jedna czysta klasa drzewa | `ordered()` i jego 11 czytelników działają bez zmian | Plan |
| Spójność typu w bazie | FK jednokolumnowy `nullOnDelete`, typ i właściciel w akcjach, test PG | Zgodne z konwencją repo, bez złożonych FK | Plan |
| Snapshot | v7 z `category_groups` i `category_group_id`; v1–v6 bez grup; poprawka porównań wersji | Czysty dodatek według `.ai/rules/snapshot.md` | Plan |

## Scope

**In scope:**
- ADR-0069, słownik, migracja z `down()` i testem PG, model, polityka, klasa kolejności
- zmiana typu zdejmuje z grupy, nowe miejsce na końcu ciągu, reset danych z grupami
- snapshot v7
- „Kategorie”: zarządzanie grupami, „Grupa…”, przeciąganie, kitchen sink, E2E
- Budżet: wiersze grup z sumami, zwijanie, „ponad plan”, przeciąganie drzewa, kitchen sink
- `CategorySelect` z grupami w oknach operacji i w Imporcie

**Out of scope:** plan miesiąca w grupach (S-25), raporty (S-28), filtr Operacji i paleta z grupami, zmiana typu grupy, plan/opis/archiwum grupy i podgrupy, edycja i archiwum kategorii w „Kategoriach” (S-19), zmiana „Razem” przy „Pokaż zarchiwizowane”, zapamiętywanie zwinięcia.

## Architecture / Approach

```
category_groups (user_id, name, name_key, type, sort_order)   unique(user_id, type, name_key)
categories.category_group_id → category_groups (nullOnDelete)
ciąg typu:  Kredyt 0 · [Opłaty stałe 1] Czynsz 2 · Prąd 3 · Wakacje 4 · [Zdrowie 5] Apteka 6 …
zapis:  drzewo widocznych → CategoryListOrder (wpisz w zajęte miejsca, ukryte zostają) → spłaszcz → 0…n−1
odczyt: buildCategoryTree(groups, categories) w „Kategoriach”, Budżecie i CategorySelect
```

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Domena, schemat i kolejność | ADR-0069, migracja, model, klasa kolejności drzewa, zmiana typu bez grupy | Błąd w algorytmie podzbioru rozrzuci kolejność ukrytych kategorii |
| 2. Snapshot v7 | Grupy w kopii, v1–v6 bez grup | v6 wpada w ścieżkę v1–v5 przez sztywne porównanie wersji |
| 3. Grupy w „Kategoriach” | CRUD grup, „Grupa…”, przeciąganie, E2E | Zagnieżdżone `vuedraggable` i telefon |
| 4. Budżet w grupach | Wiersze grup z sumami, zwijanie, „ponad plan”, przeciąganie | Suma grup różna od „Razem” przy archiwum |
| 5. Grupy w selektorze | Podpisy grup w `CategorySelect` | Rozjazd typów opcji PHP i TS w czterech miejscach |

**Prerequisites:** S-18 zamknięty (jest); w worktree działający `composer install` (hook przy tworzeniu worktree go nie przeszedł) i `npm ci`.
**Estimated effort:** ok. 4–5 sesji w 5 fazach; fazy 3 i 4 to większość pracy.

## Open Risks & Assumptions

- Przeciągnięcie w Budżecie jednego roku zmienia grupę we wszystkich latach i raportach; ADR-0069 to opisuje, UI nie pyta o potwierdzenie.
- S-19 doda menu wiersza kategorii w „Kategoriach” obok „Grupa…”; S-22 nie przesądza jego kształtu.
- Plan miesiąca, paleta i Dashboard dostaną nową kolejność (kategorie grupy obok siebie) bez wierszy grup.

## Success Criteria (Summary)

- Grupy z Excela da się odtworzyć w „Kategoriach” i przestawiać przeciąganiem w obu widokach.
- W Budżecie 2026 suma każdej grupy i „Razem” są co do grosza zgodne z kategoriami, a „Razem” jest takie samo jak przed zmianą.
- Selektor kategorii operacji i Importu pokazuje grupy, a kopia v7 i starsze wczytują się poprawnie.
