# Akcje wiersza kategorii w menu „⋯”, przypisanie grupy przez okno — Plan Brief

> Full plan: `context/changes/category-visual-fixes/plan.md`
> Research: `context/changes/category-visual-fixes/research.md`

## What & Why

W „Kategoriach” każdy wiersz ma trzy punkty wejścia akcji: link „Operacje →”, przycisk „Grupa…” i menu „⋯”. Kolumna powtarzanych napisów zagłusza nazwy kategorii, a przypisanie grupy dzieje się od razu po kliknięciu w menu, bez potwierdzenia i z błędem tylko w toaście. Użytkownik chce obie akcje w menu „⋯”, a przypisanie grupy w oknie.

## Starting Point

Link i „Grupa…” trafiły do wiersza, zanim wiersz dostał menu „⋯” (S-18, S-22). S-21 przeniósł do menu edycję, archiwum, scalanie i usuwanie, ale link i picker zostawił. Backend przypisania (`PATCH categories/{category}/group`) wystarcza oknu bez zmian.

## Desired End State

Wiersz ma tylko chip, lata i „⋯”. Menu zaczyna się od „Operacje”, a aktywny wiersz ma w nim „Zmień grupę…”. Ta pozycja otwiera okno z listą radio: „Bez grupy” i grupy typu, z zaznaczoną obecną grupą. Zapis zamyka okno i odświeża listę bez skoku przewijania. „Zmień grupę…” jest nieaktywne, gdy typ nie ma grup albo gdy trwa zapis kolejności po przeciągnięciu.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Kształt przypisania | Okno na kategorię, Anuluj/Zapisz | Chroni przed przypadkowym przeniesieniem i stawia błąd przy wyborze | Research |
| Zarządzanie grupami | Bez zmian (nagłówek sekcji, menu wiersza grupy) | Prośba dotyczyła wiersza kategorii, nie CRUD grup | Research |
| Kontrolka w oknie | Nowy `ui/radio-group`, ADR-0083, wyjątek w `js-dialogs.md` | Wszystkie grupy widoczne od razu i lista rośnie z liczbą grup; `SegmentedControl` ściska się przy 5+ grupach | Plan |
| Typ bez grup | „Zmień grupę…” nieaktywne | Spójne z „Scal z inną…” bez innej kategorii | Plan |
| Blokada w trakcie zapisu kolejności | Tylko „Zmień grupę…” | To jedyna pozycja, która rusza to samo drzewo co przeciąganie | Plan |
| Szablon okna | `ConfirmActionDialog`, wzorem `CategoryMergeDialog` | Gotowy spinner, blokada zamknięcia i błąd serwera | Plan |
| Wartość „Bez grupy” | Jawne ukryte pole `category_group_id` z `''` → `null` | reka radio potrzebuje niepustej wartości; test Feature przypina kontrakt | Plan |
| Przewijanie | Prop `options` w `ConfirmActionDialog` → `preserveScroll` | Picker zachowywał pozycję listy, okno nie może tego zgubić | Plan |
| Kolumna akcji | Szerokość `icon-sm` zamiast 7rem, stała | Nagłówek nie ma komórki akcji; przy `auto` kolumna lat rozjechałaby się | Plan |
| Archiwum | „Operacje” w menu archiwalnym | Wiersz archiwalny też traci link | Research |

## Scope

**In scope:**
- `ui/radio-group`, `CategoryGroupAssignDialog`, opcje wizyty w `ConfirmActionDialog`, ADR-0083, reguła `js-dialogs.md`, „Gotowe elementy”
- „Operacje” i „Zmień grupę…” w menu „⋯”, usunięcie komórki linku i `CategoryGroupPicker.vue`, siatka `.categories-grid`
- Teksty w `lang/pl/ui.php`, oba kitchen sinki, `design-system.md:120-121`, strażnicy (`check:ui`, `CategoriesHardcodedUiTest`, `ui-contract`)
- Test Feature dla `''`, E2E ze wspólnym helperem przypisania i nowym testem powrotu do „Bez grupy”

**Out of scope:**
- Okno „Zarządzaj grupami”, podmenu w „⋯”, podpowiedź „Dodaj grupę” w oknie
- Blokada Edytuj/Archiwizuj/Scal/Usuń w trakcie zapisu kolejności
- Backend, trasy, propsy Inertia, ADR-0069; literał `220px`; nieaktualne ADR-0078:23

## Architecture / Approach

Menu „⋯” (`CategoryListRowActions`) staje się jedynym miejscem akcji wiersza. „Operacje” to `DropdownMenuItem as-child` z Inertia `<Link>` do Operacji z filtrem po id kategorii. „Zmień grupę…” otwiera `CategoryGroupAssignDialog`, sterowane `v-model:open` poza treścią menu, jak inne potwierdzenia. Okno wysyła Wayfinder `CategoryGroupController.assign.form()` przez `ConfirmActionDialog`. Strona przekazuje `reorderHttp.processing` przez wiersz do menu.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Okno „Zmień grupę…” i `ui/radio-group` | Gotowe okno w `/dev/ui/dialogs`, prymityw radio, ADR-0083, strażnicy | `''` z formularza nie dociera jako `null` — pierwszy test to wychwyci |
| 2. Menu „⋯” przejmuje akcje wiersza | Nowy wiersz i menu, siatka, teksty, docs, E2E przez helper | 4 przepływy E2E w 3 plikach; nagłówek siatki rozjechany przy złej szerokości kolumny |

**Prerequisites:** worktree z `composer install`, `.env`, `npm ci`, `npm run build`; testowa baza PostgreSQL `testing_category-visual-fixes` (`context/foundation/agents/parallel-changes.md`); dane demo dla E2E.
**Estimated effort:** ~1–2 sesje w 2 fazach.

## Open Risks & Assumptions

- CLI `shadcn-vue add` może nie działać w powłoce agenta (Windows `npx`); wtedy prymityw powstaje ręcznie ze źródła shadcn-vue new-york-v4.
- Założenie: Inertia v3 `<Form :options>` przyjmuje `preserveScroll` (typ `FormComponentOptions` w `@inertiajs/core`), sprawdzone tylko w typach.
- Zmieniony tekst `group_empty_hint` wskazuje menu; jeśli ktoś szuka „Grupa…”, znajdzie „Zmień grupę…” w „⋯”.

## Success Criteria (Summary)

- Wiersz kategorii pokazuje tylko nazwę, lata i „⋯”; „Operacje” i „Zmień grupę…” działają z menu, także z klawiatury i na telefonie.
- Przypisanie wymaga wyboru i „Zapisz”, błąd stoi w oknie, a lista nie przewija się na górę.
- Bramka, `check:ui`, kontrakt UI i E2E zielone; stany okna i menu widać w kitchen sinkach.
