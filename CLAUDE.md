@AGENTS.md

## Claude Code

- Boost MCP starts through `.mcp.json` → `.claude/hooks/boost-mcp.sh`. In a fresh worktree that script, like the `EnterWorktree` hook in `.claude/settings.json`, first runs `composer install`, copies `.env` from `.env.example` and runs `key:generate`. If the Boost tools are still missing afterwards, reconnect the server in `/mcp`; the fallbacks are in `.docs/agents/without-boost.md`.
- Path-scoped rules from `.ai/rules/` load on their own through the `.claude/rules/project` symlink when you read or edit a matching file. Edit the rule in `.ai/rules/`; `.claude/rules/` is only the link.
- Local notes and course material (10x-cli lesson blocks) belong in `CLAUDE.local.md`, which is gitignored and loaded next to this file. When a lesson writes its block into this file, move the block there.
