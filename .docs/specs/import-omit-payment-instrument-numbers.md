# Import omits payment-instrument numbers from Description and Counterparty

## Problem Statement

Bank export rows bury Polish NRB, IBAN, and card PAN (including masked PAN) inside the statement text. On Import those strings become the live Description and sometimes Counterparty, so the Operations list shows payment-instrument numbers the User does not need. A typical BNP Paribas card row looks like a 26-digit NRB on one line and a masked PAN plus merchant text on the next. The User wants the list to read as the merchant and the rest of the narrative, not as account and card numbers. Re-import must still recognise the same file row.

## Solution

Every Import format omits payment-instrument numbers from the live Description and Counterparty at parse, after today’s column mapping and before the 255-character truncate. Raw Statement Description stays the original file-row text (ADR-0005, ADR-0007). If Description is empty after omit, it takes the remaining Counterparty; if both are empty the preview row is invalid, same as a missing description today. Already-committed Import-born live Description and Counterparty are rewritten once with the same omit; Raw Statement Description is not. Hand-entered text is not omitted, on create or on edit. The Raw Statement Description tooltip still shows the numbers.

Worked example (BNP Paribas `Opis`):

- File: `48160014461822964860000001` then `516842------4681 SEBASTIAN KOSTECKI Lublin OWOCOWA BAJKA POL 24,03 PLN 2026-09-14`
- Live Description: `SEBASTIAN KOSTECKI Lublin OWOCOWA BAJKA POL 24,03 PLN 2026-09-14`
- Raw Statement Description: the file text unchanged

## User Stories

1. As a User importing a BNP Paribas card row whose `Opis` starts with a 26-digit NRB and a masked PAN, I want the preview and the saved Financial Operation Description to keep the name, merchant, city, amount, and date and to drop the NRB and the masked PAN, so that the Operations list is readable.
2. As a User importing mBank CSV whose `Opis operacji` ends in a 26-digit NRB, I want that NRB omitted from Description and Counterparty left empty, so that mBank is not a special case.
3. As a User importing format Other with a mapped description (and optional Counterparty) that contains an IBAN or NRB, I want those numbers omitted from the live fields, so that a manual mapping does not leak payment-instrument numbers onto the list.
4. As a User whose BNP Counterparty cell is only an NRB, I want Counterparty absent after Import, so that the other party is not a string of digits.
5. As a User whose Counterparty is a name plus an NRB, I want the name kept and the NRB omitted, so that Counterparty still names the other party.
6. As a User viewing an Import-born Financial Operation, Own Transfer, or Capital Movement, I want Raw Statement Description (the tooltip) to still contain the original numbers, so that I can see what the bank wrote.
7. As a User who re-imports the same file after live Description was omitted, I want the row marked as an Import duplicate, so that omitting live text does not change the frozen file-row identity.
8. As a User whose `Opis` is only an NRB but Nadawca/odbiorca is a name, I want Description to become that name after omit, so that a usable Description remains and the row stays valid.
9. As a User whose Description and Counterparty are only payment-instrument numbers, I want the preview row invalid (missing description) and unselectable, so that Import does not persist an empty Description.
10. As a User who already committed Import-born Financial Operations, Own Transfers, and Capital Movements, I want their live Description (and Counterparty on Financial Operations) rewritten once with the same omit, so that the list is clean without re-importing.
11. As a User with hand-entered records (null Raw Statement Description), I want those live fields left as I typed them, so that a one-shot rewrite cannot touch text I wrote.
12. As a User creating or editing a Financial Operation, Own Transfer, or Capital Movement by hand, I want pasted statement text stored as I entered it, so that omit is an Import behaviour, not a form sanitizer.
13. As a User whose statement uses underscored masked PAN (`516842______4681`), I want that omitted the same as hyphen masks, so that BNP’s export variants stay clean.
14. As a User whose description contains a 16-digit run that fails Luhn, or a hyphenated reference such as `2102625950-0081491110`, I want that text kept, so that invoices, BLIK-adjacent references, and mBank operation ids are not treated as cards.
15. As a User whose row contains `PL` plus a spaced 26-digit NRB, or a foreign IBAN, I want those omitted, so that both Polish and other-country account numbers leave the live fields.
16. As a User whose Description would exceed 255 characters only because of numbers that will be omitted, I want omit to run before truncate, so that a number is never cut in half and missed.
17. As a User looking at Import preview, I want the Description cell to show the omitted live text (still read-only), so that what I commit is what I saw.
18. As a User committing that preview, I want the saved live Description and Counterparty omitted and Raw Statement Description equal to the file row, so that later edits of Description still leave the snapshot intact.
19. As a User whose Import-born Own Transfer or Capital Movement has a Description with an NRB, I want that Description omitted the same way as a Financial Operation, so that every Import-born list row is clean.
20. As a User settling a Cash Expectation from Import, I want Settlement candidate matching unchanged (Description does not gate candidacy), so that omit cannot hide a valid settle.
21. As a User pairing a later Import row as an Own Transfer, I want Import pair candidate matching unchanged (it does not compare Description text), so that omit cannot hide a valid pair.
22. As a User whose backfill would leave Description empty after omit and Counterparty fallback, I want that record’s live fields left as they are, so that a required Description is never stored empty.
23. As a User, I want leftover blank lines and extra spaces after omit collapsed, so that a former NRB-only first line does not leave a leading blank line on the list.
24. As a User, I do not want new UI copy that explains the tooltip, so that the existing Raw Statement Description tooltip remains the only extra surface.

