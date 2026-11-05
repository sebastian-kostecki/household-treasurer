#!/usr/bin/env bash
# Starts the laravel-boost MCP server for .mcp.json.
# After EnterWorktree, Claude Code restarts the server in the new worktree
# straight away, and a fresh worktree has no vendor/ or .env, so
# `php artisan` died at once (CONNECTION_CLOSED). The PostToolUse hook may
# not have finished by then, so prepare the worktree here as well.
# stdout belongs to the MCP protocol: all setup output goes to stderr.

cd "$(dirname "$0")/../.." || exit 1

if [ ! -f vendor/autoload.php ] || [ ! -f .env ]; then
  jq -n --arg path "$PWD" '{tool_input: {path: $path}}' \
    | bash .claude/hooks/worktree-setup.sh >&2
fi

exec php artisan boost:mcp
