# Cash Expectation does not earmark Available Limit; date year matches Category Budget, draft allowed

An unpaid Cash Expectation does not change Execution, Available Limit, or Planned Balance. Available Limit stays Current Plan minus booked expenses. The Cash Expectation’s date year must equal its Category’s Budget year; that Budget may be draft or execution; a missing year is invalid. A Category named by a Cash Expectation cannot be deleted or have its type changed.

We rejected earmarking (budget reserves, out of MVP) and the Financial Operation rule that the year must already be in execution (that would block next-year Agenda rows).
Amended by ADR-0063: the date year no longer has to equal the Category’s Budget year; the year of the date must have a Budget (draft or execution) and the Category joins it as a Budget Line. The block on deleting and on changing the type stays.
