<script setup>
import { ref, computed, reactive, watch } from 'vue';
import { apiFetch, unwrapData } from '../../composables/useApi.js';
import { useClaimRealtime } from './composables/useClaimRealtime.js';

const props = defineProps({
    bootstrap: { type: Object, required: true },
});

const profile = props.bootstrap.profile ?? {};
const labels = props.bootstrap.labels ?? {};
const routes = props.bootstrap.routes ?? {};
const realtimeConfig = reactive(props.bootstrap.realtime ?? {});
const discordConnected = props.bootstrap.discordConnected ?? false;
const discordVerifyEnabled = props.bootstrap.discordVerifyEnabled ?? false;
const canAcceptAnyway = props.bootstrap.canAcceptAnyway ?? false;

// State
const claim = ref(props.bootstrap.activeClaim ?? null);
const blockedUntil = ref(
    profile?.claim_blocked_until_formatted
    ?? profile?.claim_blocked_until
    ?? props.bootstrap.activeClaim?.blocked_until_formatted
    ?? props.bootstrap.activeClaim?.blocked_until
    ?? null,
);
const failedMessage = ref('');
const channels = ref(profile?.channels ?? []);
const submitting = ref(false);

// Realtime
const realtimeRef = ref(realtimeConfig);
const claimIdRef = computed(() => claim.value?.id ?? null);

function onProgress(phase, claimData) {
    if (claimData?.id && claimData.id !== claimIdRef.value) {
        realtimeRef.value.claimId = claimData.id;
        realtimeComposable.resubscribeTo(claimData.id);
    }

    applySnapshot(claimData, phase);
}

const { connected, enabled, resubscribeTo } = useClaimRealtime(
    realtimeRef,
    claimIdRef,
    onProgress,
);
const realtimeComposable = { resubscribeTo };

// Computed
const displayName = computed(() => profile?.display_name ?? labels?.title ?? '');
const profileUrl = computed(() => props.bootstrap.profileUrl ?? '');
const hasActiveClaim = computed(() => claim.value?.status === 'pending');
const isBlocked = computed(() => !!blockedUntil.value);

const proofChannel = computed(() => hasActiveClaim.value ? (claim.value?.channel ?? null) : null);
const showProofChannel = computed(() => !!proofChannel.value);
const canVerify = computed(() => hasActiveClaim.value && claim.value?.can_verify === true);
const verifyQueueStatus = computed(() => claim.value?.verify_queue_status ?? null);
const showQueueStatus = computed(() => !!verifyQueueStatus.value);
const queueBadgeLabel = computed(() => {
    const status = verifyQueueStatus.value;
    if (!status) return '';
    return labels[`queue_${status}`] ?? status;
});
const realtimeNotice = computed(() =>
    enabled.value ? labels?.realtimeNotice : labels?.degradedNotice,
);

const proofPlatformKey = computed(() => proofChannel.value?.platform?.key ?? '');
const showDiscordVerify = computed(
    () => discordVerifyEnabled && proofPlatformKey.value && proofPlatformKey.value !== 'kick',
);

const showChannelsSection = computed(() => !hasActiveClaim.value && !isBlocked.value);

// Helpers
function toast(message, type = 'success') {
    if (window.MonolithSwal?.toastMessage) {
        window.MonolithSwal.toastMessage(message, type === 'danger' ? 'error' : type);
    }
}

function derivePhaseFromClaimForm(formSelector) {
    if (formSelector?.includes('claim-start')) return 'started';
    if (formSelector?.includes('claim-verify')) return 'verify_queued';
    if (formSelector?.includes('claim-cancel')) return 'cancelled';
    if (formSelector?.includes('discord-verify') || formSelector?.includes('discord')) {
        return claim.value?.status === 'verified' ? 'verified' : 'verify_failed';
    }
    return '';
}

async function submitAction(url, method, body, phaseOverride) {
    if (submitting.value) return;
    submitting.value = true;

    const { ok, data, networkError } = await apiFetch(url, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
    });

    submitting.value = false;

    if (networkError) {
        toast(labels?.networkError ?? 'Network error.', 'error');
        return;
    }

    const result = unwrapData(data);
    if (result && typeof result === 'object' && result.id) {
        claim.value = result;
        if (result.id !== claimIdRef.value) {
            realtimeRef.value.claimId = result.id;
            resubscribeTo(result.id);
        }
    }

    const phase = phaseOverride ?? derivePhaseFromClaimForm(url);
    applySnapshot(claim.value, phase ?? '');
}

