# Bank on Account is not Import format

A bank Account names an institution (mBank, BNP Paribas, or Other) so the UI can show it wherever the Account appears. Import format stays the file layout of an export; we do not infer or pre-select format from Bank in this change, and we do not put Cash in the Bank list (Cash is already AccountType). We rejected a two-value-only Bank (it would block FR-ACC-01 for any other institution) and a merged Bank/Import-format enum (same two brand names, different jobs).
