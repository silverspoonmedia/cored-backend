function showToast(el, message, isError = false) {
    if (!el) return;

    const toast = document.createElement('div');
    toast.className = 'monolith-toast is-' + (isError ? 'error' : 'success');
    toast.setAttribute('role', 'status');
    toast.textContent = message;
    el.appendChild(toast);

    window.setTimeout(() => {
        toast.classList.add('is-leaving');
        window.setTimeout(() => toast.remove(), 300);
    }, 4000);
}

function initInferenceRatePlanAdmin(root) {
    if (!root || root.dataset.inferenceRatePlanAdminBound === '1') {
        return;
    }

    root.dataset.inferenceRatePlanAdminBound = '1';

    const syncUrl = root.dataset.syncUrl || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const toast = root.querySelector('[data-inference-admin-toast]');
    const tbody = root.querySelector('[data-inference-entries-body]');
    const template = root.querySelector('[data-inference-entry-template]');

    root.querySelector('[data-inference-add-entry]')?.addEventListener('click', () => {
        if (!template || !tbody) return;
        tbody.querySelector('[data-inference-empty-row]')?.remove();
        tbody.appendChild(template.content.cloneNode(true));
    });

    root.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-inference-remove-entry]');
        if (!btn) return;
        btn.closest('[data-inference-entry-row]')?.remove();
    });

    root.querySelector('[data-inference-save-entries]')?.addEventListener('click', async (event) => {
        if (!syncUrl || !tbody) return;

        const saveButton = event.currentTarget;
        const spinner = saveButton.querySelector('[data-form-submit-spinner]');
        
        const rates = Array.from(tbody.querySelectorAll('[data-inference-entry-row]')).map((row) => {
            const modelCardId = row.dataset.modelCardId
                || row.querySelector('[data-entry-model-select]')?.value;

            return {
                model_card_id: modelCardId,
                input_per_million_usd: Number(row.querySelector('[data-entry-input]')?.value ?? 0),
                output_per_million_usd: Number(row.querySelector('[data-entry-output]')?.value ?? 0),
            };
        }).filter((entry) => entry.model_card_id);

        if (spinner) {
            spinner.classList.remove('visually-hidden');
            saveButton.setAttribute('disabled', 'true');
        }

        try {
            const response = await fetch(syncUrl, {
                method: 'PUT',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ rates }),
            });

            const payload = await response.json();

            if (!response.ok || payload.status !== 'success') {
                showToast(toast, payload.message || 'Save failed', true);
                if (spinner) {
                    spinner.classList.add('visually-hidden');
                    saveButton.removeAttribute('disabled');
                }
                return;
            }

            showToast(toast, payload.message || 'Saved');
            window.location.reload();
        } catch {
            showToast(toast, 'Save failed', true);
            if (spinner) {
                spinner.classList.add('visually-hidden');
                saveButton.removeAttribute('disabled');
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-inference-rate-plan-admin]').forEach(initInferenceRatePlanAdmin);
});
