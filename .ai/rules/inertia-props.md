---
paths:
    - 'app/Http/Controllers/**/*.php'
    - 'resources/js/pages/**/*.vue'
---

# Inertia props

## Both sides of a prop change in the same commit, asserted by a Feature test

Props are PHP arrays in controllers and hand-written TypeScript types in `defineProps<>`; `vue-tsc` cannot see the PHP side. When you add, rename or change a prop, change the controller and the page type in the same commit and assert the prop with `assertInertia` in a Feature test.
