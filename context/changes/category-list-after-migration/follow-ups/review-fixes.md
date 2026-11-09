# Review fixes — category-list-after-migration

Źródło: `reviews/impl-review.md` (pełny przegląd, 2026-11-09).

## F1 — Próba na kopii produkcji (faza 7)

- [ ] Świeże archiwum produkcji → `backup:restore` do `restore_trial` (README „Przywrócenie produkcji”).
- [ ] `app:control-numbers --output=before.json` gałęzią S-18 przed `migrate`.
- [ ] `migrate`, `app:control-numbers --compare=before.json` = 0 różnic; przejście przez Budżet 2026, plan miesiąca, Operacje, Import, „Kategorie”.
- [ ] `migrate:rollback`, `--compare` = 0 różnic; ponowna migracja.
- [ ] Zapis `context/changes/category-list-after-migration/migration-trial.md` według kontraktu fazy 7 (bez danych osobowych).
- [ ] `README.md:168`: zdanie „jeszcze nie ćwiczono” zastąpione odnośnikiem do próby.
- [ ] Odhaczyć 7.1–7.5 w `plan.md` dopiero po powyższym.
