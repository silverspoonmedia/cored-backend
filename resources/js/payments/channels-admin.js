function initPaymentChannelsAdmin(root) {
    if (!root || root.dataset.paymentChannelsAdminBound === '1') {
        return;
    }

    root.dataset.paymentChannelsAdminBound = '1';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    const channelTemplate = root.dataset.updateUrlTemplate || '';
    const routeTemplate = root.dataset.routeUpdateUrlTemplate || '';
    const messages = JSON.parse(root.dataset.messages || '{}');

    root.querySelectorAll('[data-payment-channel-toggle]').forEach((input) => {
        input.addEventListener('change', async () => {
            const code = input.getAttribute('data-payment-channel-toggle') ?? '';
            const url = channelTemplate.replace('__CODE__', encodeURIComponent(code));
            const previous = !input.checked;

            input.disabled = true;

            try {
                await putJson(url, { is_active: input.checked }, csrfToken, messages);
            } catch (error) {
                input.checked = previous;
                window.alert(error instanceof Error ? error.message : (messages.failed || 'Update failed.'));
            } finally {
                input.disabled = false;
            }
        });
    });

    root.querySelectorAll('[data-payment-route-toggle]').forEach((input) => {
        input.addEventListener('change', async () => {
            const routeId = input.getAttribute('data-payment-route-toggle') ?? '';
            const url = routeTemplate.replace('__ROUTE__', encodeURIComponent(routeId));
            const previous = !input.checked;

            input.disabled = true;

            try {
                await putJson(url, { is_active: input.checked }, csrfToken, messages, messages.routeUpdated);
            } catch (error) {
                input.checked = previous;
                window.alert(error instanceof Error ? error.message : (messages.failed || 'Update failed.'));
            } finally {
                input.disabled = false;
            }
        });
    });

    root.querySelectorAll('[data-payment-route-priority]').forEach((input) => {
        let previous = input.value;

        input.addEventListener('change', async () => {
            const routeId = input.getAttribute('data-payment-route-priority') ?? '';
            const url = routeTemplate.replace('__ROUTE__', encodeURIComponent(routeId));
            const priority = Number.parseInt(input.value, 10);

            if (Number.isNaN(priority) || priority < 0) {
                input.value = previous;
                return;
            }

            input.disabled = true;

            try {
                await putJson(url, { priority }, csrfToken, messages, messages.routeUpdated);
                previous = String(priority);
            } catch (error) {
                input.value = previous;
                window.alert(error instanceof Error ? error.message : (messages.failed || 'Update failed.'));
            } finally {
                input.disabled = false;
            }
        });
    });
}

async function putJson(url, body, csrfToken, messages, successMessage = messages.updated) {
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
        throw new Error(payload.message || messages.failed || 'Update failed.');
    }

    if (successMessage) {
        window.alert(successMessage);
    }
}

document.querySelectorAll('[data-payment-channels-admin]').forEach(initPaymentChannelsAdmin);