function applySnapshot(claimData, phase) {
    if (!claimData || typeof claimData !== 'object') return;

    const hasClaimId = !!claimData.id;

    if (phase === 'started' || (!hasActiveClaim.value && hasClaimId && claimData.status === 'pending')) {
        failedMessage.value = '';
        claim.value = claimData;
        if (claimData.id) {
            realtimeRef.value.claimId = claimData.id;
            resubscribeTo(claimData.id);
        }
        toast(labels?.started ?? 'Claim started.', 'success');
        return;
    }

    if (phase === 'cancelled' || (phase === 'max_attempts_blocked')) {
        failedMessage.value = '';
        if (phase === 'max_attempts_blocked') {
            blockedUntil.value = claimData.blocked_until_formatted ?? claimData.blocked_until ?? null;
            toast(labels?.maxAttemptsBlocked ?? 'Max attempts reached.', 'warning');
        }
        claim.value = null;
        return;
    }

    if (phase === ('max_attempts_blocked') || (!hasClaimId && (phase === 'cancelled' || claimData?.status === 'cancelled' || claimData?.status === 'max_attempts_blocked'))) {
        failedMessage.value = '';
        if (claimData?.status === 'max_attempts_blocked') {
            blockedUntil.value = claimData.blocked_until_formatted ?? claimData.blocked_until ?? null;
            toast(labels?.maxAttemptsBlocked ?? 'Max attempts reached.', 'warning');
        }
        claim.value = null;
        return;
    }

    if (phase === 'verified') {
        failedMessage.value = '';
        toast(labels?.verified ?? 'Claim verified.', 'success');
        const target = claimData.profile_url ?? props.bootstrap.profileUrl;
        if (target) {
            window.setTimeout(() => window.location.assign(target), 800);
        }
        return;
    }

    if (!hasActiveClaim.value) return;

    if (phase === 'verify_failed') {
        claim.value = claimData;
        failedMessage.value = labels?.failed ?? 'Token not found in channel bio.';
        toast(labels?.failed ?? 'Token not found.', 'warning');
        return;
    }

    if (phase === 'verify_queued' || phase === 'verify_processing') {
        failedMessage.value = '';
        claim.value = claimData;
        if (phase === 'verify_queued') {
            toast(labels?.queued ?? 'Verification queued.', 'info');
        } else {
            toast(labels?.processing ?? 'Processing verification…', 'info');
        }
        return;
    }

    // Generic update
    claim.value = claimData;
}

// Actions
async function startClaim(channelId) {
    await submitAction(
        routes.startClaim,
        'POST',
        { creator_id: profile.id ?? '', channel_id: channelId },
        'started',
    );
}

async function verifyClaim() {
    await submitAction(
        routes.verifyClaim,
        'POST',
        { claim_id: claimIdRef.value ?? '' },
        'verify_queued',
    );
}

async function cancelClaim() {
    await submitAction(
        routes.cancelClaim,
        'POST',
        { claim_id: claimIdRef.value ?? '' },
        'cancelled',
    );
}

async function acceptAnywayClaim() {
    const confirmed = await confirm(
        labels.acceptAnywayConfirmBody
        ?? 'Accept this claim without verifying the channel bio? Confirm claimant identity first.',
    );
    if (!confirmed) return;

    await submitAction(
        routes.acceptAnywayClaim,
        'POST',
        { claim_id: claimIdRef.value ?? '' },
        'verified',
    );
}

async function discordVerifyClaim() {
    await submitAction(
        routes.discordVerifyClaim,
        'POST',
        { claim_id: claimIdRef.value ?? '' },
        null, // phase derived from result
    );
}

async function discordClaimChannel(channelId) {
    await submitAction(
        routes.discordClaimChannel,
        'POST',
        { channel_id: channelId },
        null,
    );
}

function discordRedirectForClaim() {
    const slug = profile?.slug ?? '';
    window.location.assign(`${routes.discordRedirect}?intent=claim_verify&slug=${encodeURIComponent(slug)}&claim_id=${encodeURIComponent(claimIdRef.value ?? '')}`);
}
</script>

