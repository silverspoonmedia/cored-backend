/**
 * Unwrap Monolith API envelope `{ data: { results, pagination } }` for Select2.
 *
 * @param {unknown} data
 * @returns {{ results: Array<{id: string|number, text: string}>, pagination: { more: boolean } }}
 */
export function processLookupResults(data) {
    const payload = data && typeof data === 'object' && 'data' in data && data.data != null
        ? data.data
        : data;
    const source = payload && typeof payload === 'object' ? payload : {};

    return {
        results: Array.isArray(source.results) ? source.results : [],
        pagination: source.pagination && typeof source.pagination === 'object'
            ? { more: Boolean(source.pagination.more) }
            : { more: false },
    };
}
