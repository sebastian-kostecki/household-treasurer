/**
 * The same text without Polish diacritics ("Żywność" -> "Zywnosc"), so a query
 * typed on a keyboard without them still matches. `ł` has no decomposition,
 * so it is mapped by hand.
 */
export function foldDiacritics(text: string): string {
    return text
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .replace(/ł/g, 'l')
        .replace(/Ł/g, 'L');
}

/** Text prepared for comparing: no diacritics, lower case (pl), trimmed. */
export function normalizeSearch(text: string): string {
    return foldDiacritics(text).toLocaleLowerCase('pl').trim();
}

/**
 * Whether the query occurs in any of the texts, ignoring case and Polish
 * diacritics on both sides. An empty query matches everything; a null text
 * never matches.
 */
export function matchesSearch(
    query: string,
    texts: Array<string | null>,
): boolean {
    const needle = normalizeSearch(query);

    if (needle === '') {
        return true;
    }

    return texts.some(
        (text) => text !== null && normalizeSearch(text).includes(needle),
    );
}
