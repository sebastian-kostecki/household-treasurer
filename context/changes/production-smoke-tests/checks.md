# Production smoke tests (S-48) — checks

No personal data, counterparties or single amounts. Row counts and yes/no are allowed. A result is written after the check; the expected result was written before it.

## Przebieg

- Code commit: _(fill in: short SHA of `development`/`main` used for the copy)_
- Snapshot: _(fill in: `user:backup` time UTC, snapshot version, SHA-256 matches on both sides: yes/no)_
- Deployed commit on production: `7c60d458` (VPS HEAD, 2026-11-20 06:18 CET), contains S-40.
- Browser: `playwright-cli`, Europe/Warszawa. Any clock jump of more than 2 h forward logs out: log in again.

## Phase 2 — copy, rehearsal clock (UTC), run in this order

| Krok | Kiedy (zegar UTC) | Co sprawdzić | Oczekiwany wynik | Pokrywa | Wynik |
| --- | --- | --- | --- | --- | --- |
| C1 | 2026-12-03 09:00 | Dashboard „Wymaga uwagi”: card for December plan, and the 2027 draft card | A „Plan niezatwierdzony” card with „zatwierdź do 31.12, 23:59” (December is a proposal and today is day 3). A „Budżet w szkicu” card with „Budżet 2027” and „Import nie przyjmie wierszy z 2027”; it opens Budget 2027. | S-41 (draft-year card, deadline card) | OK: 3.12 „Plan niezatwierdzony · Grudzień · zatwierdź do 31.12, 23:59” i „Budżet w szkicu · Budżet 2027 · Import nie przyjmie wierszy z 2027”; karta szkicu otwiera Budżet 2027 (z „Rozpocznij realizację”). |
| C2 | 2026-12-30 10:00 | Approve December, then „Rozpocznij realizację” for 2027; look at the Dashboard and the year menu | Dashboard still says „budżet 2026 w realizacji · budżet 2027 od 1 stycznia”; the year menu marks 2027 „od 1.01”. | S-41 | OK: po „Zatwierdź” grudzień „Zatwierdzony”, po starcie 2027 Dashboard „środa, 30 grudnia 2026 · budżet 2026 w realizacji · budżet 2027 od 1 stycznia”, menu roku „od 1.01” przy 2027. |
| C3 | 2026-12-31 22:30 | Log in again; choose 2026 in the year menu; in a second browser session choose 2023 | 2026 is chosen before 23:00 UTC. The second session shows 2023. | S-40 2.9, 2.10 (setup) | OK: po ponownym logowaniu o 22:30 UTC sesja 1 ma 2026 (wybrane w menu), sesja 2 (osobny kontekst przeglądarki) wybrała 2023 i otwiera „Budżet 2023”. |
| C4 | 2026-12-31 23:05 | In the same tabs without logging out: Budget and Month Plan; Dashboard; operation dialog date; „Lata”; year-over-year | Tab 1 (2026 chosen) opens Budget and Month Plan on 2027 with no message. Tab 2 stays on 2023. Dashboard („1 stycznia 2027 · budżet 2027 w realizacji”), the menu and the operation dialog default date all say 1 January 2027. A future date is refused with „Data nie może być w przyszłości.”. „Lata” opens on „Pełne lata” with 2027 „w toku”; „Do 1 stycznia” is one click away; year-over-year compares 1.01.2027 with 1.01.2026. | S-40 2.9, 2.10; S-39; S-41 3.7; S-43 | CZĘŚCIOWO OK (23:05 UTC, bez wylogowania): sesja 1 otwiera Budżet 2027 i „Plan miesiąca · styczeń 2027” bez komunikatu; sesja 2 zostaje na „Budżet 2023”; Dashboard „piątek, 1 stycznia 2027 · budżet 2027 w realizacji”, menu „2027” bez „od 1.01”; „Lata” otwiera „Pełne lata” z 2027 „w toku”, „Do 1 stycznia” obok; Raport roku porównuje 01.01.2026 z 01.01.2027. NIE SPRAWDZONE w przeglądarce: domyślna data dialogu operacji i odmowa daty z przyszłości (nie znalazłem przycisku otwierającego dialog w snapshotcie), Panel kategorii „Lata”. |
| C5 | 2027-01-02 09:00 | Import of a statement covering 29.12–02.01 with 2027 started; approve January | December operations land in 2026 lines, January ones in 2027 lines. January proposal copies December for fixed charges and items without a rhythm. January approved. | S-42; S-41 | OK (CSV z mBanku z datami 29.12–02.01, 6 wierszy, w kopii zaznaczono 5; wiersz przychodu pominięty): zapisano 5 operacji, 2 w pozycjach Budżetu 2026 (29.12, 31.12) i 3 w Budżecie 2027 (01.01, 2x 02.01). Styczeń 2027 zatwierdzony. Propozycja stycznia: 32 z 48 kwot jak w grudniu, 16 innych (rytmy roczny i nieregularny biorą roczny udział planu, zgodnie z README). |
| C6 | 2027-01-02 09:00 | After `user:reset-data` + `user:restore` (2027 draft again): Dashboard and Import preview | Dashboard shows „budżet 2027 w szkicu” and the draft-year card. January rows carry „Budżet 2027 jest szkicem. Dochód, wydatek i zwrot z 2027 zaimportujesz po rozpoczęciu jego realizacji.” with „Przejdź do Budżetu 2027”; a ticked January row blocks „Zatwierdź import”; unticking saves December. | S-42; S-41 | OK (02.01.2027): Dashboard „sobota, 2 stycznia 2027 · budżet 2027 w szkicu”, odznaka „Szkic”, karta „Budżet w szkicu · Budżet 2027 · Import nie przyjmie wierszy z 2027”. Podgląd Importu: wiersze ze stycznia mają „Brak kategorii wydatkowej dla roku operacji”, notatkę „Budżet 2027 jest szkicem. Dochód, wydatek i zwrot z 2027 zaimportujesz po rozpoczęciu jego realizacji.” i „Przejdź do Budżetu 2027”; zaznaczony wiersz stycznia wyłącza „Zatwierdź import”, po odznaczeniu zapisują się 2 operacje grudnia (w Budżecie 2026). |
| C7 | 2026-12-03 09:00 | Clock back; `user:reset-data` + `user:restore`; delete the 2027 draft; look at Dashboard | A „Brak budżetu” card with „Budżet 2027” that opens „Dodaj rok 2027”. | S-41 2.6 | OK: po `user:reset-data` + `user:restore` i usunięciu szkicu 2027 (UI „Usuń budżet”) Dashboard „czwartek, 3 grudnia 2026 · budżet 2026 w realizacji” i karta „Brak budżetu · Budżet 2027 · Import nie przyjmie wierszy z 2027”; klik otwiera dialog „Nowy budżet” z rokiem 2027 (przycisk „Utwórz budżet”). Uwaga: dialog nazywa się „Nowy budżet”, a nie „Dodaj rok 2027” (to etykieta pozycji menu roku). |

