function formatSnapshot(snapshot) {
    if (!snapshot) return '—';
    return `In $${Number(snapshot.input_per_million_usd).toFixed(2)} · Out $${Number(snapshot.output_per_million_usd).toFixed(2)}`;
}

function renderUsageRow(event) {
    const user = event.user ? `${event.user.name || event.user.email}` : event.user_id || '—';
    const model = event.resolved_model || event.alias_model || '—';
    const provider = event.inference_provider?.slug || '—';
    const snapshot = event.rate_snapshot;

    return `<tr>
        <td class="small">${event.created_at ? new Date(event.created_at).toLocaleString() : '—'}</td>
        <td class="small">${user}</td>
        <td class="small"><code>${model}</code></td>
        <td class="small"><code>${provider}</code></td>
        <td class="small">${event.stage || '—'}</td>
        <td class="small">${event.total_tokens ?? '—'}</td>
        <td class="small">${event.billable_usd ?? '—'}</td>
        <td class="small">${formatSnapshot(snapshot)}</td>
    </tr>`;
}

async function loadCardUsage(root) {
    const url = root.dataset.eventsUrl;
    const modelCardId = root.dataset.modelCardId;
    const tbody = root.querySelector('[data-inference-card-usage-body]');

    if (!url || !tbody || !modelCardId) return;

    try {
        const response = await fetch(`${url}?model_card_id=${encodeURIComponent(modelCardId)}&per_page=10`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const payload = await response.json();
        const items = payload.data?.items ?? [];

        if (!response.ok || items.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-muted">No events</td></tr>`;
            return;
        }

        tbody.innerHTML = items.map(renderUsageRow).join('');
    } catch {
        tbody.innerHTML = `<tr><td colspan="6" class="text-danger">Load failed</td></tr>`;
    }
}

function initInferenceUsageReport(root) {
    if (!root || root.dataset.inferenceUsageReportBound === '1') return;
    root.dataset.inferenceUsageReportBound = '1';

    const overviewUrl = root.dataset.overviewUrl || '';
    const eventsUrl = root.dataset.eventsUrl || '';
    const form = document.querySelector('[data-inference-usage-filters]');
    let currentPage = 1;
    let lastPage = 1;

    const getFilters = () => {
        const params = new URLSearchParams();
        const from = form?.querySelector('[data-filter-from]')?.value;
        const to = form?.querySelector('[data-filter-to]')?.value;
        const model = form?.querySelector('[data-filter-model]')?.value;
        const user = form?.querySelector('[data-filter-user]')?.value;
        const stage = form?.querySelector('[data-filter-stage]')?.value;

        if (from) params.set('from', from);
        if (to) params.set('to', to);
        if (model) params.set('model_slug', model);
        if (user) params.set('user_id', user);
        if (stage) params.set('stage', stage);

        return params;
    };

    const loadOverview = async () => {
        if (!overviewUrl) return;
        const params = getFilters();
        const response = await fetch(`${overviewUrl}?${params.toString()}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const payload = await response.json();
        const data = payload.data ?? {};
        const totals = data.totals ?? {};

        root.querySelector('[data-total-events]').textContent = totals.event_count ?? '—';
        root.querySelector('[data-total-users]').textContent = totals.unique_users ?? '—';
        root.querySelector('[data-total-tokens]').textContent = totals.total_tokens?.toLocaleString?.() ?? totals.total_tokens ?? '—';
        root.querySelector('[data-total-provider]').textContent = totals.provider_cost_usd ?? '—';
        root.querySelector('[data-total-billable]').textContent = totals.billable_usd ?? '—';
        root.querySelector('[data-total-margin]').textContent = totals.margin_usd ?? '—';

        const byModel = root.querySelector('[data-inference-by-model]');
        if (byModel) {
            byModel.innerHTML = (data.by_model ?? []).map((row) =>
                `<tr><td><code>${row.resolved_model}</code></td><td class="text-end">${row.event_count}</td><td class="text-end">${row.billable_usd}</td></tr>`,
            ).join('') || '<tr><td colspan="3" class="text-muted">—</td></tr>';
        }

        const byStage = root.querySelector('[data-inference-by-stage]');
        if (byStage) {
            byStage.innerHTML = (data.by_stage ?? []).map((row) =>
                `<tr><td>${row.stage}</td><td class="text-end">${row.event_count}</td><td class="text-end">${row.billable_usd}</td></tr>`,
            ).join('') || '<tr><td colspan="3" class="text-muted">—</td></tr>';
        }

        const byProvider = root.querySelector('[data-inference-by-provider]');
        if (byProvider) {
            byProvider.innerHTML = (data.by_provider ?? []).map((row) =>
                `<tr><td><code>${row.provider_slug ?? row.inference_provider_id}</code></td><td class="text-end">${row.event_count}</td><td class="text-end">${row.billable_usd}</td></tr>`,
            ).join('') || '<tr><td colspan="3" class="text-muted">—</td></tr>';
        }

        const bySource = root.querySelector('[data-inference-by-source]');
        if (bySource) {
            const sources = data.by_billing_source ?? {};
            bySource.innerHTML = Object.entries(sources).map(([key, row]) =>
                `<tr><td>${key}</td><td class="text-end">${row.event_count}</td><td class="text-end">${row.billable_usd}</td></tr>`,
            ).join('') || '<tr><td colspan="3" class="text-muted">—</td></tr>';
        }
    };

    const loadEvents = async (append = false) => {
        if (!eventsUrl) return;
        const params = getFilters();
        params.set('per_page', '20');
        params.set('page', String(currentPage));

        const response = await fetch(`${eventsUrl}?${params.toString()}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        const payload = await response.json();
        const items = payload.data?.items ?? [];
        const pagination = payload.data?.pagination ?? {};
        lastPage = pagination.last_page ?? 1;

        const tbody = root.querySelector('[data-inference-events-body]');
        const loadMore = root.querySelector('[data-inference-load-more]');

        if (!tbody) return;

        if (!append) {
            tbody.innerHTML = '';
        }

        if (items.length === 0 && !append) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-muted">No events</td></tr>`;
        } else {
            tbody.insertAdjacentHTML('beforeend', items.map(renderUsageRow).join(''));
        }

        if (loadMore) {
            loadMore.hidden = currentPage >= lastPage;
        }
    };

    form?.addEventListener('submit', (event) => {
        event.preventDefault();
        currentPage = 1;
        loadOverview();
        loadEvents(false);
    });

    root.querySelector('[data-inference-load-more]')?.addEventListener('click', () => {
        if (currentPage < lastPage) {
            currentPage += 1;
            loadEvents(true);
        }
    });

    loadOverview();
    loadEvents(false);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-inference-card-usage]').forEach(loadCardUsage);
    document.querySelectorAll('[data-inference-usage-report]').forEach(initInferenceUsageReport);
});
