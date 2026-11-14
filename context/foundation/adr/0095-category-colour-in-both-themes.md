# Category colour in both themes: a tile from two tokens, a full swatch

The category tile took the colour from the data and glued an alpha to it (`${color}26`, 15%) with the icon in the plain colour. The 1.4.3 audit (C2) measured the icon against the tile on `card`: at least 3:1 for 9 of 24 colours in the light theme (`#eab308` has 1.74:1) and 23 of 24 in the dark theme (`#be185d` has 2.70:1). In the colour picker the tinted swatches were nearly the colour of the card in the dark theme (1.08 to 1.33:1) and could not be told apart.

Rule. The category colour reaches CSS as one custom property, `--category-color`, set by `categoryTileStyle(color)` and nothing else. Two named classes in `@layer components` of `resources/css/app.css` build the colours:

- `.category-tile`: background `color-mix(in srgb, var(--category-color) var(--category-tile-tint), transparent)`, icon colour `color-mix(in srgb, var(--category-color) var(--category-icon-tint), var(--foreground))`;
- `.category-swatch`: background `var(--category-color)`, the full colour, for the swatches of `CategoryColorPicker`.

The tokens `--category-tile-tint` and `--category-icon-tint` have a value in `:root` and in `.dark`. Chosen by calculation (tile 15% on both themes, as before):

| Theme | `--category-tile-tint` | `--category-icon-tint` | Lowest icon contrast against the tile on `card` |
| --- | --- | --- | --- |
| light | 15% | 60% | 3.93:1 (`#eab308`) |
| dark | 15% | 80% | 3.47:1 (`#be185d`) |

The mix is in `srgb`, a linear interpolation of the encoded components; mixing with `transparent` gives an alpha that the test composes on `--card`. `oklab` would give other numbers than the test computes. `tests/Unit/CategoryTileContrastTest.php` reads the 24 colours of `CategoryColors`, `--card`, `--foreground` and both shares from `app.css` and requires at least 3:1 for all 48 pairs (24 colours, 2 themes), naming the colour and the theme on failure. Before the change it was red for 16 pairs (15 in the light theme, `#be185d` in the dark theme). A new palette colour or a changed token must keep it green.

Category colour stays identity only (ADR-0071, `design-system.md`): never a status, a bar or an amount. The contrast is measured against `card`; on other surfaces (a popover, a `muted` row, the `background` under the tiles of `CategoryIconPicker`) the tiles are checked by eye in the kitchen sinks. Because the tint is now a class in `@layer components` and no longer an inline style, a hover utility on the same element (`hover:bg-secondary` in `CategoryIconPicker`) replaces the tint while the pointer is over the tile; that is intended, the hover marks the pickable option.

We rejected:

- a darker palette per theme (the palette comes from the server and is stored per Category; two palettes would double the data);
- raising the tile alpha (the tile would stop looking like a tint, and the dark theme did not need it);
- `dark:` utilities (the UI check forbids them, and the shares are tokens);
- `oklab` mixing (the test cannot compute it exactly).

It changes no rule in `context/foundation/glossary.md` and no _Avoid_ term.
