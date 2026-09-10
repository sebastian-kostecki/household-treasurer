# Geist typeface with latin-ext subsets

UI type is Geist via Bunny Fonts (`weights` 400/500/600, `subsets` `latin` + `latin-ext`), not the Laravel starter’s Instrument Sans. ADR-0001’s “fonts stay on the starter stack” clause is superseded: colour and density still define the OpenShip look (ADR-0001, ADR-0003), but Polish copy needs a latin-ext shard so diacritics do not fall back to system UI mid-word. Amounts keep `tabular-nums` on the UI face; Geist exposes `tnum`, so a second monospace family is unnecessary.

We rejected keeping Instrument Sans with only a subset fix (still a starter face with weaker operator density), Inter (neutral but farther from OpenShip/Vercel language), and a dedicated mono for amounts (extra font file with no alignment gain beyond `tnum`).
