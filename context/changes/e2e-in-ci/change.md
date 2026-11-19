---
change_id: e2e-in-ci
title: E2E w CI — bramka ze stabilnych speców Playwright na pushu i PR
status: implementing
created: 2026-11-19
updated: 2026-11-19
archived_at: null
---

## Notes

Roadmap S-44 (MS-07), etap 2 planu testów (`context/foundation/test-plan.md` §3 „Bramki E2E w CI”), ryzyko #7: krytyczny przepływ w przeglądarce psuje się po merge'u, a CI jest zielone, bo specy E2E działają tylko lokalnie.
Bramki według §5: `seed`, `import-other-format-without-header`, `month-plan-groups`, `irregular-collected` i nowy spec zatwierdzenia Importu; `retries: 0`, trace przy porażce; spec staje się bramką dopiero po spełnieniu §6.3.
Worktree: .claude/worktrees/e2e-in-ci (gałąź worktree-e2e-in-ci, przestawiona na lokalny development 863a422).
