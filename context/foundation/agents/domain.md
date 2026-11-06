# Domain docs

This is a single-context repo; agent-facing docs live under `.docs/` (not `docs/`).

## Before exploring, read

- `CONTEXT.md` at the repo root: the glossary and the rules behind each term, with the synonyms to avoid.
- `.docs/adr/`: the ADRs that touch the area you are about to work in (grep the term, then read the latest numbers first; a later ADR can supersede part of an earlier one).

## Use the glossary's vocabulary

When your output names a domain concept (an issue title, a refactor proposal, a hypothesis, a test name), use the term as defined in `CONTEXT.md` and skip the synonyms it marks _Avoid_. A concept missing from the glossary is a signal: either you are inventing language the project does not use, or there is a real gap to resolve with `/mattpocock-skills:domain-modeling`, which edits `CONTEXT.md` and writes ADRs.

## Flag ADR conflicts

If your output contradicts an existing ADR, say so instead of silently overriding it:

> _Contradicts ADR-0007 (…), but worth reopening because…_
