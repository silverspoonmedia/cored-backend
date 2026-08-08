import { mountIsland, readIslandBootstrap } from '../../mountIsland.js';
import App from './App.vue';

const mountEl = document.querySelector('#vue-vtual-claim');
const bootstrapEl = document.querySelector('[data-vue-vtual-claim]');
const failMessage = bootstrapEl?.getAttribute('data-vue-mount-failed')
    ?? 'Claim UI failed to load. Refresh the page.';

if (!mountEl) {
    console.error('Vue island: missing #vue-vtual-claim');
} else {
    const bootstrap = readIslandBootstrap('[data-vue-vtual-claim]');

    if (!bootstrap?.profile) {
        mountEl.innerHTML = `<div class="alert alert-danger mb-0" role="alert">${failMessage}</div>`;
        console.error('Vue island: missing or invalid vtual claim bootstrap JSON');
    } else {
        mountIsland('#vue-vtual-claim', App, { bootstrap });
    }
}
