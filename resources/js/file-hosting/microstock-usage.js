/**
 * Microstock AI usage & balance — summary, ledger mutations, usage detail.
 *
 * Two entry points:
 * 1. createMicrostockUsagePanel(config, csrfToken) — SPA offcanvas factory (used by Vue FH island)
 * 2. initMicrostockUsage(root) — standalone page bootstrap (used by microstock-usage.blade.php)
 */

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

function formatDate(iso) {
    if (!iso) {
        return '—';
    }
    try {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(iso));
    } catch {
        return iso;
    }
}

function amountClass(amountMicro) {
    if (amountMicro > 0) {
        return 'text-success';
    }
    if (amountMicro < 0) {
        return 'text-danger';
    }
    return 'text-muted';
}

function formatUsdFromMicro(microUsd) {
    const micro = Number(microUsd) || 0;
    const usd = Math.abs(micro) / 1_000_000;
    const formatted = usd.toFixed(2);
    return micro < 0 ? '-$' + formatted : '$' + formatted;
}

function formatUsdDisplay(usdValue, microFallback) {
    if (typeof usdValue === 'string' && usdValue.includes('$')) {
        return usdValue;
    }
    const numeric = Number(usdValue);
    if (!Number.isNaN(numeric) && usdValue !== null && usdValue !== '') {
        return '$' + numeric.toFixed(2);
    }
    return formatUsdFromMicro(microFallback ?? 0);
}

/**
 * Build a usage panel controller bound to a given root element.
 * Works for both the SPA offcanvas and the standalone page.
 * @param {HTMLElement} root
 * @param {object} config
 * @param {string} csrfToken
 */
