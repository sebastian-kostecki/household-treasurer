# Konta w systemie „C · Atrament i morski” — Plan Brief

> Full plan: `context/changes/accounts-atrament-ui/plan.md`
> Research: `context/changes/accounts-atrament-ui/research.md`

## What & Why

Konta wyglądają jak widok sprzed Atramentu. Ich wygląd odstaje od Budżetu, Operacji, Planu miesiąca i Dashboardu:

- jedna karta `StatTile` z większą liczbą;
- lista bez nagłówków kolumn, z saldami, które nie stoją w jednej kolumnie;
- zarchiwizowane konta przygaszone `opacity-60`, przez co ikona „Przywróć” wygląda na wyłączoną;
- `Checkbox` w miejscu przełącznika;
- dialog bez blokady zamykania w trakcie zapisu.

Zmiana przenosi widok na wzorzec `PageAccounts` z design systemu.

## Starting Point

`Accounts.vue` używa `StatTile`, `Checkbox`, `ListSurface` z `.holdings-list-row` (wspólną z Lokatami) i `AccountIdentityBlock`. Kontroler wysyła tylko Płynność dysponowalną. Łączne środki liczy inline wyłącznie `DashboardController`. `AccountFormDialog` ma ręczne pola, „Typ” jako select i „Anuluj” `secondary`, wbrew ADR-0040.

## Desired End State

Na górze strony stoi wiersz tytułu „Konta” z opisem i przyciskiem „Dodaj konto”. Pod nim jest panel miar z Płynnością dysponowalną i Łącznymi środkami („z lokatami”), a niżej `Switch` „Pokaż zarchiwizowane”. Tabela ma kolumny Nazwa | Typ | Saldo bieżące | Akcje, logo banku 28 px, podpis z bankiem albo „Portfel” i saldo z „zł” w każdej komórce. Na telefonie wiersz ma dwie linie. Zarchiwizowany wiersz jest wyciszony, bez przezroczystości, a jego ujemne saldo zostaje czerwone. Dialog konta ma pola 34 px, „Typ” jako segmenty, „zł” w polu salda, spinner i blokadę zamykania. Lokaty i Dashboard wyglądają jak przed zmianą.

## Key Decisions Made

| Decision                            | Choice                                                             | Why (1 sentence)                                                                       | Source   |
| ----------------------------------- | ------------------------------------------------------------------ | -------------------------------------------------------------------------------------- | -------- |
| Jednostka w tabeli                  | „zł” w każdej komórce, bez stopki „Kwoty w zł” (ADR-0044)          | Jak we wzorcu `PageAccounts`: jedna kolumna kwot bez wiersza sumy, sumy w panelu miar. | Plan     |
| Ujemne saldo zarchiwizowanego konta | Zostaje `text-destructive`, reszta wiersza `muted-foreground`      | FR-ACC-10 każe jednoznacznie oznaczyć ujemne saldo, a Płynność nadal je liczy.         | Plan     |
| Miejsce `Switch`                    | Osobny wiersz pod panelem miar, jak w Budżecie                     | W wierszu tytułu stoi tylko jedna akcja `primary` (`design-system.md`).                | Plan     |
| Podpis Łącznych środków             | Statyczny „z lokatami”                                             | Jeden nowy props, spójnie z Dashboardem.                                               | Plan     |
| Dialog konta                        | Pełne ADR-0040 i „Typ” jako `SegmentedControl` `block`             | Tak wymagają komponenty `Dialog`/`Field` mocka i `js-dialogs.md`; zamyka dług F7.      | Plan     |
| Odczyt Łącznych środków             | `App\Support\TotalFunds::forUser`, używany przez Dashboard i Konta | Jedna definicja z glosariusza zamiast drugiej kopii sumy.                              | Research |
| Siatka tabeli                       | Nowa `.accounts-grid`; `.holdings-list-row` bez zmian              | `.holdings-list-row` jest wspólna z Lokatami, które dostaną własne `/10x-ui`.          | Research |
| Znak banku                          | `AccountMarkCompact size="md"` z logo, bez skrótów z podglądu      | Każdy nazwany bank ma logo; skróty to wartości z podglądu, nie z repo.                 | Research |
| Straż literałów                     | Nowy `AccountsHardcodedUiTest` na wzór Dashboardu                  | `check:ui` nie łapie `text-lg`, `opacity-60` ani `border-border/60`.                   | Research |

