# Review fixes — category-list-after-migration

Źródło: `reviews/impl-review.md` (pełny przegląd, 2026-11-09).

## F1 — Próba na kopii produkcji (faza 7)

- [x] Świeże archiwum produkcji → `backup:restore` do `restore_trial` (README „Przywrócenie produkcji”).
- [x] `app:control-numbers --output=before.json` gałęzią S-18 przed `migrate`.
- [x] `migrate`, `app:control-numbers --compare=before.json` = 0 różnic; przejście przez Budżet 2026, plan miesiąca, Operacje, Import, „Kategorie”.
- [x] `migrate:rollback`, `--compare` = 0 różnic; ponowna migracja.
- [x] Zapis `context/changes/category-list-after-migration/migration-trial.md` według kontraktu fazy 7 (bez danych osobowych).
- [x] `README.md:168`: zdanie „jeszcze nie ćwiczono” zastąpione odnośnikiem do próby.
- [x] Odhaczyć 7.1–7.5 w `plan.md` dopiero po powyższym.

Zrobione 2026-11-10: `migration-trial.md`.
