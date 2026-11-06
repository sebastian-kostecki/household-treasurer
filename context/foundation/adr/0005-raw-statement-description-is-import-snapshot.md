# Raw Statement Description is an import-only snapshot

A Financial Operation or Own Transfer created by Import keeps an immutable Raw Statement Description: the original file-row text, not a second editable description and not a column on the Operations list. Hand-created records leave it null. Later edits of description or Counterparty do not change it. We rejected an editable copy, a visible list column, and snapshotting only the bank “Opis” cell (that would drop BNP Nadawca after the User edits Counterparty).
