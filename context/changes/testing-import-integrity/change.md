---
change_id: testing-import-integrity
title: Testy integralności Importu i benchmark trafności podpowiedzi kategorii
status: impl_reviewed
created: 2026-11-17
updated: 2026-11-17
archived_at: null
---

## Notes

Open a change folder for rollout Phase 1 of context/foundation/test-plan.md: "Integralność Importu".
Risks covered: #1 (zatwierdzenie Importu po cichu dubluje albo gubi operacje; częściowa porażka nie wycofuje całego zestawu), #4 (spadek trafności podpowiedzi kategorii w Imporcie, którego nikt nie mierzy). Test types planned: integracja (Feature na PostgreSQL z plikami fixture) + benchmark trafności z progiem.
Risk response intent:
- #1: ponowny Import nakładającego się wyciągu nie tworzy żadnej nowej operacji; wariant formatu, preambuły albo układu kolumn daje komplet wierszy; błąd jednego wybranego wiersza wycofuje cały zestaw (liczba operacji i salda kont przed i po równe). Fixture z zanonimizowanych prawdziwych wyciągów, asercje na stanie bazy, nie tylko na odpowiedzi HTTP.
- #4: stały zestaw referencyjny (opis z wyciągu → oczekiwana kategoria) ma trafność co najmniej równą zapisanemu poziomowi bazowemu, a spadek czerwieni bramkę. Korekta względem planu: M-2/S-16 porzucono 2026-11-08 (PRD v3), podpowiedzi działają na jednej liście kategorii (archiwum 2026-11-12-import-suggestions-on-category-list), więc poziom bazowy to zmierzona dziś trafność, nie „przed S-16”. Research ma potwierdzić to przeformułowanie do backportu w §2.
After creating the folder, follow the downstream continuation rule.

## Decyzje użytkownika (planowanie, 2026-11-17)

- Luki z research: A (powtórzony `rows.*.index`) naprawiana w tej zmianie po czerwonym teście (`distinct`); B (wiersz znika po zmianie formatu komórki) i C (BNP ignoruje `Waluta`) jako issue na GitHubie.
- Fixture wyciągów: najpierw istniejące prawdziwe pliki i ręczne warianty; świeże eksporty mBank i BNP od użytkownika w osobnej fazie.
- Zestaw referencyjny trafności: pseudonimy tokenów ze snapshotu `1-20261112070312.json` (1031 wierszy z 2026), bez tekstu z banku i bez kwot.
- Próg trafności: zero spadku (reguła `--compare` z S-23), poprawa przechodzi, bazę podnosi się świadomie.
- Równoczesne podwójne zatwierdzenie: zaakceptowane ryzyko w §7 planu testów.

## Issue (faza 3)

- B (wiersz znika po zmianie formatu komórki): #271
- C (BNP ignoruje `Waluta`): #272