## Phase 3 — production after the M-5 deploy

| Krok | Kiedy (data) | Co sprawdzić | Oczekiwany wynik | Pokrywa | Wynik |
| --- | --- | --- | --- | --- | --- |
| D1 | after deploy (agent) | `curl -I https://budget.kostecki.dev/up` | HTTP 200 | deploy | OK 2026-11-20: HTTP/2 200. |
| D2 | after deploy (owner) | `git rev-parse --short HEAD` on the VPS; `git merge-base --is-ancestor f48b8363 HEAD && echo ok` | Prints `ok`: the deployed commit contains S-40. | S-40 | OK (odczyt agenta przez SSH, 2026-11-20): `HEAD` na VPS `7c60d458` (06:18 CET) zawiera `f48b8363` (`merge-base --is-ancestor` → ok); kontener `treasurer-app` z obrazem `prod` zbudowanym ok. 2 h wcześniej i działający. Właściciel potwierdza, że to wynik `deploy.sh` z tego commitu. |
| D3 | after deploy (owner) | Dashboard; login screen | Dashboard shows today's date with the weekday and „budżet 2026 w realizacji”. Login screen shows „Rok budżetu”, „2026 · <%>” and November as the current month bar (no day). | S-39 | |
| D4 | after deploy (owner) | claude.ai connector asked for To Spend | Equals the Dashboard value for the same `app_day`. | S-47 | |
| D5 | after deploy (owner) | Year menu; „Lata” | 2026 is „bieżący” with no „od 1.01” mark; „Lata” opens on „Do <today>”. | S-41; S-43 | |

