# Cash Expectation is edited or deleted while unpaid, then destroyed on settlement

While unpaid, a Cash Expectation may be edited (same fields and validation as create) or deleted; delete does not move Liquidity or Execution. There is no cancel or skip state and no stored overdue status: overdue is date before today. Settlement destroys the record; there is no unpay; editing or deleting the Financial Operation does not recreate a Cash Expectation. An Account named by a Cash Expectation cannot be deleted (archive stays as for a Financial Operation); its opening date cannot be later than the earliest Cash Expectation on that Account.

We rejected a stored status, a cancelled/skipped list, keeping a closed row for unpay, resurrecting the Cash Expectation when a spawned Financial Operation is deleted, and letting Account opening date move past an unpaid row.