function bindUsagePanel(root, config, csrfToken) {
    if (!root || root.dataset.fhMsUsageBound === '1') {
        return null;
    }
    root.dataset.fhMsUsageBound = '1';

    const labels = config.labels || {};

    const els = {
        balance: root.querySelector('[data-fh-msu-balance], [data-fh-ms-balance]'),
        estimate: root.querySelector('[data-fh-msu-estimate], [data-fh-ms-estimate]'),
        dailyCard: root.querySelector('[data-fh-msu-daily-card], [data-fh-ms-daily-card]'),
        daily: root.querySelector('[data-fh-msu-daily], [data-fh-ms-daily]'),
        ledgerBody: root.querySelector('[data-fh-msu-ledger-body], [data-fh-ms-ledger-body]'),
        ledgerMoreWrap: root.querySelector('[data-fh-msu-ledger-more-wrap], [data-fh-ms-ledger-more-wrap]'),
        ledgerMore: root.querySelector('[data-fh-msu-ledger-more], [data-fh-ms-ledger-more]'),
        usageBody: root.querySelector('[data-fh-msu-usage-body], [data-fh-ms-usage-body]'),
        usageMoreWrap: root.querySelector('[data-fh-msu-usage-more-wrap], [data-fh-ms-usage-more-wrap]'),
        usageMore: root.querySelector('[data-fh-msu-usage-more], [data-fh-ms-usage-more]'),
        platformProvider: root.querySelector('[data-fh-msu-platform-provider], [data-fh-ms-platform-provider]'),
        platformBillable: root.querySelector('[data-fh-msu-platform-billable], [data-fh-ms-platform-billable]'),
        platformMargin: root.querySelector('[data-fh-msu-platform-margin], [data-fh-ms-platform-margin]'),
        platformEvents: root.querySelector('[data-fh-msu-platform-events], [data-fh-ms-platform-events]'),
        platformUsers: root.querySelector('[data-fh-msu-platform-users], [data-fh-ms-platform-users]'),
        platformDailyFree: root.querySelector('[data-fh-msu-platform-daily-free], [data-fh-ms-platform-daily-free]'),
        platformSourceBody: root.querySelector('[data-fh-msu-platform-source-body], [data-fh-ms-platform-source-body]'),
        platformStageBody: root.querySelector('[data-fh-msu-platform-stage-body], [data-fh-ms-platform-stage-body]'),
        platformModelBody: root.querySelector('[data-fh-msu-platform-model-body], [data-fh-ms-platform-model-body]'),
        platformUsageBody: root.querySelector('[data-fh-msu-platform-usage-body], [data-fh-ms-platform-usage-body]'),
        platformUsageMoreWrap: root.querySelector('[data-fh-msu-platform-usage-more-wrap], [data-fh-ms-platform-usage-more-wrap]'),
        platformUsageMore: root.querySelector('[data-fh-msu-platform-usage-more], [data-fh-ms-platform-usage-more]'),
        ledgerTab: root.querySelector('#fh-msu-ledger-tab, #fh-ms-ledger-tab'),
        usageTab: root.querySelector('#fh-msu-usage-tab, #fh-ms-usage-tab'),
        platformTab: root.querySelector('#fh-msu-platform-tab, #fh-ms-platform-tab'),
    };

    const state = {
        ledgerPage: 0,
        ledgerHasMore: false,
        ledgerLoading: false,
        usagePage: 0,
        usageHasMore: false,
        usageLoading: false,
        usageLoaded: false,
        platformLoaded: false,
        platformUsagePage: 0,
        platformUsageHasMore: false,
        platformUsageLoading: false,
        summaryLoaded: false,
    };

    function fetchJson(url, params) {
        const u = new URL(url, window.location.origin);
        Object.entries(params || {}).forEach(function ([key, value]) {
            if (value !== null && value !== undefined && value !== '') {
                u.searchParams.set(key, String(value));
            }
        });
        return fetch(u.toString(), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
        }).then(function (res) {
            return res.json().then(function (json) {
                return { ok: res.ok, json: json };
            });
        });
    }

    function renderSummary(data) {
        if (els.balance) {
            els.balance.textContent = formatUsdDisplay(data.balance_usd, data.balance_micro_usd);
        }
        if (els.estimate) {
            els.estimate.textContent = formatUsdDisplay(
                data.estimated_per_analysis_usd,
                data.estimated_per_analysis_micro_usd,
            );
        }
        const daily = data.daily_free;
        if (daily?.enabled && els.dailyCard && els.daily) {
            els.dailyCard.classList.remove('d-none');
            const tmpl = labels.dailyFreeLine || ':remaining / :limit free today';
            els.daily.textContent = tmpl
                .replace(':remaining', String(daily.remaining ?? 0))
                .replace(':limit', String(daily.limit ?? 0))
                .replace(':used', String(daily.used ?? 0));
        }
    }

    function renderLedgerRows(items, append) {
        if (!els.ledgerBody) {
            return;
        }
        if (!append) {
            els.ledgerBody.innerHTML = '';
        }
        if (!items.length && !append) {
            els.ledgerBody.innerHTML = '<tr><td colspan="4" class="text-muted">'
                + escapeHtml(labels.emptyLedger || 'No transactions yet.')
                + '</td></tr>';
            return;
        }
        const html = items.map(function (row) {
            const cls = amountClass(Number(row.amount_micro_usd || 0));
            return '<tr>'
                + '<td class="text-nowrap small">' + escapeHtml(formatDate(row.created_at)) + '</td>'
                + '<td>' + escapeHtml(row.description || '') + '</td>'
                + '<td class="text-end fw-semibold ' + cls + '">' + escapeHtml(row.amount_usd || '') + '</td>'
                + '<td class="text-end text-muted">' + escapeHtml(row.balance_after_usd || '') + '</td>'
                + '</tr>';
        }).join('');
        els.ledgerBody.insertAdjacentHTML('beforeend', html);
    }

    function sourceBadge(source) {
        if (source === 'daily_free') {
            return '<span class="badge text-bg-info">' + escapeHtml(labels.sourceDailyFree || 'Free') + '</span>';
        }
        return '<span class="badge text-bg-secondary">' + escapeHtml(labels.sourcePrepaid || 'Prepaid') + '</span>';
    }

    function renderUsageRows(items, append) {
        const body = els.usageBody;
        const cols = 7;
        if (!body) {
            return;
        }
        if (!append) {
            body.innerHTML = '';
        }
        if (!items.length && !append) {
            body.innerHTML = '<tr><td colspan="' + cols + '" class="text-muted">'
                + escapeHtml(labels.emptyUsage || 'No usage events yet.')
                + '</td></tr>';
            return;
        }
        const html = items.map(function (row) {
            const model = row.resolved_model || row.alias_model || '—';
            return '<tr>'
                + '<td class="text-nowrap small">' + escapeHtml(formatDate(row.created_at)) + '</td>'
                + '<td>' + escapeHtml(row.file_name || '—') + '</td>'
                + '<td><span class="badge text-bg-light border">' + escapeHtml(row.stage || '') + '</span></td>'
                + '<td class="small text-muted d-none d-md-table-cell">' + escapeHtml(model) + '</td>'
                + '<td class="text-end">' + escapeHtml(String(row.total_tokens ?? 0)) + '</td>'
                + '<td class="text-end">' + escapeHtml(row.billable_usd || '$0.00') + '</td>'
                + '<td>' + sourceBadge(row.billing_source) + '</td>'
                + '</tr>';
        }).join('');
        body.insertAdjacentHTML('beforeend', html);
    }

    function renderPlatformOverview(data) {
        const totals = data.totals || {};
        const daily = data.daily_free || {};
        if (els.platformProvider) {
            els.platformProvider.textContent = totals.provider_cost_usd || '—';
        }
        if (els.platformBillable) {
            els.platformBillable.textContent = totals.billable_usd || '—';
        }
        if (els.platformMargin) {
            els.platformMargin.textContent = totals.margin_usd || '—';
        }
        if (els.platformEvents) {
            els.platformEvents.textContent = String(totals.event_count ?? 0);
        }
        if (els.platformUsers) {
            els.platformUsers.textContent = String(totals.unique_users ?? 0);
        }
        if (els.platformDailyFree) {
            els.platformDailyFree.textContent = daily.subsidized_provider_cost_usd || '—';
        }

        renderSimpleRows(els.platformSourceBody, Object.entries(data.by_billing_source || {}).map(function (entry) {
            const source = entry[0];
            const row = entry[1];
            return '<tr>'
                + '<td>' + sourceBadge(source) + '</td>'
                + '<td class="text-end">' + escapeHtml(String(row.event_count ?? 0)) + '</td>'
                + '<td class="text-end">' + escapeHtml(row.provider_cost_usd || '') + '</td>'
                + '<td class="text-end">' + escapeHtml(row.billable_usd || '') + '</td>'
                + '</tr>';
        }), 4);

        renderSimpleRows(els.platformStageBody, (data.by_stage || []).map(function (row) {
            return '<tr>'
                + '<td>' + escapeHtml(row.stage || '') + '</td>'
                + '<td class="text-end">' + escapeHtml(String(row.event_count ?? 0)) + '</td>'
                + '<td class="text-end">' + escapeHtml(row.provider_cost_usd || '') + '</td>'
                + '</tr>';
        }), 3);

        renderSimpleRows(els.platformModelBody, (data.by_model || []).map(function (row) {
            return '<tr>'
                + '<td class="small">' + escapeHtml(row.resolved_model || '') + '</td>'
                + '<td class="text-end">' + escapeHtml(String(row.event_count ?? 0)) + '</td>'
                + '<td class="text-end">' + escapeHtml(String(row.total_tokens ?? 0)) + '</td>'
                + '</tr>';
        }), 3);
    }

    function renderSimpleRows(body, rows, colSpan) {
        if (!body) {
            return;
        }
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="' + colSpan + '" class="text-muted">'
                + escapeHtml(labels.emptyPlatform || 'No data yet.')
                + '</td></tr>';
            return;
        }
        body.innerHTML = rows.join('');
    }

    function renderPlatformUsageRows(items, append) {
        if (!els.platformUsageBody) {
            return;
        }
        if (!append) {
            els.platformUsageBody.innerHTML = '';
        }
        if (!items.length && !append) {
            els.platformUsageBody.innerHTML = '<tr><td colspan="7" class="text-muted">'
                + escapeHtml(labels.emptyPlatform || 'No data yet.')
                + '</td></tr>';
            return;
        }
        const html = items.map(function (row) {
            const userLabel = row.user?.email || row.user?.name || row.user_id || '—';
            return '<tr>'
                + '<td class="text-nowrap small">' + escapeHtml(formatDate(row.created_at)) + '</td>'
                + '<td class="small d-none d-md-table-cell">' + escapeHtml(userLabel) + '</td>'
                + '<td>' + escapeHtml(row.file_name || '—') + '</td>'
                + '<td><span class="badge text-bg-light border">' + escapeHtml(row.stage || '') + '</span></td>'
                + '<td class="text-end">' + escapeHtml(row.provider_cost_usd || '$0.00') + '</td>'
                + '<td class="text-end">' + escapeHtml(row.billable_usd || '$0.00') + '</td>'
                + '<td>' + sourceBadge(row.billing_source) + '</td>'
                + '</tr>';
        }).join('');
        els.platformUsageBody.insertAdjacentHTML('beforeend', html);
    }

    function loadPlatform(appendUsage) {
        if (!config.adminOverviewUrl) {
            return Promise.resolve();
        }
        if (!state.platformLoaded) {
            return fetchJson(config.adminOverviewUrl).then(function (out) {
                if (out.ok && out.json?.data) {
                    renderPlatformOverview(out.json.data);
                    state.platformLoaded = true;
                }
                return loadPlatformUsage(false);
            });
        }
        if (appendUsage) {
            return loadPlatformUsage(true);
        }
        return loadPlatformUsage(false);
    }

    function loadPlatformUsage(append) {
        if (!config.adminUsageUrl || state.platformUsageLoading) {
            return Promise.resolve();
        }
        state.platformUsageLoading = true;
        const page = append ? state.platformUsagePage + 1 : 1;
        return fetchJson(config.adminUsageUrl, { page: page, per_page: 20 }).then(function (out) {
            const payload = out.json?.data || {};
            const items = Array.isArray(payload.items) ? payload.items : [];
            const pagination = payload.pagination || {};
            state.platformUsagePage = page;
            state.platformUsageHasMore = Boolean(pagination.has_more);
            renderPlatformUsageRows(items, append);
            els.platformUsageMoreWrap?.classList.toggle('d-none', !state.platformUsageHasMore);
        }).finally(function () {
            state.platformUsageLoading = false;
        });
    }

    function loadSummary() {
        return fetchJson(config.summaryUrl).then(function (out) {
            if (out.ok && out.json?.data) {
                renderSummary(out.json.data);
                state.summaryLoaded = true;
            }
        });
    }

    function loadLedger(append) {
        if (state.ledgerLoading) {
            return Promise.resolve();
        }
        state.ledgerLoading = true;
        const page = append ? state.ledgerPage + 1 : 1;
        return fetchJson(config.ledgerUrl, { page: page, per_page: 20 }).then(function (out) {
            const payload = out.json?.data || {};
            const items = Array.isArray(payload.items) ? payload.items : [];
            const pagination = payload.pagination || {};
            state.ledgerPage = page;
            state.ledgerHasMore = Boolean(pagination.has_more);
            renderLedgerRows(items, append);
            els.ledgerMoreWrap?.classList.toggle('d-none', !state.ledgerHasMore);
        }).finally(function () {
            state.ledgerLoading = false;
        });
    }

    function loadUsage(append) {
        if (state.usageLoading) {
            return Promise.resolve();
        }
        state.usageLoading = true;
        const page = append ? state.usagePage + 1 : 1;
        return fetchJson(config.usageEventsUrl || config.usageUrl, { page: page, per_page: 20 }).then(function (out) {
            const payload = out.json?.data || {};
            const items = Array.isArray(payload.items) ? payload.items : [];
            const pagination = payload.pagination || {};
            state.usagePage = page;
            state.usageHasMore = Boolean(pagination.has_more);
            renderUsageRows(items, append);
            els.usageMoreWrap?.classList.toggle('d-none', !state.usageHasMore);
            state.usageLoaded = true;
        }).finally(function () {
            state.usageLoading = false;
        });
    }

    // Event bindings
    els.ledgerMore?.addEventListener('click', function () {
        if (state.ledgerHasMore) {
            loadLedger(true);
        }
    });

    els.usageMore?.addEventListener('click', function () {
        if (state.usageHasMore) {
            loadUsage(true);
        }
    });

    els.usageTab?.addEventListener('shown.bs.tab', function () {
        if (!state.usageLoaded) {
            loadUsage(false);
        }
    });

    els.platformTab?.addEventListener('shown.bs.tab', function () {
        if (!state.platformLoaded) {
            loadPlatform(false);
        }
    });

    els.platformUsageMore?.addEventListener('click', function () {
        if (state.platformUsageHasMore) {
            loadPlatformUsage(true);
        }
    });

    function open() {
        if (window.bootstrap?.Offcanvas) {
            window.bootstrap.Offcanvas.getOrCreateInstance(root).show();
        }
        if (!state.summaryLoaded) {
            loadSummary();
            loadLedger(false);
        }
    }

    function close() {
        if (window.bootstrap?.Offcanvas) {
            window.bootstrap.Offcanvas.getInstance(root)?.hide();
        }
    }

    return {
        open: open,
        close: close,
        loadSummary: loadSummary,
        loadLedger: loadLedger,
        loadUsage: loadUsage,
        loadPlatform: loadPlatform,
    };
}

/**
 * SPA offcanvas factory — called by the Vue FH island.
 * @param {object} config  config.usagePanel from data-config
 * @param {string} csrfToken
 */
export function createMicrostockUsagePanel(config, csrfToken) {
    const root = document.querySelector('[data-fh-ms-usage-panel]');
    if (!root) {
        return { isReady: false, open() {}, close() {} };
    }
    const panel = bindUsagePanel(root, config, csrfToken);
    if (!panel) {
        return { isReady: false, open() {}, close() {} };
    }
    return { ...panel, isReady: true };
}

/**
 * Standalone page bootstrap — auto-runs on the usage page.
 */
function initMicrostockUsage(root) {
    if (!root || root.dataset.fhMsUsageBound === '1') {
        return;
    }
    const config = JSON.parse(root.dataset.config || '{}');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const panel = bindUsagePanel(root, config, csrf);
    if (panel) {
        panel.loadSummary();
        panel.loadLedger(false);
    }
}

document.querySelectorAll('[data-fh-ms-usage]').forEach(initMicrostockUsage);
