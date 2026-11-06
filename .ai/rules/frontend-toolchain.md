---
paths:
    - 'resources/js/**/*.test.ts'
    - 'vite.config.ts'
    - 'package.json'
    - 'tsconfig.json'
---

# Frontend toolchain

## Everything runs through Vite+ (`vp`); there is no separate ESLint, Prettier or Vitest

Static checks: `npm run check` (autofix `npm run check:fix`); warnings fail the run. Tests: `npm test`. Keep `*.test.ts` next to the code, import from `vite-plus/test` and use relative imports, because Vite plugins and the `@` alias are off during tests. Confirm options with `npx vp --help`; online docs may describe a newer version than the installed one.

## `vite-plus`, the `vite` alias and `overrides.vite` move together

They must stay on the same version in `package.json`, so upgrade all three in one change. npm is the only package manager: a `pnpm-workspace.yaml` makes `vp migrate` switch the project to pnpm.
