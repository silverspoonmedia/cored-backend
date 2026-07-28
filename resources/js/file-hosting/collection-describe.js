/**
 * Structured collection describe form — compose / parse / validate for Lock & Describe.
 * Composes a single collection_hint string for the lock API (max 2000 chars).
 */

export const HINT_MAX_LENGTH = 2000;
export const PRIMARY_CONCEPT_MIN = 10;

export const SECTION_HEADERS = {
    primaryConcept: 'PRIMARY CONCEPT',
    visibleSubjects: 'VISIBLE SUBJECTS',
    buyerIntent: 'BUYER INTENT',
    styleMood: 'STYLE AND MOOD',
    additionalNotes: 'ADDITIONAL NOTES',
};

/**
 * @returns {{
 *   primaryConcept: string,
 *   visibleSelected: string[],
 *   visibleOther: string,
 *   intentSelected: string[],
 *   intentOther: string,
 *   styleSelected: string[],
 *   styleOther: string,
 *   additionalNotes: string,
 * }}
 */
export function emptyDescribeSections() {
    return {
        primaryConcept: '',
        visibleSelected: [],
        visibleOther: '',
        intentSelected: [],
        intentOther: '',
        styleSelected: [],
        styleOther: '',
        additionalNotes: '',
    };
}

/**
 * @param {string[]} selected
 * @param {string} other
 * @param {Record<string, string>} labelMap key => display label
 */
function joinSelections(selected, other, labelMap) {
    const items = (selected || []).map(function (key) {
        return labelMap[key] || key;
    });
    const otherTrim = (other || '').trim();
    if (otherTrim) {
        items.push(otherTrim);
    }
    return items.filter(Boolean).join(', ');
}

/**
 * @param {string} value comma-separated composed values
 * @param {Record<string, string>} labelMap
 * @returns {{ selected: string[], other: string }}
 */
function splitSelections(value, labelMap) {
    const selected = [];
    const custom = [];
    const labelToKey = Object.fromEntries(
        Object.entries(labelMap).map(function ([key, label]) {
            return [label.toLowerCase(), key];
        }),
    );

    value.split(',').forEach(function (part) {
        const piece = part.trim();
        if (!piece) {
            return;
        }
        const key = labelToKey[piece.toLowerCase()];
        if (key) {
            selected.push(key);
        } else {
            custom.push(piece);
        }
    });

    return {
        selected,
        other: custom.join(', '),
    };
}

/**
 * @param {ReturnType<typeof emptyDescribeSections>} sections
 * @param {{ visible?: Record<string,string>, intent?: Record<string,string>, style?: Record<string,string> }} labelMaps
 */
export function composeCollectionHint(sections, labelMaps = {}) {
    const visibleMap = labelMaps.visible || {};
    const intentMap = labelMaps.intent || {};
    const styleMap = labelMaps.style || {};
    const parts = [];

    const concept = (sections.primaryConcept || '').trim();
    if (concept) {
        parts.push(SECTION_HEADERS.primaryConcept + ': ' + concept);
    }

    const visible = joinSelections(sections.visibleSelected, sections.visibleOther, visibleMap);
    if (visible) {
        parts.push(SECTION_HEADERS.visibleSubjects + ': ' + visible);
    }

    const intent = joinSelections(sections.intentSelected, sections.intentOther, intentMap);
    if (intent) {
        parts.push(SECTION_HEADERS.buyerIntent + ': ' + intent);
    }

    const style = joinSelections(sections.styleSelected, sections.styleOther, styleMap);
    if (style) {
        parts.push(SECTION_HEADERS.styleMood + ': ' + style);
    }

    const notes = (sections.additionalNotes || '').trim();
    if (notes) {
        parts.push(SECTION_HEADERS.additionalNotes + ': ' + notes);
    }

    return parts.join('\n\n').trim();
}

/**
 * @param {string|null|undefined} text
 * @param {{ visible?: Record<string,string>, intent?: Record<string,string>, style?: Record<string,string> }} labelMaps
 */
export function parseCollectionHint(text, labelMaps = {}) {
    const result = emptyDescribeSections();
    const trimmed = (text || '').trim();
    if (!trimmed) {
        return result;
    }

    if (!/^PRIMARY CONCEPT:/im.test(trimmed)) {
        result.primaryConcept = trimmed;
        return result;
    }

    const visibleMap = labelMaps.visible || {};
    const intentMap = labelMaps.intent || {};
    const styleMap = labelMaps.style || {};

    trimmed.split(/\n\n+/).forEach(function (block) {
        const match = block.match(/^([A-Z][A-Z ]+):\s*([\s\S]*)$/);
        if (!match) {
            return;
        }
        const header = match[1].trim();
        const value = match[2].trim();
        if (!value) {
            return;
        }

        switch (header) {
            case SECTION_HEADERS.primaryConcept:
                result.primaryConcept = value;
                break;
            case SECTION_HEADERS.visibleSubjects: {
                const parsed = splitSelections(value, visibleMap);
                result.visibleSelected = parsed.selected;
                result.visibleOther = parsed.other;
                break;
            }
            case SECTION_HEADERS.buyerIntent: {
                const parsed = splitSelections(value, intentMap);
                result.intentSelected = parsed.selected;
                result.intentOther = parsed.other;
                break;
            }
            case SECTION_HEADERS.styleMood: {
                const parsed = splitSelections(value, styleMap);
                result.styleSelected = parsed.selected;
                result.styleOther = parsed.other;
                break;
            }
            case SECTION_HEADERS.additionalNotes:
                result.additionalNotes = value;
                break;
            default:
                break;
        }
    });

    return result;
}