## Implementation Decisions

- Put omit behind one small interface: given optional text, return optional text with payment-instrument numbers removed and leftover whitespace collapsed. The existing Import row-field mapper (all three format entry points) is the only parse caller. Parsers keep sending raw cells; they do not omit.
- Mapping order stays as today, then omit on live Description and Counterparty, then truncate to 255, then the empty-Description fallback to remaining Counterparty. Raw Statement Description is joined from the pre-omit cells and is never truncated by this work.
- Matchers, applied to non-overlapping spans, more specific first so a 26-digit NRB is not partly eaten as a PAN:
  - Polish NRB: 26 digits, optional `PL` prefix, optional spaces.
  - IBAN: ISO 13616 (two-letter country, two check digits, BBAN) at a plausible IBAN length, so `POL` and amount fragments such as `24,03` do not match.
  - Masked PAN: six or more digits, then four or more mask characters (`-` or `_`), then four digits.
  - Unmasked PAN: a 13–19 digit run that passes Luhn and was not already consumed as NRB/IBAN.
- Do not treat a single hyphen between digit groups as a mask (mBank-style `2102625950-0081491110` stays).
- Preview and commit keep copying mapper output from the Import session. No new request fields. No Vue editor for Description.
- One-shot backfill: an Action the data migration calls. For every Financial Operation, Own Transfer, and Capital Movement with a non-null Raw Statement Description on that record (either Own Transfer snapshot counts), omit live Description and, on Financial Operations, Counterparty, then apply the same empty-Description fallback. If Description would still be empty, skip that record. Do not write Raw Statement Description. Do not touch Cash Expectations. Do not change Import duplicate identity columns.
- Hand-create and hand-edit validation stay as they are (`raw_statement_description` prohibited; no omit on those paths).
- No schema change. No new ADR (ADR-0005 and ADR-0007 already fix Raw Statement Description as the snapshot and fingerprint input).
- Update product requirements for mBank, BNP Paribas, and Other so live Description/Counterparty omit payment-instrument numbers while the original statement snapshot stays the file row. Glossary in `CONTEXT.md` already records this.

## Testing Decisions

- A good test asserts externally visible strings: live Description, Counterparty, Raw Statement Description, preview `valid`, persisted columns, and Import duplicate identity. It does not assert regex internals, Luhn helper names, or Vue/Tailwind.
- Primary parse seam: the existing Import row-field mapper unit tests. Extend them with the omit cases (BNP card example, mBank NRB tail, Counterparty-only NRB → null, empty-Opis fallback to Counterparty, both-empty still null, raw unchanged, truncate-after-omit, whitespace collapse, Luhn-fail run kept, hyphenated reference kept, masked PAN with underscores, IBAN with `PL` and spaces). One format entry point is enough for matcher cases; add a thin case per `fromMbank` / `fromBnpParibas` / `fromOther` only to prove each caller omits live fields and leaves raw.
- Matcher-only edge cases may sit on the omit function itself when they are string-in/string-out and would only noise the mapper tests. That function is an implementation detail of the mapper, not a second product seam.
- HTTP seam: existing CSV/XLSX Import feature tests. Update assertions that today expect a 26-digit Counterparty or an NRB inside live Description (split-party BNP Counterparty that is only digits becomes null; mBank fixture live Description drops the NRB tail; raw still contains it). Add one preview+commit case that the user’s BNP card example persists omitted Description, full Raw Statement Description, and that a second preview of the same row is an Import duplicate.
- Backfill seam: the Action, with factories, `RefreshDatabase`. Import-born Financial Operation, Own Transfer, and Capital Movement live fields omitted, raw untouched, identity digest unchanged; hand-entered (null raw) untouched; a record whose Description would become empty is skipped; a second run is a no-op on already-clean text.
- Do not add `pest-plugin-browser` coverage. Do not assert tooltip markup; Raw Statement Description already has list coverage.

## Out of Scope

- Omitting numbers from hand-entered create/edit, or from Cash Expectation text.
- Changing, masking, or hashing Raw Statement Description, or changing the Import duplicate fingerprint.
- Preview editing of Description, new tooltip copy, or hiding numbers in the Raw Statement Description tooltip.
- Stripping names, merchant, city, amount, or date from statement text.
- New Import formats, live bank connections, or deleting historical rows that would be invalid if imported today.
- A second persisted “cleaned description” column.

## Further Notes

- Tracker: https://github.com/sebastian-kostecki/household-treasurer/issues/142 (`ready-for-agent`).
- Glossary: `CONTEXT.md` (Import, Financial Operation, Counterparty, Raw Statement Description, Import duplicate). Decisions this spec relies on: ADR-0005, ADR-0006, ADR-0007.
- Product requirements to extend: `.docs/prd.md` FR-IMP-05, FR-IMP-06, FR-IMP-07 (live fields omit; snapshot stays the file row).
- Visual language unchanged. No new UI chrome.
