# Odświeżenie planu testów — Plan Brief

> Full plan: `context/changes/test-plan-refresh/plan.md`
> Research: `context/changes/test-plan-refresh/research.md`
> Refresh brief: `context/changes/test-plan-refresh/refresh-brief.md`

## What & Why

Przepisujemy `context/foundation/test-plan.md` §1–§6 i §8 (§7 bez zmian), żeby plan testów odpowiadał repo po M-3, M-4 i próbie przełomu roku (roadmapa F-09, MS-06). Odświeżony plan odblokowuje dwie rzeczy:
- S-44 `e2e-in-ci`, czyli które specy blokują CI;
- S-39…S-43, czyli jak testować północ w Polsce.

Twoja największa obawa to Import, który zapisuje operacje inne niż w pliku. Dostaje ona własny etap.

## Starting Point

Obecny plan (2026-11-17) ma etap 1 jako `change opened`, choć zmiana jest zarchiwizowana. Cytuje porzucony M-2 (S-11…S-16), pisze „brak warstwy e2e” przy 15 specach i nic nie wie o przełomie roku. Research potwierdził przy tym trzy rzeczy:
- krotki z pliku sprawdzamy tylko na podglądzie;
- #271/#272 są zgodne z FR-IMP-08/10;
- ADR-0103 (jeden dzień aplikacji) jest już na `development`, ale ten worktree wyszedł 9 commitów wcześniej (przegląd planu, F1).

## Desired End State

Plan testów ma 9 ryzyk i 6 etapów:
- następny etap dla orkiestratora to Bramki E2E w CI (S-44);
- §5 nazywa pięć speców-bramek z `retries: 0`;
- §6 zawiera gotowe przepisy na test E2E i na test przełomu roku (22:59:59/23:00 UTC, `shell.today`);
- §7 jest bajt w bajt taki jak przed zmianą.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
|---|---|---|---|
| #1 i #271/#272 | Osobny etap „Wierność Importu”: krotki po zapisie od razu, #271/#272 po Twojej decyzji o FR-IMP-08/10 | Obawa nr 1 dostaje własny etap, a oba issue właściciela, bez asercji sprzecznych z PRD | Plan (Q1), Research |
| Przełom roku | Ryzyko #9 + wzorzec §6 + bramka §5 od S-39, bez wiersza w §3 | Testy i tak muszą powstać w S-39…S-43, a osobny etap by je dublował | Plan (Q2), Research |
| Bramki E2E | `seed`, `import-other-format-without-header`, `month-plan-groups`, `irregular-collected` + nowy spec zatwierdzenia Importu w S-44 | Trzy krytyczne przepływy z #7, a niestabilne specy zostają lokalnie | Plan (Q3), Research |
| Powtórki w CI | `retries: 0`, trace przy porażce | Powtórki maskują niestabilny spec (antywzorzec z briefu) | Plan (Q4) |
| Ocena ryzyk | #3 High→Medium, #5 Medium→High, #6 Medium→Low, #9 High/High | Testy dodane w M-3/M-4 obniżają #3/#6, a osiem podbić snapshotu bez strażnika kompletności podnosi #5 (BKP-3 naprawione już w `backup-signals`) | Plan (Q5), Research, audyt |
| Kolejność §3 | E2E w CI → Wierność Importu → Backup → Niezmienniki kwot → Bramki jakości | Zgodna z M-5 (tylko S-44 w kamieniu), potem obawa nr 1 i droga odwrotu | Plan (Q6) |
| Numer ryzyka przełomu | #9 na końcu tabeli, nie #2 | Schemat zabrania przenumerowania, a priorytet wyrażają §3 i §5 | Plan (schemat) |

## Scope

**In scope:**
- §1 linia hot-spotów;
- §2 ryzyka i Risk Response Guidance;
- §3 etapy;
- §4 stos;
- §5 bramki;
- §6 wzorce (E2E, przełom roku, nowe numery etapów);
- §8 daty.

**Out of scope:**
- §7;
- testy, specy, `playwright.config.ts`, YAML CI (S-44);
- zmiany PRD, słownika i ADR (etap 3);
- roadmapa (commit M-5 to warunek wstępny, robi go `/10x-roadmap`).

## Architecture / Approach

Jeden plik, trzy fazy w kolejności zależności treści:
1. numery ryzyk (§2);
2. etapy i bramki, które je cytują (§3–§5);
3. wzorce i daty, które cytują numery etapów (§6, §8).

Każda faza kończy się kontrolami grep i diff: liczba wierszy, brak S-11…S-17, brak kotwic kodu w Source, pusty diff §7. Każda ma też jeden commit `docs(test-plan-refresh): … (pN)`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
|---|---|---|
| 1. Mapa ryzyk (§1, §2) | 9 ryzyk z bieżącymi źródłami i nowymi ocenami, guidance dla każdego | Kotwica pliku przemycona do kolumny Source |
| 2. Etapy, stos i bramki (§3–§5) | 6 etapów, bieżące wersje i liczby, bramka E2E i bramka przełomu | Brak w S-44 czegoś, czego potrzebuje do planu |
| 3. Domknięcie (§3 status, §6, §8) | Etap 1 `complete`, przepisy E2E i przełomu roku, daty | Bramka przełomu roku bez etapu §3 (świadomy wyjątek od schematu), której stanu orkiestrator nie śledzi |

**Prerequisites** (sprawdza je kontrola 1.1):
- roadmapa M-5 zacommitowana na `development` (`/10x-roadmap` w głównym checkoucie);
- `development` zmergowany do worktree (ADR-0103, audyt obserwowalności, `backup-signals`, roadmapa M-5).
**Estimated effort:** jedna sesja, trzy krótkie fazy dokumentacyjne.

## Open Risks & Assumptions

- Etap 3 może utknąć na decyzji o FR-IMP-08/10. To decyzja produktowa, którą otwiera ten etap.
- Etapu 3 nie ma w roadmapie M-5. Czy wejdzie przed styczniem, rozstrzyga `/10x-roadmap`.
- Liczby hot-spotów i profilu testów są przeliczane w dniu implementacji (2026-11-19: 370 commitów, brief mówił 373).
- Bramka E2E przy `retries: 0` może czerwienić się przez środowisko CI w grudniu. Ryzyko przejmuje S-44.

## Success Criteria (Summary)

- `/10x-test-plan` po zmianie podaje etap 2 (S-44) jako następny, a S-44 bierze listę bramek i politykę powtórek z §5.
- S-39 bierze przepis na test przełomu roku z §6.2 bez szukania w archiwum.
- §2 opisuje Twoje obawy (Import, przełom roku, E2E w CI) jako scenariusze z bieżącymi dowodami, bez M-2.
