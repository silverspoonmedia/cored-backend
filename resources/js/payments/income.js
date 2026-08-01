function initPaymentIncomePage(root) {
    if (!root || root.dataset.paymentIncomeBound === '1') {
        return;
    }

    root.dataset.paymentIncomeBound = '1';

    const form = root.querySelector('[data-payment-income-filters]');
    const exportLink = root.querySelector('[data-payment-income-export]');
    const exportBase = root.dataset.exportUrl || '';

    form?.addEventListener('submit', () => {
        const params = new URLSearchParams(new FormData(form));
        const paidFrom = params.get('from');
        const paidTo = params.get('to');

        if (exportLink && exportBase) {
            const exportParams = new URLSearchParams();
            if (paidFrom) {
                exportParams.set('paid_from', paidFrom);
            }
            if (paidTo) {
                exportParams.set('paid_to', paidTo);
            }
            exportParams.set('status', 'paid');
            const base = exportBase.split('?')[0];
            exportLink.href = exportParams.toString() ? `${base}?${exportParams}` : base;
        }
    });
}

document.querySelectorAll('[data-payment-income-page]').forEach(initPaymentIncomePage);
