---
paths:
    - 'routes/*.php'
    - 'app/Http/Controllers/**/*.php'
    - 'resources/js/**/*.vue'
---

# Wayfinder

## Wayfinder is in beta: copy an existing page before using a new option

Routes reach TypeScript as generated functions in `resources/js/actions`, `resources/js/routes` and `resources/js/wayfinder` (gitignored, produced by `npm run build`). Copy the pattern from an existing page and read `vendor/laravel/wayfinder/resources/boost/skills/wayfinder-development/SKILL.blade.php` before using a new option. After changing routes, run `npm run build` before `npm run types:check`, otherwise the type check runs against stale generated files.