/**
 * @param {ReturnType<typeof emptyDescribeSections>} sections
 */
export function validateCollectionDescribe(sections) {
    const errors = [];
    const concept = (sections.primaryConcept || '').trim();
    if (concept.length < PRIMARY_CONCEPT_MIN) {
        errors.push('primaryConcept');
    }

    const hasVisible = (sections.visibleSelected?.length || 0) > 0
        || (sections.visibleOther || '').trim() !== '';
    if (!hasVisible) {
        errors.push('visible');
    }

    const hasIntent = (sections.intentSelected?.length || 0) > 0
        || (sections.intentOther || '').trim() !== '';
    if (!hasIntent) {
        errors.push('intent');
    }

    const hasStyle = (sections.styleSelected?.length || 0) > 0
        || (sections.styleOther || '').trim() !== '';
    if (!hasStyle) {
        errors.push('style');
    }

    return {
        valid: errors.length === 0,
        errors,
    };
}

/**
 * @param {HTMLElement} form
 */
export function readDescribeForm(form) {
    const sections = emptyDescribeSections();
    if (!form) {
        return sections;
    }

    sections.primaryConcept = form.querySelector('[data-fh-describe-primary]')?.value || '';
    sections.additionalNotes = form.querySelector('[data-fh-describe-notes]')?.value || '';
    sections.visibleOther = form.querySelector('[data-fh-describe-visible-other]')?.value || '';
    sections.intentOther = form.querySelector('[data-fh-describe-intent-other]')?.value || '';
    sections.styleOther = form.querySelector('[data-fh-describe-style-other]')?.value || '';

    form.querySelectorAll('[data-fh-describe-visible]:checked').forEach(function (el) {
        sections.visibleSelected.push(el.value);
    });
    form.querySelectorAll('[data-fh-describe-intent]:checked').forEach(function (el) {
        sections.intentSelected.push(el.value);
    });
    form.querySelectorAll('[data-fh-describe-style]:checked').forEach(function (el) {
        sections.styleSelected.push(el.value);
    });

    return sections;
}

/**
 * @param {HTMLElement} form
 * @param {ReturnType<typeof emptyDescribeSections>} sections
 */
export function populateDescribeForm(form, sections) {
    if (!form || !sections) {
        return;
    }

    const primary = form.querySelector('[data-fh-describe-primary]');
    if (primary) {
        primary.value = sections.primaryConcept || '';
    }
    const notes = form.querySelector('[data-fh-describe-notes]');
    if (notes) {
        notes.value = sections.additionalNotes || '';
    }

    const visibleOther = form.querySelector('[data-fh-describe-visible-other]');
    if (visibleOther) {
        visibleOther.value = sections.visibleOther || '';
    }
    const intentOther = form.querySelector('[data-fh-describe-intent-other]');
    if (intentOther) {
        intentOther.value = sections.intentOther || '';
    }
    const styleOther = form.querySelector('[data-fh-describe-style-other]');
    if (styleOther) {
        styleOther.value = sections.styleOther || '';
    }

    const selectedVisible = new Set(sections.visibleSelected || []);
    form.querySelectorAll('[data-fh-describe-visible]').forEach(function (el) {
        el.checked = selectedVisible.has(el.value);
    });
    const selectedIntent = new Set(sections.intentSelected || []);
    form.querySelectorAll('[data-fh-describe-intent]').forEach(function (el) {
        el.checked = selectedIntent.has(el.value);
    });
    const selectedStyle = new Set(sections.styleSelected || []);
    form.querySelectorAll('[data-fh-describe-style]').forEach(function (el) {
        el.checked = selectedStyle.has(el.value);
    });
}

/**
 * @param {HTMLElement} form
 * @param {{ visible?: Record<string,string>, intent?: Record<string,string>, style?: Record<string,string> }} labelMaps
 */
export function updateDescribePreview(form, labelMaps, options = {}) {
    const preview = form?.querySelector('[data-fh-describe-preview-text]');
    const counter = form?.querySelector('[data-fh-describe-char-count]');
    if (!preview) {
        return { hint: '', length: 0 };
    }

    const sections = readDescribeForm(form);
    const hint = composeCollectionHint(sections, labelMaps);
    preview.textContent = hint || '—';
        if (counter) {
            const tmpl = options.charCountTemplate || ':count / :max';
            counter.textContent = tmpl
                .replace(':count', String(hint.length))
                .replace(':max', String(HINT_MAX_LENGTH));
            counter.classList.toggle('text-danger', hint.length > HINT_MAX_LENGTH);
        }

    return { hint, length: hint.length };
}

/**
 * @param {HTMLElement} form
 * @param {string[]} errors
 */
export function markDescribeValidation(form, errors) {
    if (!form) {
        return;
    }
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    (errors || []).forEach(function (key) {
        switch (key) {
            case 'primaryConcept':
                form.querySelector('[data-fh-describe-primary]')?.classList.add('is-invalid');
                break;
            case 'visible':
                form.querySelector('[data-fh-describe-visible-other]')?.classList.add('is-invalid');
                break;
            case 'intent':
                form.querySelector('[data-fh-describe-intent-other]')?.classList.add('is-invalid');
                break;
            case 'style':
                form.querySelector('[data-fh-describe-style-other]')?.classList.add('is-invalid');
                break;
            default:
                break;
        }
    });
}
