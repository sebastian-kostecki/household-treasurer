<!-- IMPL-REVIEW-REPORT -->
# Implementation Review: Konta według strony design systemu (ADR-0045)

- **Plan**: context/changes/accounts-design-system-page/plan.md
- **Scope**: Full plan
- **Reviewed phases**: 1, 2, 3
- **Date**: 2026-11-06
- **Verdict**: NEEDS ATTENTION
- **Findings**: 0 critical, 3 warnings, 2 observations

## Verdicts

| Dimension | Verdict |
|-----------|---------|
| Plan Adherence | WARNING |
| Scope Discipline | WARNING |
| Safety & Quality | WARNING |
| Architecture | PASS |
| Pattern Consistency | WARNING |
| Success Criteria | PASS |

## Findings

### F1 — Formatting `.ai/rules/index.md` broke the globs

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Scope Discipline
- **Location**: .ai/rules/index.md:7-21 (commit 6de2267), vite.config.ts:87
- **Detail**: In Phase 2 the gate fix for `npm run check` ran `vp fmt` on the index. The formatter reads `*…*` as emphasis and rewrote the globs, for example `components/*Dialog.vue` → `_Dialog.vue`, `routes/*.php` → `routes/_.php` and `Controllers/**/*.php` → `\*\*/_.php`. The rows js-dialogs, js-sidebar, reports, wayfinder and inertia-props no longer match real files. Loading rules through the `paths:` frontmatter still works. This is a ping-pong between `boost:index-rules` (unformatted table) and the formatter (740c31b broken → 0d071b4 clean → 6de2267 broken). `fmt.ignorePatterns` does not cover `.ai/**`.
- **Fix**: Restore `.ai/rules/index.md` from 0d071b4 and add `.ai/rules/index.md` to `fmt.ignorePatterns` in `vite.config.ts`; `npm run check` passes and `boost:index-rules` gives no diff.
- **Decision**: FIXED

### F2 — Typeahead in the account and bank selects matches the short mark, not the name

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Safety & Quality
- **Location**: resources/js/components/AccountSelect.vue:74-84, resources/js/components/AccountBankSelect.vue:62-67
- **Detail**: Reka `SelectItem` takes `textValue` from `textContent` when the prop is missing (`reka-ui/dist/Select/SelectItem.js:100`, `shared/useTypeahead.js:14,64` with `startsWith`). `aria-hidden` does not remove the tile's text from `textContent`, so an option now reads „mBKonto osobiste” or „złPortfel”. Typing „K” or „P” in the account select (operation dialogs, import) no longer finds the account by name. In the bank select only „LF” (Lokaty Facto) is affected. Before the change the tile was an `<img>` or svg with no text.
- **Fix**: Add `:text-value="account.name"` on `SelectItem` in `AccountSelect` and `:text-value="bankName(bank)"` in `AccountBankSelect`.
- **Decision**: FIXED

### F3 — „logo” still in the description of `Select` / `AccountBankSelect`

- **Severity**: ⚠️ WARNING
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Plan Adherence
- **Location**: context/foundation/design-system.md:16, .ai/rules/js.md:49
- **Detail**: Both places say `Select` (Reka) is for options with „ikonę lub logo (np. `AccountBankSelect`)”. After ADR-0047 `AccountBankSelect` shows a short mark or `Building2`, so this sentence contradicts the ADR (manual check 1.4).
- **Fix**: Change „ikonę lub logo” to „ikonę lub znak” (and „icon or logo” to „icon or mark” in js.md).
- **Decision**: FIXED

### F4 — `mark-label` missing from the list of named styles in `.ai/rules/css.md`

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: .ai/rules/css.md:14
- **Detail**: The rule lists typography styles by name (`page-title` … `amount-sum`) and says „Add a new style … here”. `mark-label` is in app.css and in the design-system.md table, but not in the rule.
- **Fix**: Add `mark-label` to the list in `.ai/rules/css.md:14`.
- **Decision**: FIXED

### F5 — ADR-0044 has no back-reference to ADR-0047

- **Severity**: 💡 OBSERVATION
- **Impact**: 🏃 LOW — quick decision; fix is obvious and narrowly scoped
- **Dimension**: Pattern Consistency
- **Location**: context/foundation/adr/0044-accounts-table-follows-atrament.md:13
- **Detail**: ADR-0047 „Supersedes in part ADR-0044”. ADR-0044 points back only to ADR-0045, which is the convention for superseded decisions.
- **Fix**: Add „Superseded in part by ADR-0047 (short mark instead of the logo)” to ADR-0044, next to the ADR-0045 reference.
- **Decision**: FIXED