<template>
    <div class="card-header d-flex align-items-center gap-2">
        <h3 class="card-title mb-0 flex-grow-1 float-none">{{ labels.claimPageTitle ?? 'Claim this profile' }}</h3>
        <div class="card-tools float-none me-0 flex-shrink-0">
            <a :href="profileUrl" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1" aria-hidden="true" />
                {{ labels.backToProfile ?? 'Back to profile' }}
            </a>
        </div>
    </div>

    <div class="card-body">
        <p class="text-muted small mb-3">{{ labels.claimPageSubtitle ?? 'Prove ownership.' }}</p>

        <!-- Realtime connection badge -->
        <template v-if="enabled">
            <div
                class="d-flex align-items-center gap-2 small mb-3"
                :class="connected ? 'text-success' : 'text-secondary'"
                role="status"
                aria-live="polite"
            >
                <i class="bi bi-broadcast" :class="{ 'text-success': connected }" aria-hidden="true" />
                <span>{{ connected ? (labels.connected ?? 'Live updates') : (labels.connecting ?? 'Connecting…') }}</span>
            </div>
        </template>

        <!-- Degraded banner -->
        <div v-if="enabled && !connected" class="alert alert-info small mb-3" role="status">
            {{ labels.degraded ?? 'Realtime unavailable.' }}
        </div>

        <!-- Blocked banner -->
        <div v-if="isBlocked && !hasActiveClaim" class="alert alert-warning small mb-3" role="alert">
            {{ (labels.blockedUntil ?? 'Blocked until :until.').replace(':until', blockedUntil) }}
        </div>

        <!-- Active claim panel -->
        <div v-if="hasActiveClaim" class="border rounded p-3 mb-4">
            <h5 class="h6 mb-2">{{ labels.activeHeading ?? 'Active claim' }}</h5>

            <dl class="row small mb-3">
                <dt :class="['col-sm-4', { 'd-none': !showProofChannel }]">
                    {{ labels.proofChannelLabel ?? 'Proof channel' }}
                </dt>
                <dd :class="['col-sm-8 mb-0', { 'd-none': !showProofChannel }]">
                    <template v-if="showProofChannel">
                        <span class="badge text-bg-light border">
                            {{ proofChannel.platform?.name ?? proofChannel.platform?.key ?? '—' }}
                        </span>
                        <span class="ms-1">{{ proofChannel.handle ?? proofChannel.display_name ?? proofChannel.external_id ?? '' }}</span>
                        <a
                            v-if="proofChannel.url"
                            :href="proofChannel.url"
                            class="btn btn-outline-secondary btn-sm ms-2"
                            target="_blank"
                            rel="noopener noreferrer"
                        >{{ labels.openLink ?? 'Open' }}</a>
                    </template>
                </dd>
            </dl>

            <!-- Failed banner -->
            <div v-if="failedMessage" class="alert alert-warning small mb-3" role="alert">
                {{ failedMessage }}
            </div>

            <p class="fw-semibold mb-1">{{ labels.tokenLabel ?? 'Claim token' }}</p>
            <p class="mb-2"><code class="user-select-all">{{ claim.token ?? '' }}</code></p>
            <p class="text-muted small mb-3">{{ labels.tokenHelp ?? '' }}</p>

            <dl class="row small mb-3">
                <dt class="col-sm-4">{{ labels.attemptsRemaining ?? 'Attempts remaining' }}</dt>
                <dd class="col-sm-8 mb-0">{{ claim.attempts_remaining ?? '—' }}</dd>
                <dt :class="['col-sm-4', { 'd-none': !showQueueStatus }]">
                    {{ labels.queueStatusLabel ?? 'Queue status' }}
                </dt>
                <dd :class="['col-sm-8 mb-0', { 'd-none': !showQueueStatus }]">
                    <span class="badge text-bg-info">{{ queueBadgeLabel }}</span>
                </dd>
            </dl>

            <p class="text-muted small mb-3">{{ realtimeNotice }}</p>

            <div class="d-flex flex-wrap gap-2">
                <div v-if="canVerify">
                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        :disabled="submitting"
                        @click="verifyClaim"
                    >
                        <span
                            v-if="submitting"
                            class="spinner-border spinner-border-sm me-1"
                            role="status"
                            aria-hidden="true"
                        />
                        {{ labels.verifyButton ?? 'Verify' }}
                    </button>
                </div>

                <button
                    type="button"
                    class="btn btn-outline-danger btn-sm"
                    :disabled="submitting"
                    @click="cancelClaim"
                >
                    {{ labels.cancel ?? 'Cancel claim' }}
                </button>

                <button
                    v-if="canAcceptAnyway"
                    type="button"
                    class="btn btn-warning btn-sm"
                    :disabled="submitting"
                    @click="acceptAnywayClaim"
                >
                    {{ labels.acceptAnyway ?? 'Accept Anyway' }}
                </button>

                <template v-if="showDiscordVerify">
                    <template v-if="discordConnected">
                        <button
                            type="button"
                            class="btn btn-outline-primary btn-sm"
                            :disabled="submitting"
                            @click="discordVerifyClaim"
                        >
                            {{ labels.discordVerifyButton ?? 'Verify with Discord' }}
                        </button>
                    </template>
                    <template v-else>
                        <a
                            href="#"
                            class="btn btn-outline-primary btn-sm"
                            @click.prevent="discordRedirectForClaim"
                        >
                            {{ labels.discordConnectCta ?? 'Connect Discord to verify' }}
                        </a>
                    </template>
                    <p class="text-muted small mt-2 mb-0">{{ labels.discordPanelHint ?? '' }}</p>
                </template>
            </div>
        </div>

        <!-- Channels section (pre-claim) -->
        <div v-if="showChannelsSection">
            <h5 class="h6 mb-2">{{ labels.channelsHeading ?? 'Channels on this profile' }}</h5>
            <p v-if="channels.length === 0" class="text-muted small mb-4">{{ labels.noChannelsNote ?? 'No channels linked yet.' }}</p>
            <ul v-else class="list-group list-group-flush mb-4">
                <li v-for="channel in channels" :key="channel.id" class="list-group-item px-0">
                    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <span>
                            <span class="badge text-bg-light border">
                                {{ channel.platform?.name ?? channel.platform?.key ?? '—' }}
                            </span>
                            <span class="ms-1">{{ channel.handle ?? channel.display_name ?? channel.external_id ?? '' }}</span>
                        </span>
                        <div class="d-flex gap-2">
                            <a
                                v-if="channel.url"
                                :href="channel.url"
                                class="btn btn-outline-secondary btn-sm"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ labels.openLink ?? 'Open' }}
                            </a>
                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                :disabled="submitting"
                                @click="startClaim(channel.id)"
                            >
                                <span
                                    v-if="submitting"
                                    class="spinner-border spinner-border-sm me-1"
                                    role="status"
                                    aria-hidden="true"
                                />
                                {{ labels.verifyWithChannel ?? 'Verify with this channel' }}
                            </button>
                            <template v-if="discordVerifyEnabled && (channel.platform?.key ?? '') !== 'kick'">
                                <button
                                    v-if="discordConnected"
                                    type="button"
                                    class="btn btn-outline-primary btn-sm"
                                    :disabled="submitting"
                                    @click="discordClaimChannel(channel.id)"
                                >
                                    {{ labels.claimWithDiscord ?? 'Claim with Discord' }}
                                </button>
                                <a
                                    v-else
                                    :href="`${routes.discordRedirect}?intent=claim_channel&slug=${encodeURIComponent(profile?.slug ?? '')}&channel_id=${encodeURIComponent(channel.id ?? '')}`"
                                    class="btn btn-outline-primary btn-sm"
                                >
                                    {{ labels.discordConnectCta ?? 'Connect Discord to verify' }}
                                </a>
                            </template>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Steps -->
        <details class="small">
            <summary class="fw-semibold mb-2">{{ labels.stepsHeading ?? 'How claiming works' }}</summary>
            <ol class="mb-0 mt-2">
                <li>{{ labels.step1 }}</li>
                <li>{{ labels.step2 }}</li>
                <li>{{ labels.step3 }}</li>
                <li>{{ labels.step4 }}</li>
                <li>{{ labels.step5 }}</li>
            </ol>
        </details>
    </div>
</template>
