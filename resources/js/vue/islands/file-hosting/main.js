import { mountIsland, readIslandBootstrap } from '../../mountIsland.js';
import App from './App.vue';

const mountEl = document.querySelector('#vue-file-hosting');
const bootstrapEl = document.querySelector('[data-vue-file-hosting]');
const failMessage = bootstrapEl?.getAttribute('data-vue-mount-failed')
    || 'File Hosting UI failed to load. Refresh the page.';

if (!mountEl) {
    console.error('Vue island: missing #vue-file-hosting');
} else {
    const bootstrap = readIslandBootstrap('[data-vue-file-hosting]');

    if (!bootstrap.config) {
        mountEl.innerHTML = `<div class="alert alert-danger mb-0" role="alert">${failMessage}</div>`;
        console.error('Vue island: missing or invalid FH bootstrap JSON');
    } else {
        mountIsland('#vue-file-hosting', App, { bootstrap });
    }
}
