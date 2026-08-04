<script setup>
import { ref, computed } from 'vue';
import { apiFetch, unwrapData } from '../../composables/useApi.js';

const props = defineProps({
    profile: {
        type: Object,
        default: () => ({}),
    },
    meUrl: {
        type: String,
        required: true,
    },
    labels: {
        type: Object,
        default: () => ({}),
    },
});

const liveProfile = ref({ ...props.profile });
const loading = ref(false);
const error = ref('');
const refreshedAt = ref(null);

const displayName = computed(() => liveProfile.value?.name ?? '—');
const email = computed(() => liveProfile.value?.email ?? '—');
const roles = computed(() => {
    const list = liveProfile.value?.roles;

    return Array.isArray(list) ? list : [];
});

async function refresh() {
    if (loading.value) {
        return;
    }

    loading.value = true;
    error.value = '';

    const { ok, data, networkError } = await apiFetch(props.meUrl);

    loading.value = false;

    if (networkError) {
        error.value = props.labels.loadFailed ?? 'Request failed.';

        return;
    }

    if (!ok) {
        error.value = (typeof data.message === 'string' && data.message)
            || (props.labels.loadFailed ?? 'Request failed.');

        return;
    }

    const next = unwrapData(data);

    if (next && typeof next === 'object') {
        liveProfile.value = next;
        refreshedAt.value = new Date().toISOString();
    }
}
</script>

<template>
    <div class="card mb-0">
        <div class="card-header">
            <h3 class="card-title mb-0">{{ labels.title ?? 'Session' }}</h3>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">{{ labels.hint }}</p>

            <div
                v-if="error"
                class="alert alert-danger mb-3"
                role="alert"
            >
                {{ error }}
            </div>

            <dl class="row mb-3">
                <dt class="col-sm-4">{{ labels.name ?? 'Name' }}</dt>
                <dd class="col-sm-8">{{ displayName }}</dd>
                <dt class="col-sm-4">{{ labels.email ?? 'Email' }}</dt>
                <dd class="col-sm-8">{{ email }}</dd>
                <dt class="col-sm-4">{{ labels.roles ?? 'Roles' }}</dt>
                <dd class="col-sm-8">
                    <template v-if="roles.length">
                        <span
                            v-for="role in roles"
                            :key="role"
                            class="badge text-bg-primary me-1"
                        >{{ role }}</span>
                    </template>
                    <span
                        v-else
                        class="text-muted"
                    >{{ labels.noRoles ?? '—' }}</span>
                </dd>
            </dl>

            <button
                type="button"
                class="btn btn-sm btn-outline-primary"
                :disabled="loading"
                @click="refresh"
            >
                <span
                    v-if="loading"
                    class="spinner-border spinner-border-sm me-1"
                    role="status"
                    aria-hidden="true"
                />
                {{ loading ? (labels.refreshing ?? '…') : (labels.refresh ?? 'Refresh') }}
            </button>

            <p
                v-if="refreshedAt"
                class="text-muted small mt-2 mb-0"
            >
                {{ labels.refreshedAt ?? 'Updated' }}: {{ refreshedAt }}
            </p>
        </div>
    </div>
</template>
