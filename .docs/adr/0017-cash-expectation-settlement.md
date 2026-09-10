# Cash Expectation settlement spawns or attaches one Financial Operation

A Cash Expectation is not a Financial Operation. Hand-entry settlement creates a new Financial Operation (prefilled, editable; date defaults to today) and the Cash Expectation is destroyed — it is not converted in place and not kept for unpay. Import does not auto-settle: the User opts in on the preview. If a Financial Operation that already is that movement exists, settle from the Cash Expectation attaches it and must not spawn a second one or rewrite the FO.

We rejected converting in place (the record is not an Operation), leaving a paid Cash Expectation on Agenda, and auto-matching Import (silent double-book or silent settle). The match rule for candidates is ADR 0020. Lifecycle after create (edit, delete, derived overdue, no unpay) is ADR 0021. A spawned amount that differs from the Cash Expectation is a correction, not a remainder (ADR 0022).
