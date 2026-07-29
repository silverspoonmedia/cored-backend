/**
 * Microstock asset review side panel.
 *
 * Opens a Bootstrap offcanvas with the signed preview image, editable title,
 * and keyword chips. Saves via the web hop PATCH endpoint and re-analyzes via
 * POST. Pure chip helpers are exported for unit tests.
 */

import { isTerminalMetadataStatus, resolveMicrostockBadge, stageStripItems } from './microstock-ui.js';
import { joinFhPath, resolveFhBase } from './router.js';

const PANEL_POLL_FALLBACK_MS = 30000;

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

/** Normalize + dedupe keywords (trim, drop empties, case-insensitive unique). */
export function normalizeKeywords(list) {
    const seen = new Set();
    const out = [];
    (Array.isArray(list) ? list : []).forEach((k) => {
        const v = String(k ?? '').trim();
        const key = v.toLowerCase();
        if (v === '' || seen.has(key)) {
            return;
        }
        seen.add(key);
        out.push(v);
    });
    return out;
}

/** Parse a raw input string (comma/newline separated) into keywords. */
export function parseKeywordInput(raw) {
    return normalizeKeywords(String(raw ?? '').split(/[\n,]+/));
}

export function createMicrostockPanel(config, csrfToken, hooks = {}) {
    const panel = document.querySelector('[data-fh-microstock-panel]');
    if (!panel) {
        return { open: function () {} };
    }
    const els = {
        thumb: panel.querySelector('[data-fh-ms-thumb]'),
        status: panel.querySelector('[data-fh-ms-status]'),
        stages: panel.querySelector('[data-fh-ms-stages]'),
        error: panel.querySelector('[data-fh-ms-error]'),
        title: panel.querySelector('#fhMsTitle'),
        description: panel.querySelector('[data-fh-ms-description]'),
        category: panel.querySelector('[data-fh-ms-category]'),
        chips: panel.querySelector('[data-fh-ms-chips]'),
        keywordInput: panel.querySelector('#fhMsKeywordInput'),
        keywordCount: panel.querySelector('[data-fh-ms-keyword-count]'),
        keywordClear: panel.querySelector('[data-fh-ms-keyword-clear]'),
        detailsChevron: panel.querySelector('[data-fh-ms-details-chevron]'),
        save: panel.querySelector('[data-fh-ms-save]'),
        reanalyze: panel.querySelector('[data-fh-ms-reanalyze]'),
    };
    const labels = config.microstockPanel || {};
    const state = { fileId: null, keywords: [], pollTimer: null };

    function isRealtimeConnected() {
        if (typeof hooks.isRealtimeConnected === 'function') {
            return hooks.isRealtimeConnected();
        }
        return window.fileHostingRealtime?.connected === true;
    }

    function assetUrl(suffix) {
        const assetsRoot = config.assetsBase || joinFhPath(resolveFhBase(config), 'microstock', 'assets');
        if (!assetsRoot || !state.fileId) {
            return '';
        }
        return assetsRoot + '/' + encodeURIComponent(state.fileId) + suffix;
    }

    function request(method, suffix, body) {
        const url = assetUrl(suffix);
        if (!url) {
            return Promise.resolve({ ok: false, status: 0, json: {} });
        }
        return fetch(url, {
            method: method,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: body ? JSON.stringify(body) : null,
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (json) {
                return { ok: res.ok, status: res.status, json: json };
            });
        });
    }

    function toast(message, type) {
        if (typeof hooks.toast === 'function') {
            hooks.toast(message, type);
        }
    }

    function stopMetadataPoll() {
        if (state.pollTimer !== null) {
            clearInterval(state.pollTimer);
            state.pollTimer = null;
        }
    }

    function startMetadataPoll() {
        stopMetadataPoll();
        if (isRealtimeConnected()) {
            return;
        }
        state.pollTimer = setInterval(function () {
            loadMetadata(false);
        }, PANEL_POLL_FALLBACK_MS);
    }

    function setStatus(status, errorMessage, stages) {
        const badge = resolveMicrostockBadge(
            { status: status, stages: stages || {} },
            labels,
        );
        if (els.status) {
            els.status.textContent = badge.label;
            els.status.className = 'badge ' + badge.cls;
        }
        if (els.error) {
            const show = status === 'failed' && Boolean(errorMessage);
            els.error.classList.toggle('d-none', !show);
            els.error.textContent = show ? String(errorMessage) : '';
        }
        renderStageStrip(stages);
    }

    function renderStageStrip(stages) {
        if (!els.stages) {
            return;
        }
        const items = stageStripItems(stages, labels);
        els.stages.innerHTML = items.map(function (item, index) {
            const dotCls = item.state === 'ok'
                ? 'text-success'
                : (item.state === 'failed' ? 'text-danger' : (item.state === 'running' ? 'text-warning' : 'text-muted'));
            const icon = item.state === 'ok'
                ? '\u2713'
                : (item.state === 'failed' ? '\u2717' : (item.state === 'running' ? '\u25CF' : '\u25CB'));
            const sep = index > 0 ? '<span class="text-muted mx-1">\u2192</span>' : '';
            return sep
                + '<span class="' + dotCls + '" title="' + escapeHtml(item.label) + ': ' + escapeHtml(item.state) + '">'
                + '<span class="fh-ms-stage-dot">' + icon + '</span>'
                + '<span class="visually-hidden">' + escapeHtml(item.label) + ' ' + escapeHtml(item.icon) + '</span>'
                + '</span>';
        }).join('');
    }

    function applyMetadata(data) {
        if (els.title) {
            els.title.value = data.title || '';
            els.title.disabled = data.status === 'pending' || data.status === 'failed';
        }
        if (els.description) {
            els.description.textContent = data.description || '—';
        }
        if (els.category) {
            els.category.textContent = data.category || '—';
        }
        if (Array.isArray(data.keywords)) {
            state.keywords = normalizeKeywords(data.keywords);
            renderChips();
        }
        setStatus(data.status || null, data.error || null, data.stages || {});
        const terminal = isTerminalMetadataStatus(data.status);
        if (els.save) {
            els.save.disabled = data.status !== 'generated';
        }
        if (terminal) {
            stopMetadataPoll();
        }
    }

    function loadMetadata(startPollIfPending) {
        if (!state.fileId) {
            return Promise.resolve();
        }
        return request('GET', '/metadata').then(function (out) {
            if (!out.ok) {
                setStatus(null);
                return;
            }
            const data = out.json?.data || {};
            applyMetadata(data);
            if (startPollIfPending && data.status === 'pending' && !isRealtimeConnected()) {
                startMetadataPoll();
            }
        }).catch(function () { /* best-effort */ });
    }

    function handleAssetProgress(payload) {
        if (!state.fileId || String(payload?.file_id) !== String(state.fileId)) {
            return;
        }
        const ms = payload.microstock || {};
        applyMetadata({
            title: ms.title,
            keywords: ms.keywords,
            description: els.description?.textContent === '—' ? '' : els.description?.textContent,
            category: els.category?.textContent === '—' ? '' : els.category?.textContent,
            status: ms.status,
            stages: ms.stages,
            error: ms.error,
        });
        if (ms.status === 'pending' && !isRealtimeConnected()) {
            startMetadataPoll();
        }
    }

    function renderChips() {
        if (!els.chips) {
            return;
        }
        els.chips.innerHTML = state.keywords.map(function (k, i) {
            return '<span class="badge text-bg-light border d-inline-flex align-items-center gap-1">'
                + escapeHtml(k)
                + '<button type="button" class="btn-close btn-close-sm" aria-label="Remove" data-fh-ms-chip-remove="' + i + '"></button>'
                + '</span>';
        }).join('');
        if (els.keywordCount) {
            els.keywordCount.textContent = String(state.keywords.length);
        }
    }

    function addKeywords(raw) {
        const next = normalizeKeywords(state.keywords.concat(parseKeywordInput(raw)));
        state.keywords = next;
        renderChips();
    }

    els.chips?.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-fh-ms-chip-remove]');
        if (!btn) {
            return;
        }
        const idx = Number(btn.dataset.fhMsChipRemove);
        state.keywords = state.keywords.filter(function (_, i) { return i !== idx; });
        renderChips();
    });

    els.keywordInput?.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ',') {
            return;
        }
        e.preventDefault();
        addKeywords(els.keywordInput.value);
        els.keywordInput.value = '';
    });

    els.keywordInput?.addEventListener('blur', function () {
        if (els.keywordInput.value.trim() !== '') {
            addKeywords(els.keywordInput.value);
            els.keywordInput.value = '';
        }
    });

    els.keywordClear?.addEventListener('click', function () {
        state.keywords = [];
        renderChips();
    });

    const aiDetailsCollapse = document.getElementById('fhMsAiDetails');
    if (aiDetailsCollapse && els.detailsChevron) {
        aiDetailsCollapse.addEventListener('show.bs.collapse', function () {
            els.detailsChevron.classList.add('bi-chevron-down');
            els.detailsChevron.classList.remove('bi-chevron-right');
        });
        aiDetailsCollapse.addEventListener('hide.bs.collapse', function () {
            els.detailsChevron.classList.add('bi-chevron-right');
            els.detailsChevron.classList.remove('bi-chevron-down');
        });
    }

    els.save?.addEventListener('click', function () {
        if (!state.fileId) {
            return;
        }
        const title = (els.title?.value || '').trim();
        els.save.disabled = true;
        request('PATCH', '/metadata', { title: title, keywords: state.keywords })
            .then(function (out) {
                if (out.ok) {
                    toast(out.json.message || labels.saved || 'Saved', 'success');
                    setStatus('finalized');
                    if (typeof hooks.onSaved === 'function') {
                        hooks.onSaved(state.fileId, { title: title, keywords: state.keywords.slice() });
                    }
                } else {
                    toast(out.json.message || 'Error', 'error');
                }
            })
            .catch(function () { toast('Network error', 'error'); })
            .finally(function () { els.save.disabled = false; });
    });

    els.reanalyze?.addEventListener('click', function () {
        if (!state.fileId) {
            return;
        }
        els.reanalyze.disabled = true;
        request('POST', '/analyze')
            .then(function (out) {
                toast(out.json.message || labels.queued || 'Queued', out.ok ? 'success' : 'error');
                if (out.ok) {
                    setStatus('pending', null, { vision: 'pending', thinking: 'pending', result: 'pending' });
                    if (!isRealtimeConnected()) {
                        startMetadataPoll();
                    }
                }
            })
            .catch(function () { toast('Network error', 'error'); })
            .finally(function () { els.reanalyze.disabled = false; });
    });

    panel.addEventListener('hidden.bs.offcanvas', function () {
        stopMetadataPoll();
    });

    function open(fileId) {
        if (!fileId) {
            return;
        }
        state.fileId = fileId;
        state.keywords = [];
        stopMetadataPoll();
        renderChips();
        if (els.title) {
            els.title.value = '';
        }
        if (els.thumb) {
            els.thumb.src = '';
        }

        request('GET', '/preview').then(function (out) {
            if (out.ok && out.json?.data?.url && els.thumb) {
                els.thumb.src = out.json.data.url;
            }
        }).catch(function () { /* best-effort */ });

        loadMetadata(true);

        if (window.bootstrap?.Offcanvas) {
            window.bootstrap.Offcanvas.getOrCreateInstance(panel).show();
        } else {
            panel.classList.add('show');
        }
    }

    return {
        open: open,
        handleAssetProgress: handleAssetProgress,
        onRealtimeConnected: stopMetadataPoll,
        onRealtimeDegraded: function () {
            if (state.fileId && panel.classList.contains('show')) {
                loadMetadata(true);
            }
        },
    };
}
