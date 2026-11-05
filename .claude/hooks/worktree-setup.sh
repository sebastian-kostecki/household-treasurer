#!/usr/bin/env bash
# PostToolUse hook for EnterWorktree: prepares a fresh worktree so that
# `php artisan boost:mcp` (.mcp.json) can start there.
# Steps follow AGENTS.md "Fresh checkout or worktree": composer install,
# .env from .env.example, key:generate. Idempotent, does nothing without an
# artisan file, and never fails the tool call.

input=$(cat)
project_dir="${CLAUDE_PROJECT_DIR:-$(pwd)}"

# Find the worktree path: tool response, tool input, conventional location, hook cwd.
name=$(jq -r '.tool_input.name // empty' <<<"$input")
candidates=(
  "$(jq -r '.tool_response.worktreePath // .tool_response.worktree_path // .tool_response.path // empty' <<<"$input")"
  "$(jq -r '.tool_input.path // empty' <<<"$input")"
)
[ -n "$name" ] && candidates+=("$project_dir/.claude/worktrees/$name")
candidates+=("$(jq -r '.cwd // empty' <<<"$input")")

worktree=""
for dir in "${candidates[@]}"; do
  if [ -n "$dir" ] && [ -f "$dir/artisan" ]; then
    worktree="$(cd "$dir" && pwd -P)"
    break
  fi
done

[ -z "$worktree" ] && exit 0

cd "$worktree" || exit 0
log=()

if [ ! -f vendor/autoload.php ]; then
  if composer install --no-interaction --no-progress --prefer-dist >/dev/null 2>&1; then
    log+=("composer install")
  else
    log+=("composer install FAILED")
  fi
fi

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env && log+=(".env from .env.example")
fi

if [ -f .env ] && [ -f vendor/autoload.php ] && ! grep -Eq '^APP_KEY=.+' .env; then
  if php artisan key:generate --no-interaction >/dev/null 2>&1; then
    log+=("key:generate")
  else
    log+=("key:generate FAILED")
  fi
fi

if [ ${#log[@]} -eq 0 ]; then
  msg="Worktree $worktree already prepared (vendor, .env, APP_KEY)."
else
  msg="Worktree $worktree prepared: $(IFS=','; echo "${log[*]}"). Run /mcp to reconnect laravel-boost."
fi

jq -n --arg msg "$msg" '{systemMessage: $msg, hookSpecificOutput: {hookEventName: "PostToolUse", additionalContext: $msg}}'
