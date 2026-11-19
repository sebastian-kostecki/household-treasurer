# MCP server for the budget (read, then cash entry) — Plan Brief

> Full plan: `context/changes/mcp-read-only-server/plan.md`
> Research: `context/changes/mcp-read-only-server/research.md`

## What & Why

The user wants to ask Claude about the budget without opening the app — balances, Spendable Liquidity and Total funds, To Spend this month, plan vs actual for Categories and Category Groups, recent operations — first from Claude Code, then from the phone, and to record cash operations by voice or text. An MCP server gives Claude these answers straight from the user's own data, computed by the same code as the app.

## Starting Point

The readers exist in `app/Support` and read no session; some orchestration (current-year Budget, To Spend chain, month timing, operations rows) sits in controllers, month group sums exist only in TypeScript, and hand-entry validation is bound to HTTP FormRequests. `laravel/mcp` is only a dev dependency of Boost; the app has Fortify session auth only.

## Desired End State

Claude Code answers from local data over stdio. On production, `https://budget.kostecki.dev/mcp` with Passport OAuth works as a claude.ai custom connector on web, Desktop and phone after the normal login and a Polish consent page. With the server switch on, Claude records a cash expense, refund or income after the user approves it, never twice for one `request_id`, and tells the new balance and To Spend.

## Key Decisions Made

| Decision | Choice | Why (1 sentence) | Source |
| --- | --- | --- | --- |
| Timing | Whole change in M-5 as S-47 | User decision; roadmap gets S-47 and an exception note on the parked line | Research (user) |
| Scope | Local read → production read → cash write | User decision 1a | Research (user) |
| Auth | Passport OAuth, no Sanctum stage | Static headers are beta for limited organizations; OAuth is the documented path to the phone | Research |
| Dependencies | laravel/mcp ^1.2 in Phase 1, Passport ^13 in Phase 3, one ADR-0110 | Each package arrives with the code that uses it | Plan |
| Deploy window | Read endpoint and write tool on production before ~15.12 | User chose option A; write path gets full entry tests on SQLite and PostgreSQL | Plan |
| Month group sums | Port ADR-0079 read-mode rule to PHP with a parity test | Claude must not sum Category rows itself | Plan |
| Year without Budget in execution | Say so with the App Day, year and phase; balances still answer | Same reading as the Dashboard; no closed month posing as current | Plan |
| Consent screen | Inertia page in Atrament (`auth/OAuthAuthorize`) | Hard UI rule; part of the existing login path | Plan |
| Cash kinds | Expense, refund and income; kind + unsigned amount | Same validation and Action cover all three; the model never picks a sign | Plan |
| Duplicates | Separate `mcp_operation_requests` table, unique `(user_id, request_id)` | Real protection without touching the ledger or the snapshot format | Plan |
| After-write answer | `MonthToSpend` before/after, warn when `after < 0 && after < before` | Same reading as the Dashboard, no third calculator | Plan |
| Read vs write | One scope `mcp:use`, forced approval, `MCP_WRITE_ENABLED` switch (default off) | Write can be turned off in `.env.prod` plus `scripts/artisan.sh config:cache`, without a code deploy | Plan |
| Extraction vs copy | Extract shared readers to `app/Support` in Phase 1 | No duplicate logic in `app/Mcp` | Research |
| Local server | Registered in the user's own Claude Code config, user pinned by `MCP_LOCAL_USER` in `local` only | Not started in every worktree session | Plan |

## Scope

**In scope:**
- ADR-0110, tech stack, roadmap S-47, `.ai/rules/mcp.md`
- Shared readers: `BudgetForYear`, `CurrentMonthReading`, `MonthTiming`, `FinancialOperationRows`, `MonthPlanGroupSums`
- Six read tools, one write tool, local stdio and HTTP `/mcp`
- Passport, discovery, DCR limited to Claude redirects, consent page, login/2FA/passkey redirect fix, rate limit, `mcp` call log, client cleanup
- HTTP-free hand-entry validation; key table; production rollout and Cloudflare rules

**Out of scope:**
- Sanctum, custom scopes, other write tools (plans, import, edits, deletes, own transfers)
- Port of the TS entry warning (later Irregular months are not warned about)
- Year fallback in tools, reports tools, MCP resources/prompts, streaming
- Changes to the import validator, `SelectedBudgetYear`, `ShellState`, `DashboardNotices`, `ReportController`

## Architecture / Approach

`routes/ai.php` registers `LocalTreasurerServer` (a subclass that only pins the user in `boot()`) with `Mcp::local` (stdio) and `TreasurerServer` with `Mcp::web('/mcp')` behind `auth:api` (Passport), `throttle:mcp` and `LogMcpCall`. Tools in `app/Mcp/Tools` start from `$request->user()` relations, call `app/Support` readers with `AppDay::today()`, and return minor units plus display strings and `app_day`. The write tool calls `App\Actions\RecordCashOperation`, which validates through the Phase 5 rules and writes through `ManageFinancialOperation`.

## Phases at a Glance

| Phase | What it delivers | Key risk |
| --- | --- | --- |
| 1. Foundations and shared readers | ADR, roadmap, rule, laravel/mcp, readers in `app/Support` | Collision with S-41 in `DashboardController` |
| 2. Local read server | Six read tools over stdio, month group sums with parity | Answers diverging from the pages |
| 3. OAuth through Passport | `/mcp`, discovery, consent page, login fix, limits, logs | Inertia XHR login ending in an external redirect |
| 4. Read endpoint on production | Connector on claude.ai, Desktop and phone | Cloudflare challenging Anthropic's egress |
| 5. Validation without HTTP | Shared expense/income rules, no behaviour change | Regressions in every hand entry |
| 6. Cash write tool | Idempotent cash entry behind switch and approval | A wrong entry in the ledger before the year turn |

**Prerequisites:** S-39 done (App Day); Cloudflare panel access; `.env.prod` access on `halina175`; merge `development` into the branch before Phase 1.
**Estimated effort:** ~6–8 sessions across 6 phases (about 1 + 1.5 + 2 + 0.5 + 1 + 1.5 days).

## Open Risks & Assumptions

- Bot Fight Mode on a free Cloudflare plan cannot be skipped by path; if it is on, it must be turned off for the whole zone.
- Whether Claude Code reuses its registered client and Passport skips consent is unverified; the login fix covers that path either way.
- Adding a connector from inside the mobile app is undocumented; the plan adds it on claude.ai and uses it on the phone.
- Production deploys of Phases 4 and 6 share the pre-15.12 window with S-40, S-41 and S-43.

## Success Criteria (Summary)

- From the phone, Claude answers To Spend, balances, plan vs actual and recent operations with the same numbers as the app.
- A cash expense spoken on the phone is recorded once, after approval, with the new balance and To Spend in the answer.
- No answer contains another user's data, and every answer names the App Day it used.
