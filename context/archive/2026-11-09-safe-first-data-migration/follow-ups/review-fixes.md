# Follow-ups z przeglądu implementacji

- [ ] **F1:** Ustawić `BACKUP_ARCHIVE_PASSWORD` w `.env.prod` na VPS. Dziś archiwa z produkcji są najpewniej jawne (próba przywracania przeszła z fałszywym hasłem), a każde wdrożenie dokłada pełną kopię (dump + `storage/app`). Po ustawieniu hasła zapisać je poza serwerem: bez niego `backup:restore` nie otworzy archiwum. Starsze, jawne archiwa zostają do wygaśnięcia retencji.
- [ ] **F3:** Plan S-18 ma przećwiczyć kroki „Przywrócenie produkcji” z README na bazie testowej przed pierwszą nieodwracalną migracją.
