# When Boost tools are not available

Boost MCP is configured for Claude Code in `.mcp.json` (`boost.json`: `"agents": ["claude_code"]`). The database tools need the Sail PostgreSQL container running. If the Boost tools are missing from your tool list, use these fallbacks:

- Package docs: check the installed version (`composer show <vendor/package>`, `package.json`), then read the matching official docs: `laravel.com/docs/13.x`, `inertiajs.com/docs/v3`, `pestphp.com/docs`.
- Skills: `php artisan boost:update` copies the skills listed in `boost.json` into `.claude/skills/<name>/` (gitignored; the project skill `.ai/skills/openship-ui-development` is linked there). Without that step, find `<skill-name>/SKILL.blade.php` under `vendor/laravel/boost/.ai/` or `vendor/laravel/*/resources/boost/skills/`.
- Schema: read `database/migrations/`.
- Durable rules: edit `.ai/rules/<area>.md` (frontmatter `paths:` with globs) and run `php artisan boost:index-rules` to refresh `.ai/rules/index.md`. `.ai/rules/boost` does not exist.
