import { mountIsland, readIslandBootstrap } from '../../mountIsland.js';
import App from './App.vue';

const bootstrap = readIslandBootstrap('[data-vue-dashboard-session]');

mountIsland('#vue-dashboard-session', App, {
    profile: bootstrap.profile ?? {},
    meUrl: bootstrap.meUrl ?? '',
    labels: bootstrap.labels ?? {},
});
