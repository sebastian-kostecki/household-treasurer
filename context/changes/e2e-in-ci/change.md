---
change_id: e2e-in-ci
title: E2E w CI — bramka ze stabilnych speców Playwright na pushu i PR
status: implemented
created: 2026-11-19
updated: 2026-11-19
archived_at: null
---

## Notes

Roadmap S-44 (MS-07), etap 2 planu testów (`context/foundation/test-plan.md` §3 „Bramki E2E w CI”), ryzyko #7: krytyczny przepływ w przeglądarce psuje się po merge'u, a CI jest zielone, bo specy E2E działają tylko lokalnie.
Bramki według §5: `seed`, `import-other-format-without-header`, `month-plan-groups`, `irregular-collected` i nowy spec zatwierdzenia Importu; `retries: 0`, trace przy porażce; spec staje się bramką dopiero po spełnieniu §6.3.
Worktree: .claude/worktrees/e2e-in-ci (gałąź worktree-e2e-in-ci, przestawiona na lokalny development 863a422).

### Faza 2 — celowe zepsucia (cofnięte przed commitem)

- `seed`: `name="description"` → `name="descriptionx"` w `ExpenseFormDialog.vue` — spec czerwony.
- `month-plan-groups`: `v-if="overPlan"` → `v-if="false"` w `MonthPlanGroupRow.vue` — spec czerwony.
- `irregular-collected`: `:value="openingCollectedMinorUnits ?? 0"` → `:value="0"` w `CategoryListFormDialog.vue` — spec czerwony.
- `import-other-format-without-header`: `v-model="hasHeader"` → `:model-value="hasHeader"` w `ImportColumnMapping.vue` — spec czerwony.
- `pageerror`: sam `throw` w `onMounted` NIE czerwieni specu, bo Vue łapie błąd hooka i loguje go przez `console.error` (brak `pageerror`). Dowód asercji: `setTimeout(() => { throw … })` w `onMounted` w `Operations.vue` — `seed` czerwony na `expect(pageErrors).toEqual([])`.
- Zamyka punkt `context/archive/2026-11-10-category-rhythm/follow-ups/review-fixes.md:6` (stamp base36 zamiast `Date.now()` — flake Luhna).

### Faza 3 — celowe zepsucie (cofnięte przed commitem)

- `import-approve`: `rows[${row.index}][category_id]` → `[category_idx]` w `ImportPreviewRow.vue` — serwer nie dostaje kategorii, brak przekierowania na `/operations`, spec czerwony (`toHaveURL`). Po cofnięciu zielony, w bazie 0 operacji ze stampem.

### Faza 4 — próba w trybie CI

- Próba 1 (świeża baza `e2e_ci_e2e_in_ci`, `CI=1`, `setup` osobno, potem `--project=gate --no-deps --repeat-each=20`): 99/100. Porażka: `irregular-collected` (limit testu 30 s; spec trwa 27–43 s).
- Poprawka 1: `timeout: 90_000` w projekcie `gate`. Próba 2: 99/100, porażka `month-plan-groups`: dialog „Dodaj wydatek” w stanie `closed` został w DOM >5 s (animacja wyjścia pod obciążeniem; aplikacja nie respektuje `prefers-reduced-motion`).
- Poprawka 2: `expect: { timeout: 10_000 }` w configu (asercje bez zmian). Próba 3: 100/100, 36 min.
- Wymuszona porażka w trybie CI (zła kwota w `seed`): adnotacja `::error` reportera `github`, `trace.zip` w `test-results/`, raport w `playwright-report/`.
- Smoke `scripts/e2e-server-ci.sh` w obrazie Sail: `/login` 200, asset z manifestu 200, przy `public/hot` kod 1 i komunikat.
