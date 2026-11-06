# Domain docs

Project documentation lives under `context/` (10x layout): `foundation/` holds the documents that outlive any one change, `changes/<id>/` the change in progress, `archive/` closed changes.

## Before exploring, read

- `context/foundation/glossary.md`: the glossary and the rules behind each term, with the synonyms to avoid.
- `context/foundation/adr/`: the ADRs that touch the area you are about to work in (grep the term, then read the latest numbers first; a later ADR can supersede part of an earlier one).

## Use the glossary's vocabulary

When your output names a domain concept (an issue title, a refactor proposal, a hypothesis, a test name), use the term as defined in `context/foundation/glossary.md` and skip the synonyms it marks _Avoid_. A concept missing from the glossary is a signal: either you are inventing language the project does not use, or there is a real gap. Close the gap inside the current 10x change (`context/changes/<id>/`): a glossary entry with its _Avoid_ list and a new ADR in `context/foundation/adr/` come before code, as the hard rule in `AGENTS.md` says. Name the gap in the plan phase that introduces the term.

## Flag ADR conflicts

If your output contradicts an existing ADR, say so instead of silently overriding it:

> _Contradicts ADR-0007 (…), but worth reopening because…_
