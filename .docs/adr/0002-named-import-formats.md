# Named Import formats, not a bank API and not generic-only parsing

Import must handle real bank exports (mBank CSV with a preamble, BNP Paribas XLSX) without becoming a live bank integration (out of MVP). We ship named Import formats that know header location and columns, plus an Other CSV fallback with manual mapping. We rejected generic-only column mapping (too much friction on known banks) and a closed list with no fallback (every new bank would block Import until we ship code).