## Scope

**In scope:**

- ADR-0044, `design-system.md`, `js.md`
- `TotalFunds` i props `total_funds_minor_units` z testami
- widok `Accounts.vue` z `.accounts-grid` w `app.css`
- `AccountFormDialog`
- `AccountsHardcodedUiTest`
- scenariusze `/dev/ui/accounts` i `/dev/ui/dialogs`
- zrzuty 390/1280 px w obu motywach

**Out of scope:**

- Lokaty i ich dialog
- zmiany `.holdings-list-row`, `AccountIdentityBlock`, `AccountMarkCompact`, `StatTile`
- skróty banku w kafelku
- podpis z kwotą lokat
- composable `useShowArchivedToggle`
- treść dialogów i potwierdzeń
- stopki Dashboardu

## Architecture / Approach

Najpierw dokumenty (ADR-0044 i reguły), potem kontrakt propsów po obu stronach z `assertInertia`, potem widok na nazwanej klasie siatki w `@layer components`, osobno dialog na `FormField` i `SegmentedControl`, a na końcu macierz stanów, zrzuty z podglądu worktree i pełne CI. Komponenty dzielone z Lokatami i Dashboardem zostają nietknięte, więc zmiana nie przestawia innych widoków.

## Phases at a Glance

| Phase                      | What it delivers                                                                       | Key risk                                                                                |
| -------------------------- | -------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------- |
| 1. Decyzje i dokumenty     | ADR-0044, poprawione `design-system.md` i `js.md`                                      | Sprzeczność z ADR-0037/0039, jeśli wyjątki nie zostaną dopisane we wszystkich miejscach |
| 2. Kontrakt propsów        | `TotalFunds`, props `total_funds_minor_units`, fixtures, testy na PostgreSQL           | `types:check` padnie, jeśli fixtures nie wejdą w tym samym commicie                     |
| 3. Widok Konta             | Tytuł, `StatPanel`, `Switch`, `.accounts-grid`, wiersz zarchiwizowany, straż literałów | Układ dwóch linii na 390 px przy długich nazwach                                        |
| 4. Dialog konta            | Pełne ADR-0040, „Typ” jako segmenty                                                    | Powiązanie segmentów z polem Bank i wysyłką `type`                                      |
| 5. Stany i bramka wizualna | Scenariusze sinka, zrzuty w obu motywach, `ci:check` + Sloppy                          | Czytelność `destructive` na wyciszonym wierszu w ciemnym motywie                        |

**Prerequisites:** worktree z `composer install`, `.env`, `npm ci`, `npm run build`; sieć `household-treasurer_sail` z PostgreSQL do testów.
**Estimated effort:** ~2–3 sesje w 5 fazach (fazy 1–2 krótkie, 3–5 główna praca).

## Open Risks & Assumptions

- Zakładam, że stałe szerokości kolumn z mocka (180/150/96 px) mieszczą „Rachunek bankowy” i saldo sześciocyfrowe. Jeśli nie, faza 3 je dostraja w klasie, nie w szablonie.
- `AccountMarkCompact` zostaje z `border-border/60`, więc straż Kont go pomija; zostaje to długiem do zmiany Lokat.
- ADR-0044 tworzy drugi wyjątek od „Kwoty w zł”; następny widok tabelaryczny musi wybrać jedną z dwóch reguł świadomie.

## Success Criteria (Summary)

- Konta wyglądają jak reszta aplikacji w Atramencie: tytuł `page-title`, dwie miary w `StatPanel`, tabela z nagłówkami, „zł” w saldach i wyciszone zarchiwizowane konta z czytelnymi akcjami.
- Dialog konta nie da się zamknąć w trakcie zapisu i zachowuje się jak dialogi kategorii i operacji.
- `composer ci:check`, `vendor/bin/sloppy ci` i `AccountsHardcodedUiTest` są zielone, a Lokaty i Dashboard są wizualnie bez zmian.
