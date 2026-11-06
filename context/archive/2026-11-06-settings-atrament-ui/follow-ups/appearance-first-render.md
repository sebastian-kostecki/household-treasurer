# Follow-up: przeskok zaznaczenia motywu przy pierwszym renderze

Sprawdzenie z planu (faza 4, punkt 4.6), 2026-11-06, podgląd worktree (`php artisan serve`, Chromium przez Playwright, 1280 px).

## Wynik

Przeskok występuje. Po wybraniu „Ciemny” (`localStorage.appearance = dark`, cookie `appearance=dark`) i odświeżeniu `/settings/appearance`:

- pierwszy render `SegmentedControl` (~200 ms po starcie strony): aktywna opcja „Systemowy”;
- po `onMounted` w `useAppearance`: aktywna opcja „Ciemny”.

Motyw strony (`html.dark`) jest poprawny od początku. Przeskakuje tylko zaznaczenie w przełączniku, przez jedną klatkę.

## Przyczyna

`resources/js/composables/useAppearance.ts`: moduł trzyma `const appearance = ref<Appearance>('system')`, a zapisany wybór czyta z `localStorage` dopiero w `onMounted`. Pierwszy render komponentu widzi więc wartość domyślną.

## Propozycja (poza tą zmianą)

Zainicjować `appearance` z zapisanego wyboru przed pierwszym renderem: z `localStorage` po stronie klienta albo z cookie `appearance`, które serwer już zna (`HandleAppearance`). Do tego test jednostkowy composable'a. To nie jest regresja tej zmiany, bo stare `AppearanceTabs` miały ten sam przeskok.

- [ ] F1 — `useAppearance`: stan początkowy z zapisanego wyboru zamiast `'system'` + test.
