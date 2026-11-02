## Project facts

- CI, Sail and production run PHP 8.5. The host CLI is 8.4, and the locked dependencies need at least 8.4.1, so write code that runs on 8.4.1.
- Run project commands through Sail, for example `./vendor/bin/sail artisan test --compact`. In a worktree, the running Sail stack serves the main checkout, not your code, so run the Sail image directly (see "Test database"). If you use the host CLI, say so in your report.
- npm is the only JS package manager. Do not add `pnpm-workspace.yaml`, because `vp migrate` reads it and switches the project to pnpm.
- Docs describe more than the code. Confirm the code exists before relying on a term from `CONTEXT.md`, `.docs/prd.md` or `.docs/specs/`. Agenda, Cash Expectation, Reserve and Year Close are not implemented on `main`.

## Money

Amounts are integers in minor units: `*_minor_units` columns (`bigint`) and `int` in PHP, with `float` kept out of money code. Parse user input with `App\Support\MinorUnits`. In Vue, follow `.ai/rules/js.md`.

## Test database

`phpunit.xml` defaults to in-memory SQLite, while CI and production use PostgreSQL, so a green SQLite run proves nothing about PostgreSQL. For migrations, raw SQL, date or JSON functions, and behavior that depends on constraints, run the affected tests on PostgreSQL:

`docker compose exec -u sail -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing laravel.test php artisan test --compact <path>`

That command fails from a worktree. With Sail running and after the fresh-checkout steps, run the Sail image on the worktree instead. It gives the same PHP 8.5, Node and PostgreSQL as CI:

`docker run --rm -u 1000:1000 -e HOME=/tmp -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing -e DB_USERNAME=sail -e DB_PASSWORD=password --network household-treasurer_sail -v "$PWD":/var/www/html -w /var/www/html --entrypoint php sail-8.5/app artisan test --compact <path>`

For the full CI gate, replace `--entrypoint php sail-8.5/app artisan test --compact <path>` with `--entrypoint composer sail-8.5/app ci:check`. CI also runs `vendor/bin/sloppy ci` as a separate step, outside `ci:check`; run `vendor/bin/sloppy ci` on the host before finishing PHP changes. Fix new findings instead of regenerating `.sloppy-baseline.json`.

## Inertia props contract

Props are PHP arrays in controllers and hand-written TypeScript types in `defineProps<>`, and `vue-tsc` cannot see the PHP side. When you add, rename or change a prop, change both sides in the same commit and assert the prop with `assertInertia` in a Feature test.

## Month logic lives twice

`app/Support/MonthRemainingCalculator.php` and `resources/js/lib/month-remaining-calculator.ts` carry the same calculation, and `hand-entry-month-remaining-preview.ts` and `import-month-remaining-warnings.ts` build on the TS copy. The calculators are covered by `tests/Unit/MonthRemainingCalculatorTest.php` and `resources/js/lib/month-remaining-calculator.test.ts`; the two preview files have no tests. Change both copies and both tests together. Compute new month readings on the server and pass them as props; add a TS copy only when a preview must react before submit, and state the reason in the plan.

`app/Support/ToSpendCalculator.php` and `resources/js/lib/to-spend-calculator.ts` are a second pair, covered by `tests/Unit/ToSpendCalculatorTest.php` and `resources/js/lib/to-spend-calculator.test.ts`. Change both copies and both tests together. The TS copy exists for the live preview on the month plan page: a proposal cannot be saved before approval, so the preview must react before submit.

## Wayfinder

Wayfinder is in beta. Copy the pattern from an existing page and read `vendor/laravel/wayfinder/resources/boost/skills/wayfinder-development/SKILL.blade.php` before using a new option. After changing routes, run `npm run build` before `npm run types:check`.

## Frontend toolchain

Static checks run through Vite+ (`vp`): `npm run check`, with autofix through `npm run check:fix`. Warnings fail the run. CI also runs `npm run types:check` for Vue templates. Frontend tests run through `npm test` (`vp test`): keep `*.test.ts` next to the code, import from `vite-plus/test`, and use relative imports, because Vite plugins and the `@` alias are off during tests. Stay within `vp`, because this project has no separate ESLint, Prettier or Vitest setup. `vite-plus`, the `vite` alias and `overrides.vite` in `package.json` must stay on the same version, so upgrade them together. Confirm options with `npx vp --help`, because online docs may describe a newer version than the installed one.

## Fresh checkout or worktree

Before running checks, follow the CI order: `composer install`, copy `.env.example` to `.env`, `php artisan key:generate`, `npm ci`, `npm run build`. The build generates the Wayfinder directories (`resources/js/actions`, `resources/js/routes`, `resources/js/wayfinder`), which `npm run types:check` needs.

## Preview from a worktree

Manual checks of a phase need a running app that serves the worktree code. The Sail stack serves the main checkout, so before asking the user to confirm manual checks, build and start a preview and give the address and login:

1. In the worktree `.env` set `DB_DATABASE` to a separate database (`create database <name>` in the `household-treasurer-pgsql-1` container). Never point it at `household_treasurer`: migrating it changes the main checkout's schema.
2. Run `php artisan migrate --seed --force` with the Sail image on the `household-treasurer_sail` network. `DemoHouseholdSeeder` creates `test@example.com` / `password` with a budget in execution.
3. Run `npm run build` again after every Vue or route change. The preview serves the built assets, and the Wayfinder step makes the build take a few minutes.
4. Start `php artisan serve --host=0.0.0.0 --port=8000` in the Sail image with `-d -p 8090:8000 --network household-treasurer_sail` and the worktree mounted at `/var/www/html`, then open http://localhost:8090.
5. Stop it with `docker rm -f <container>` and drop the database after the change is merged.

## When Boost tools are not available

Boost MCP is configured for Claude Code, and `.ai/rules/boost` does not exist. Claude Code starts it with host PHP through `.mcp.json`, so a worktree needs `composer install` and `.env`, and the database tools need the Sail PostgreSQL container running. If the Boost tools are missing from your tool list:

- Package docs: check the installed version, then read the matching official docs: `laravel.com/docs/13.x`, `inertiajs.com/docs/v3`, `pestphp.com/docs`.
- Skills: the project skill is `.ai/skills/openship-ui-development/SKILL.md`. Package skills are not tracked: find `<skill-name>/SKILL.blade.php` under `vendor/laravel/boost/.ai/` or `vendor/laravel/*/resources/boost/skills/`.
- Schema: read `database/migrations/`.
- Durable rules: edit `.ai/rules/<area>.md` and add its row to `.ai/rules/index.md`.

## Agent skills

### Issue tracker

Issues live in this repo’s GitHub Issues (`gh` CLI). See `.docs/agents/issue-tracker.md`.

### Triage labels

Canonical roles map 1:1: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. See `.docs/agents/triage-labels.md`.

### Domain docs

single-context. See `.docs/agents/domain.md`.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:

- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
    - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Test every code change by adding or updating a test.
- Run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.

- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
