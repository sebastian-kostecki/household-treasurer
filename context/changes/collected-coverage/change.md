---
change_id: collected-coverage
title: Collected coverage
status: implementing
created: 2026-11-13
updated: 2026-11-13
archived_at: null
---

## Notes

Kontrola wstępna bramek (2026-11-13, worktree `.claude/worktrees/collected-coverage`, gałąź oparta na `development` `e38c7ed`):

- Przed fazą 2: utworzyć pustą bazę `testing_collected_coverage` (`context/foundation/agents/parallel-changes.md`); testy na PostgreSQL poleceniem worktree z AGENTS.md.
- Przed fazą 3: `npm ci` i `npm run build` (w worktree nie ma `node_modules`); Node z `~/.nvm/versions/node/v24.21.0/bin`.
- Faza 4, E2E: własna baza `e2e_collected_coverage` i wolny port 8100–8199.
- Na bazie dev nie ma kategorii nieregularnej; ręczna weryfikacja wymaga oznaczenia jednej.