Repeat D1, D3 and D4 after any later deploy before 31.12 as rows `D1b`, `D3b`, `D4b`.

## Phase 4 — production in December

| Krok | Kiedy (data) | Co sprawdzić | Oczekiwany wynik | Pokrywa | Wynik |
| --- | --- | --- | --- | --- | --- |
| G1 | 3–5.12 | Dashboard „Wymaga uwagi” | A card with „zatwierdź do 31.12, 23:59” while December is a proposal; the „Budżet w szkicu · Budżet 2027” card if 2027 is a draft, or „Brak budżetu · Budżet 2027” opening „Dodaj rok 2027” if there is none. | S-41 | |
| G2 | 30–31.12 | Approve December, then start 2027 | Dashboard still reads „budżet 2026 w realizacji · budżet 2027 od 1 stycznia”; the year menu marks 2027 „od 1.01”. | S-41 | |
| G3 | 31.12, 23:00–23:59 Polish time (before 23:00 UTC) | Choose 2026 in the year menu; keep the tab open and do not log out. Record the time. | 2026 is the chosen year in this session. | S-40 (setup for S2) | |

## Phase 5 — production on 1–2 January 2027

| Krok | Kiedy (data) | Co sprawdzić | Oczekiwany wynik | Pokrywa | Wynik |
| --- | --- | --- | --- | --- | --- |
| S1 | 1.01, from 00:00 Polish time | Menu, Dashboard, operation dialog default date | All three agree on 1 January 2027; Dashboard reads „piątek, 1 stycznia 2027 · budżet 2027 w realizacji”; the „od 1.01” mark is gone. | S-39; S-41 | |
| S2 | 1.01, 00:00 to about 01:30 Polish time, in the G3 tab | Budget and Month Plan open on 2027 with no message. Record the time. | Both open on 2027, no message. If checked later or after a new login: „nie dowodzi (nowa sesja)”, pointing to C4. | S-40 | |
| S3 | 1.01 | „Lata”; Category Panel „Lata” tab; year-over-year; Budget 2027 | „Lata” and the panel open on „Pełne lata” with 2027 „w toku”; year-over-year compares 1.01.2027 with 1.01.2026; Budget 2027 shows „zebrane startowe” without „prognoza”. | S-43; S-41 | |
| S4 | 2.01 | Import the statement covering 29.12–02.01; approve January | December operations land in 2026 lines, January ones in 2027 lines; the January proposal copies December amounts for fixed charges and items without a rhythm; January approved. | S-42 | |

## Sprzątanie

Phase 2 only. Done 2026-11-20 except the last item (owner):

- `app:rehearsal-clock --off` prints „zegar próby wyłączony”.
- `user:reset-data` of the rehearsal user.
- Drop database `preview_production_smoke_tests`.
- Empty `storage/app/restore-trial/` (no snapshot or CSV).
- Remove `.playwright-cli`.
- OWNER TO DO: delete this snapshot (`1-20261120070708.json`) (and the 2026-11-18 one) from `storage/app/private/user-snapshots/` on the VPS.

## Werdykt MS-12

_(To fill after S4: "M-5 works on production without manual fixes", or a list of failures, each with the id of the change that fixes it. The owner agrees with it.)_
