import { createApp } from 'vue';

/**
 * Mount a Vue SFC on a Blade mountpoint when the element exists.
 * No-ops when the selector is missing so shared entries stay safe.
 *
 * @param {string} selector
 * @param {import('vue').Component} rootComponent
 * @param {Record<string, unknown>} [rootProps]
 * @returns {import('vue').App|null}
 */
export function mountIsland(selector, rootComponent, rootProps = {}) {
    const el = document.querySelector(selector);

    if (!el) {
        return null;
    }

    const app = createApp(rootComponent, rootProps);
    app.mount(el);

    return app;
}

/**
 * Parse a JSON bootstrap blob next to an island mountpoint.
 *
 * @param {string} selector  e.g. '[data-vue-dashboard-session]'
 * @returns {Record<string, unknown>}
 */
export function readIslandBootstrap(selector) {
    const el = document.querySelector(selector);

    if (!el) {
        return {};
    }

    try {
        return JSON.parse(el.textContent || '{}');
    } catch (e) {
        console.error('Vue island: invalid bootstrap JSON', selector, e);

        return {};
    }
}
