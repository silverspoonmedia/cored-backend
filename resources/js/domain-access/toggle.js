function initDomainAccess(root) {
    if (!root || root.dataset.domainAccessBound === '1') {
        return;
    }

    root.dataset.domainAccessBound = '1';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const template = root.dataset.updateUrlTemplate || '';
    const messages = JSON.parse(root.dataset.messages || '{}');
    const toast = root.querySelector('[data-domain-access-toast]');

    root.querySelectorAll('[data-domain-toggle]').forEach((input) => {
        input.addEventListener('change', async () => {
            const key = input.getAttribute('data-domain-toggle') ?? '';
            const card = input.closest('[data-domain-card]');
            const url = template.replace('__KEY__', encodeURIComponent(key));
            const previous = !input.checked;
            const visibility = input.checked ? 'private' : 'public';

            input.disabled = true;

            try {
                await putJson(url, { visibility }, csrfToken);
                applyVisibility(card, visibility, messages);
                showToast(toast, messages.updated || 'Updated.', 'success');
            } catch (error) {
                input.checked = previous;
                showToast(toast, error instanceof Error ? error.message : (messages.failed || 'Update failed.'), 'error');
            } finally {
                input.disabled = false;
            }
        });
    });
}

function applyVisibility(card, visibility, messages) {
    if (!card) {
        return;
    }

    const isPrivate = visibility === 'private';
    const badge = card.querySelector('[data-domain-visibility-badge]');
    const hint = card.querySelector('[data-domain-hint]');

    if (badge) {
        badge.textContent = isPrivate ? (messages.private || 'Private') : (messages.public || 'Public');
        badge.classList.remove('text-bg-success', 'text-bg-warning');
        badge.classList.add(isPrivate ? 'text-bg-warning' : 'text-bg-success');
    }

    if (hint) {
        hint.textContent = isPrivate ? (messages.privateHint || '') : (messages.publicHint || '');
    }
}

function showToast(element, message, type) {
    if (!element) {
        return;
    }

    element.textContent = message;
    element.classList.remove('is-success', 'is-error', 'is-visible');
    element.classList.add(type === 'error' ? 'is-error' : 'is-success');

    window.requestAnimationFrame(() => {
        element.classList.add('is-visible');
    });

    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(() => {
        element.classList.remove('is-visible');
    }, 2800);
}

async function putJson(url, body, csrfToken) {
    const response = await fetch(url, {
        method: 'PUT',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    const payload = await response.json();

    if (!response.ok || payload.status !== 'success') {
        throw new Error(payload.message || 'Update failed.');
    }
}

document.querySelectorAll('[data-domain-access]').forEach(initDomainAccess);
