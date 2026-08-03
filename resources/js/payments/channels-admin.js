function routeRoleWeight(role) {
    if (role === 'only' || role === 'primary') {
        return 0;
    }

    if (role === 'fallback') {
        return 1;
    }

    return 2;
}

function compareRouteItems(a, b) {
    const aRole = routeRoleWeight(a.dataset.routeRole ?? '');
    const bRole = routeRoleWeight(b.dataset.routeRole ?? '');

    if (aRole !== bRole) {
        return aRole - bRole;
    }

    const aPriority = Number.parseInt(
        a.dataset.routePriority ?? a.querySelector('[data-payment-route-priority]')?.value ?? '9999',
        10,
    );
    const bPriority = Number.parseInt(
        b.dataset.routePriority ?? b.querySelector('[data-payment-route-priority]')?.value ?? '9999',
        10,
    );

    return aPriority - bPriority;
}

function initPaymentChannelsAdmin(root) {
    if (!root || root.dataset.paymentChannelsAdminBound === '1') {
        return;
    }

    root.dataset.paymentChannelsAdminBound = '1';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const channelTemplate = root.dataset.updateUrlTemplate || '';
    const routeTemplate = root.dataset.routeUpdateUrlTemplate || '';
    const syncPolicyUrl = root.dataset.syncPolicyUrl || '';
    const messages = JSON.parse(root.dataset.messages || '{}');
    const toast = root.querySelector('[data-payment-admin-toast]');
    const searchInput = root.querySelector('[data-payment-channel-search]');
    const countLabel = root.querySelector('[data-payment-channel-count]');
    const noResults = root.querySelector('[data-payment-channel-no-results]');
    const cards = Array.from(root.querySelectorAll('[data-payment-channel-card]'));

    cards.forEach((card) => resortRouteList(card, messages.primaryRoute));

    searchInput?.addEventListener('input', () => {
        filterChannels(cards, searchInput.value, countLabel, noResults, messages);
    });

    root.querySelector('[data-payment-sync-policy]')?.addEventListener('click', async (event) => {
        const button = event.currentTarget;

        if (!(button instanceof HTMLButtonElement) || !syncPolicyUrl) {
            return;
        }

        button.disabled = true;

        try {
            const response = await fetch(syncPolicyUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            const payload = await response.json();

            if (!response.ok || payload.status !== 'success') {
                throw new Error(payload.message || messages.failed || 'Sync failed.');
            }

            showToast(toast, payload.message || messages.syncPolicyDone, 'success');
            window.setTimeout(() => window.location.reload(), 1200);
        } catch (error) {
            showToast(toast, error instanceof Error ? error.message : (messages.failed || 'Sync failed.'), 'error');
            button.disabled = false;
        }
    });

    root.querySelectorAll('[data-payment-channel-toggle]').forEach((input) => {
        input.addEventListener('change', async () => {
            const code = input.getAttribute('data-payment-channel-toggle') ?? '';
            const card = input.closest('[data-payment-channel-card]');
            const url = channelTemplate.replace('__CODE__', encodeURIComponent(code));
            const previous = !input.checked;

            input.disabled = true;

            try {
                await putJson(url, { is_active: input.checked }, csrfToken, messages);
                showToast(toast, messages.updated, 'success');
                card?.classList.toggle('is-inactive', !input.checked);
                refreshChannelBadges(card, input.checked, messages.inactiveChannel);
            } catch (error) {
                input.checked = previous;
                showToast(toast, error instanceof Error ? error.message : (messages.failed || 'Update failed.'), 'error');
            } finally {
                input.disabled = false;
            }
        });
    });

    root.querySelectorAll('[data-payment-route-toggle]').forEach((input) => {
        input.addEventListener('change', async () => {
            const routeId = input.getAttribute('data-payment-route-toggle') ?? '';
            const routeItem = input.closest('[data-payment-route-item]');
            const card = input.closest('[data-payment-channel-card]');
            const url = routeTemplate.replace('__ROUTE__', encodeURIComponent(routeId));
            const previous = !input.checked;

            input.disabled = true;

            try {
                await putJson(url, { is_active: input.checked }, csrfToken, messages);
                showToast(toast, messages.routeUpdated, 'success');
                routeItem?.classList.toggle('is-inactive', !input.checked);
                resortRouteList(card, messages.primaryRoute);
            } catch (error) {
                input.checked = previous;
                showToast(toast, error instanceof Error ? error.message : (messages.failed || 'Update failed.'), 'error');
            } finally {
                input.disabled = false;
            }
        });
    });

    root.querySelectorAll('[data-payment-route-priority]').forEach((input) => {
        if (input.disabled) {
            return;
        }

        let previous = input.value;

        input.addEventListener('change', async () => {
            const routeId = input.getAttribute('data-payment-route-priority') ?? '';
            const card = input.closest('[data-payment-channel-card]');
            const url = routeTemplate.replace('__ROUTE__', encodeURIComponent(routeId));
            const priority = Number.parseInt(input.value, 10);

            if (Number.isNaN(priority) || priority < 0) {
                input.value = previous;
                return;
            }

            input.disabled = true;

            try {
                await putJson(url, { priority }, csrfToken, messages);
                showToast(toast, messages.routeUpdated, 'success');
                const routeItem = input.closest('[data-payment-route-item]');
                if (routeItem) {
                    routeItem.dataset.routePriority = String(priority);
                }
                resortRouteList(card, messages.primaryRoute);
                previous = String(priority);
            } catch (error) {
                input.value = previous;
                showToast(toast, error instanceof Error ? error.message : (messages.failed || 'Update failed.'), 'error');
            } finally {
                input.disabled = false;
            }
        });
    });
}

function filterChannels(cards, query, countLabel, noResultsEl, messages) {
    const needle = String(query ?? '').trim().toLowerCase();
    let visible = 0;

    cards.forEach((card) => {
        const haystack = [
            card.dataset.channelCode ?? '',
            card.dataset.channelName ?? '',
            card.dataset.channelKind ?? '',
        ].join(' ');

        const matches = needle === '' || haystack.includes(needle);
        card.setAttribute('data-hidden', matches ? 'false' : 'true');

        if (matches) {
            visible += 1;
        }
    });

    if (countLabel) {
        const template = messages?.visibleCountTemplate;

        if (typeof template === 'string') {
            countLabel.textContent = template.replace(':count', String(visible));
        } else {
            countLabel.textContent = formatVisibleCount(visible);
        }
    }

    noResultsEl?.classList.toggle('d-none', visible > 0 || needle === '');
}

function formatVisibleCount(count) {
    if (count === 0) {
        return 'No channels visible';
    }

    if (count === 1) {
        return '1 channel visible';
    }

    return `${count} channels visible`;
}

function refreshChannelBadges(card, isActive, inactiveLabel) {
    if (!card) {
        return;
    }

    const meta = card.querySelector('.pc-channel-card__meta');

    if (!meta) {
        return;
    }

    meta.querySelectorAll('[data-channel-inactive-badge]').forEach((badge) => badge.remove());

    if (!isActive && inactiveLabel) {
        const badge = document.createElement('span');
        badge.className = 'badge text-bg-secondary';
        badge.dataset.channelInactiveBadge = '1';
        badge.textContent = inactiveLabel;
        meta.appendChild(badge);
    }
}

function refreshPrimaryRouteMarkers(card, primaryLabel) {
    if (!card) {
        return;
    }

    const routes = Array.from(card.querySelectorAll('[data-payment-route-item]'));
    const activeRoutes = routes
        .filter((route) => !route.classList.contains('is-inactive'))
        .sort(compareRouteItems);

    const primaryId = activeRoutes[0]?.dataset.routeId ?? null;

    routes.forEach((route, index) => {
        const rank = route.querySelector('.pc-route-rank');
        const isPrimary = route.dataset.routeId === primaryId;
        route.classList.toggle('is-primary', isPrimary);

        if (rank) {
            rank.textContent = String(index + 1);
        }

        route.querySelectorAll('[data-route-primary-badge]').forEach((badge) => badge.remove());

        if (isPrimary && primaryLabel) {
            const gatewayLine = route.querySelector('.pc-route-gateway');

            if (gatewayLine) {
                const badge = document.createElement('span');
                badge.className = 'badge text-bg-success ms-1';
                badge.dataset.routePrimaryBadge = '1';
                badge.textContent = primaryLabel;
                gatewayLine.appendChild(badge);
            }
        }
    });
}

function resortRouteList(card, primaryLabel) {
    if (!card) {
        return;
    }

    const list = card.querySelector('.pc-route-list');

    if (!list) {
        return;
    }

    const items = Array.from(list.querySelectorAll('[data-payment-route-item]'));

    items.sort(compareRouteItems);

    const active = document.activeElement;
    const activeItem = active ? active.closest('[data-payment-route-item]') : null;
    const activeId = active && active.id ? active.id : null;
    const restoreFocus = activeItem && list.contains(activeItem);

    items.forEach((item) => list.appendChild(item));
    refreshPrimaryRouteMarkers(card, primaryLabel);

    if (restoreFocus && activeId) {
        const restored = document.getElementById(activeId);
        if (restored && typeof restored.focus === 'function') {
            restored.focus();
        }
    }
}

function showToast(element, message, type) {
    if (!element) {
        return;
    }

    const toast = document.createElement('div');
    toast.className = 'monolith-toast is-' + (type === 'error' ? 'error' : 'success');
    toast.setAttribute('role', 'status');
    toast.textContent = message;
    element.appendChild(toast);

    window.setTimeout(() => {
        toast.classList.add('is-leaving');
        window.setTimeout(() => toast.remove(), 300);
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

document.querySelectorAll('[data-payment-channels-admin]').forEach(initPaymentChannelsAdmin);
