/**
 * Profiles show page — live DOM updates for all mutation types.
 *
 * Hooks into the existing ajax-form:success event. No Vue, no reactive state.
 * Reads table element IDs (set on Blade tbodys) to target rows correctly.
 */
(function () {
    'use strict';

    var routeKey = document.querySelector('[data-profile-route-key]')?.getAttribute('data-profile-route-key') ?? '';
    var profileRoot = document.querySelector('[data-profile-route-key]');
    var canMutate = profileRoot?.getAttribute('data-profile-can-mutate') === '1';
    var canAcceptAnyway = profileRoot?.getAttribute('data-profile-can-accept-anyway') === '1';
    var statusLabels = {};
    var actionLabels = {};
    var linkActionLabels = {};
    var channelUrls = {
        refresh: profileRoot?.dataset.channelRefreshUrl || '',
        archive: profileRoot?.dataset.channelArchiveUrl || '',
        restore: profileRoot?.dataset.channelRestoreUrl || '',
        promote: profileRoot?.dataset.channelPromoteUrl || '',
    };
    var linkDestroyUrl = profileRoot?.dataset.linkDestroyUrl || '';
    var emptyRowColspan = {
        claims: canMutate ? 6 : 5,
        active: canMutate ? 7 : 6,
        archived: canMutate ? 5 : 4,
        links: canMutate ? 4 : 3,
    };
    var emptyRowConfig = {
        'profile-active-channels-tbody': { id: 'profile-active-channels-empty', colspan: emptyRowColspan.active },
        'profile-archived-channels-tbody': { id: 'profile-archived-channels-empty', colspan: emptyRowColspan.archived },
        'profile-channel-claims-tbody': { id: 'profile-channel-claims-empty', colspan: emptyRowColspan.claims },
        'profile-links-tbody': { id: 'profile-links-empty', colspan: emptyRowColspan.links },
    };

    // ── Bootstrap state from Blade data attributes ──────────────────────

    var statusEl = document.getElementById('profile-active-channels-table');
    if (statusEl) {
        try { statusLabels = JSON.parse(statusEl.dataset.statusLabels || '{}'); } catch (e) {}
    }

    if (statusEl) {
        try { actionLabels = JSON.parse(statusEl.dataset.actionLabels || '{}'); } catch (e) {}
    }

    var linksEl = document.getElementById('profile-links-table');
    if (linksEl) {
        try { linkActionLabels = JSON.parse(linksEl.dataset.linkActionLabels || '{}'); } catch (e) {}
    }

    // ── Helpers ────────────────────────────────────────────────────────

    function extractResource(payload) {
        if (!payload || typeof payload !== 'object') return null;
        return payload.data ?? payload;
    }

    function getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    }

    function badgeClass(status) {
        if (status === 'active') return 'success';
        if (status === 'pending') return 'warning';
        return 'secondary';
    }

    function channelActionUrl(template, channelId) {
        if (!template || !channelId) return '';
        return template.replace('__CHANNEL__', encodeURIComponent(channelId));
    }

    function linkActionUrl(template, linkId) {
        if (!template || !linkId) return '';
        return template.replace('__LINK__', encodeURIComponent(linkId));
    }

    function emptyRowMeta(tbodyId) {
        return emptyRowConfig[tbodyId] || { id: tbodyId.replace('tbody', 'empty'), colspan: 4 };
    }

    function createForm(action, method) {
        var form = document.createElement('form');
        form.setAttribute('data-ajax-form', '');
        form.method = 'POST';
        form.action = action;
        form.novalidate = true;
        var csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = getCsrfToken();
        form.appendChild(csrfInput);
        if (method && method.toUpperCase() !== 'POST') {
            var hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = '_method';
            hidden.value = method.toUpperCase();
            form.appendChild(hidden);
        }
        return form;
    }

    function confirmBtn(label, title, body, submitLabel, submitClass) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline-danger btn-sm';
        btn.textContent = label;
        btn.setAttribute('data-bs-toggle', 'modal');
        btn.setAttribute('data-bs-target', '#vtualConfirmModal');
        btn.setAttribute('data-confirm-title', title);
        btn.setAttribute('data-confirm-body', body);
        btn.setAttribute('data-confirm-submit-label', submitLabel);
        if (submitClass) btn.setAttribute('data-confirm-submit-class', submitClass);
        return btn;
    }

    function platformName(channel) {
        return (channel.platform && (channel.platform.name || channel.platform.key)) || '';
    }

    // ── Row bulders ────────────────────────────────────────────────────

    function buildActiveChannelRow(channel) {
        var row = document.createElement('tr');
        if (channel.id) row.setAttribute('data-channel-id', channel.id);

        // Avatar
        var avatarTd = document.createElement('td');
        avatarTd.className = 'text-nowrap';
        if (channel.avatar_url) {
            var img = document.createElement('img');
            img.src = channel.avatar_url;
            img.alt = (channel.display_name || channel.handle || '');
            img.className = 'rounded-circle object-fit-cover';
            img.width = 36; img.height = 36;
            img.loading = 'lazy';
            img.referrerPolicy = 'no-referrer';
            avatarTd.appendChild(img);
        } else {
            var ph = document.createElement('span');
            ph.className = 'text-muted small';
            ph.textContent = '\u2014';
            avatarTd.appendChild(ph);
        }
        row.appendChild(avatarTd);

        // Platform
        var platTd = document.createElement('td');
        platTd.textContent = platformName(channel);
        row.appendChild(platTd);

        // Profile / external_id
        var profTd = document.createElement('td');
        var code = document.createElement('code');
        code.className = (channel.profile_input ? 'user-select-all' : '');
        code.textContent = channel.profile_input || channel.external_id || '';
        profTd.appendChild(code);
        row.appendChild(profTd);

        // Handle
        var handleTd = document.createElement('td');
        handleTd.textContent = channel.handle || '\u2014';
        row.appendChild(handleTd);

        // Bio
        var bioTd = document.createElement('td');
        bioTd.className = 'small text-muted';
        var desc = channel.description || '';
        bioTd.textContent = desc || '\u2014';
        if (desc) bioTd.title = desc;
        row.appendChild(bioTd);

        // Status + visibility + resolve_error
        var statusTd = document.createElement('td');
        var badge = document.createElement('span');
        badge.className = 'badge text-bg-' + badgeClass(channel.status);
        badge.textContent = statusLabels[channel.status] || channel.status || '';
        statusTd.appendChild(badge);
        if ((channel.visibility || 'public') === 'private') {
            var visBadge = document.createElement('span');
            visBadge.className = 'badge text-bg-secondary ms-1';
            visBadge.textContent = actionLabels.private || 'Private';
            statusTd.appendChild(visBadge);
        }
        if (channel.resolve_error) {
            var errDiv = document.createElement('div');
            errDiv.className = 'form-text text-danger';
            errDiv.textContent = channel.resolve_error;
            statusTd.appendChild(errDiv);
        }
        row.appendChild(statusTd);

        // Actions
        var actionsTd = document.createElement('td');
        actionsTd.className = 'text-end text-nowrap';
        if (canMutate) {
            var group = document.createElement('div');
            group.className = 'btn-group';
            group.setAttribute('role', 'group');

            if ((channel.visibility || 'public') === 'private' && channel.discovery_source === 'discord_connection') {
                var promoteForm = createForm(channelActionUrl(channelUrls.promote, channel.id));
                var promoteBtn = document.createElement('button');
                promoteBtn.type = 'submit';
                promoteBtn.className = 'btn btn-outline-primary btn-sm';
                promoteBtn.textContent = actionLabels.promote || 'Promote';
                promoteForm.appendChild(promoteBtn);
                group.appendChild(promoteForm);
            }

            var refreshForm = createForm(channelActionUrl(channelUrls.refresh, channel.id));
            var refreshBtn = document.createElement('button');
            refreshBtn.type = 'submit';
            refreshBtn.className = 'btn btn-outline-secondary btn-sm';
            refreshBtn.textContent = actionLabels.refresh || 'Refresh';
            refreshForm.appendChild(refreshBtn);
            group.appendChild(refreshForm);

            var archiveForm = createForm(channelActionUrl(channelUrls.archive, channel.id));
            var archiveBtn = confirmBtn(
                actionLabels.archive || 'Archive',
                (actionLabels.confirm && actionLabels.confirm.archive_title) || 'Archive channel?',
                (actionLabels.confirm && actionLabels.confirm.archive_body) || 'This will archive the channel.',
                (actionLabels.confirm && actionLabels.confirm.archive_submit) || 'Archive',
                'btn-danger'
            );
            archiveForm.appendChild(archiveBtn);
            group.appendChild(archiveForm);

            actionsTd.appendChild(group);
        }
        row.appendChild(actionsTd);

        return row;
    }

    function buildArchivedChannelRow(channel) {
        var row = document.createElement('tr');
        if (channel.id) row.setAttribute('data-channel-id', channel.id);

        var platTd = document.createElement('td');
        platTd.textContent = platformName(channel);
        row.appendChild(platTd);

        var extTd = document.createElement('td');
        var code = document.createElement('code');
        code.textContent = channel.external_id || '';
        extTd.appendChild(code);
        row.appendChild(extTd);

        var handleTd = document.createElement('td');
        handleTd.textContent = channel.handle || '\u2014';
        row.appendChild(handleTd);

        var statusTd = document.createElement('td');
        var badge = document.createElement('span');
        badge.className = 'badge text-bg-' + badgeClass(channel.status);
        badge.textContent = statusLabels[channel.status] || channel.status || '';
        statusTd.appendChild(badge);
        row.appendChild(statusTd);

        if (canMutate) {
            var actionsTd = document.createElement('td');
            actionsTd.className = 'text-end text-nowrap';
            var restoreForm = createForm(channelActionUrl(channelUrls.restore, channel.id));
            var restoreBtn = confirmBtn(
                actionLabels.restore || 'Restore',
                (actionLabels.confirm && actionLabels.confirm.restore_title) || 'Restore channel?',
                (actionLabels.confirm && actionLabels.confirm.restore_body) || 'This will restore the channel.',
                (actionLabels.confirm && actionLabels.confirm.restore_submit) || 'Restore',
                'btn-success'
            );
            restoreBtn.className = 'btn btn-outline-success btn-sm';
            restoreForm.appendChild(restoreBtn);
            actionsTd.appendChild(restoreForm);
            row.appendChild(actionsTd);
        }

        return row;
    }

    function buildLinkRow(link) {
        var row = document.createElement('tr');

        var labelTd = document.createElement('td');
        labelTd.textContent = link.label || '\u2014';
        row.appendChild(labelTd);

        var urlTd = document.createElement('td');
        urlTd.className = 'small';
        var a = document.createElement('a');
        a.href = link.url || '#';
        a.target = '_blank';
        a.rel = 'noopener';
        a.textContent = link.url || '';
        urlTd.appendChild(a);
        row.appendChild(urlTd);

        var kindTd = document.createElement('td');
        var kindBadge = document.createElement('span');
        kindBadge.className = 'badge text-bg-light border';
        kindBadge.textContent = link.kind || 'other';
        kindTd.appendChild(kindBadge);
        row.appendChild(kindTd);

        if (canMutate) {
            var actionsTd = document.createElement('td');
            actionsTd.className = 'text-end';
            var delForm = createForm(linkActionUrl(linkDestroyUrl, link.id), 'DELETE');
            delForm.className += ' d-inline';
            var delBtn = confirmBtn(
                '',
                (linkActionLabels.confirm && linkActionLabels.confirm.delete_title) || 'Delete link?',
                (linkActionLabels.confirm && linkActionLabels.confirm.delete_body) || 'This will permanently delete the link.',
                (linkActionLabels.confirm && linkActionLabels.confirm.delete_submit) || linkActionLabels.delete || 'Delete',
                'btn-danger'
            );
            delBtn.setAttribute('aria-label', linkActionLabels.delete || 'Delete');
            delBtn.innerHTML = '<i class="bi bi-trash" aria-hidden="true"></i>';
            delForm.appendChild(delBtn);
            actionsTd.appendChild(delForm);
            row.appendChild(actionsTd);
        }

        return row;
    }

    // ── Table helpers ────────────────────────────────────────────────

    function removeEmptyRow(tbodyId) {
        var meta = emptyRowMeta(tbodyId);
        var empty = document.getElementById(meta.id);
        if (empty) empty.remove();
    }

    function addEmptyRowIfNeeded(tbodyId) {
        var meta = emptyRowMeta(tbodyId);
        var tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        if (tbody.querySelectorAll('tr:not([data-empty-row])').length === 0) {
            var tr = document.createElement('tr');
            tr.id = meta.id;
            tr.setAttribute('data-empty-row', '');
            var td = document.createElement('td');
            td.setAttribute('colspan', String(meta.colspan));
            td.className = 'text-muted small';
            td.textContent = '\u2014';
            tr.appendChild(td);
            tbody.appendChild(tr);
        }
    }

    function prependToTbody(tbodyId, row) {
        var tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        tbody.insertBefore(row, tbody.firstChild);
        removeEmptyRow(tbodyId);
    }

    function appendToTbody(tbodyId, row) {
        var tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        var channelId = row.getAttribute && row.getAttribute('data-channel-id');
        if (channelId && tbody.querySelector('[data-channel-id="' + CSS.escape(channelId) + '"]')) {
            return;
        }
        tbody.appendChild(row);
        removeEmptyRow(tbodyId);
    }

    function removeRowFromTbody(tbodyId, channelId) {
        var tbody = document.getElementById(tbodyId);
        if (!tbody) return;
        var row = channelId ? tbody.querySelector('[data-channel-id="' + CSS.escape(channelId) + '"]') : null;
        if (row) {
            row.remove();
            addEmptyRowIfNeeded(tbodyId);
        }
    }

    // ── Mutation handlers ────────────────────────────────────────────────

    function handleChannelAdd(data) {
        var channel = extractResource(data);
        if (!channel) return;
        appendToTbody('profile-active-channels-tbody', buildActiveChannelRow(channel));
    }

    function handleChannelRefresh(data) {
        var channel = extractResource(data);
        if (!channel || !channel.id) return;
        var row = document.querySelector('#profile-active-channels-tbody [data-channel-id="' + CSS.escape(channel.id) + '"]');
        if (!row) return;
        var newRow = buildActiveChannelRow(channel);
        row.parentNode.replaceChild(newRow, row);
    }

    function handleChannelArchive(data) {
        var channel = extractResource(data);
        if (!channel || !channel.id) return;
        var row = document.querySelector('#profile-active-channels-tbody [data-channel-id="' + CSS.escape(channel.id) + '"]');
        if (row) row.remove();
        addEmptyRowIfNeeded('profile-active-channels-tbody');
        prependToTbody('profile-archived-channels-tbody', buildArchivedChannelRow(channel));
    }

    function handleChannelRestore(data) {
        var channel = extractResource(data);
        if (!channel || !channel.id) return;
        removeRowFromTbody('profile-archived-channels-tbody', channel.id);
        appendToTbody('profile-active-channels-tbody', buildActiveChannelRow(channel));
    }

    function handleChannelPromote(data) {
        var channel = extractResource(data);
        if (!channel || !channel.id) return;
        var row = document.querySelector('#profile-active-channels-tbody [data-channel-id="' + CSS.escape(channel.id) + '"]');
        if (!row) return;
        var newRow = buildActiveChannelRow(channel);
        row.parentNode.replaceChild(newRow, row);
    }

    function handleClaimVerify(data) {
        var claim = extractResource(data);
        removeClaimRow(claim);
        // If verification produced a channel, append it
        if (claim && claim.channel && claim.channel.id) {
            appendToTbody('profile-active-channels-tbody', buildActiveChannelRow(claim.channel));
        }
    }

    function handleClaimCancel(data) {
        var claim = extractResource(data);
        removeClaimRow(claim);
    }

    function handleClaimAcceptAnyway(data) {
        var claim = extractResource(data);
        removeClaimRow(claim);
        if (claim && claim.channel && claim.channel.id) {
            appendToTbody('profile-active-channels-tbody', buildActiveChannelRow(claim.channel));
        }
    }

    function removeClaimRow(claim) {
        if (!claim || !claim.id) return;
        var claimsTbody = document.getElementById('profile-channel-claims-tbody');
        if (!claimsTbody) return;
        // Claims don't have data attributes, so scan all rows for the matching ID in the form action
        var rows = claimsTbody.querySelectorAll('tr:not([data-empty-row])');
        for (var i = 0; i < rows.length; i++) {
            var form = rows[i].querySelector('form[action*="' + claim.id + '"]');
            if (form) {
                rows[i].remove();
                break;
            }
        }
        addEmptyRowIfNeeded('profile-channel-claims-tbody');
    }

    function handleLinkAdd(data) {
        var link = extractResource(data);
        if (!link) return;
        appendToTbody('profile-links-tbody', buildLinkRow(link));
    }

    function handleLinkDelete(data) {
        var link = extractResource(data);
        if (!link || !link.id) return;
        var linksTbody = document.getElementById('profile-links-tbody');
        if (!linksTbody) return;
        var rows = linksTbody.querySelectorAll('tr:not([data-empty-row])');
        for (var i = 0; i < rows.length; i++) {
            var form = rows[i].querySelector('form[action*="' + link.id + '"]');
            if (form) {
                rows[i].remove();
                break;
            }
        }
        addEmptyRowIfNeeded('profile-links-tbody');
    }

    function handleMetaUpdate(data) {
        var creator = extractResource(data);
        if (!creator) return;
        if (creator.display_name) {
            var metaWrap = document.querySelector('[data-profile-meta]');
            if (metaWrap) metaWrap.setAttribute('data-profile-display-name', creator.display_name);
            // Update breadcrumb card title (first .card-title)
            var titleEl = document.querySelector('.card-title');
            if (titleEl) titleEl.textContent = creator.display_name;
        }
        if (creator.slug) {
            var metaWrap = document.querySelector('[data-profile-meta]');
            if (metaWrap) metaWrap.setAttribute('data-profile-slug', creator.slug);
        }
    }

    // ── Router ────────────────────────────────────────────────────────

    function routeKeyFromAction(action) {
        if (!routeKey) return false;
        return action.indexOf('/vtual/profiles/' + encodeURIComponent(routeKey)) !== -1;
    }

    document.addEventListener('ajax-form:success', function (event) {
        var detail = event.detail;
        if (!detail) return;
        var form = detail.form;
        var data = detail.data;
        if (!form || !data) return;

        var action = form.action || '';

        // Channel add — use formKind marker (profiles)
        if (form.dataset && form.dataset.formKind === 'profiles') {
            handleChannelAdd(data);
            form.reset();
            var sel = form.querySelector('[name="platform_key"]');
            if (sel && sel.options.length > 0) sel.selectedIndex = 0;
            return;
        }

        // Meta update: PATCH vtual/profiles/{key}
        if (action.indexOf('/vtual/profiles/') !== -1 && form.method.toUpperCase() === 'POST') {
            var methodInput = form.querySelector('input[name="_method"]');
            var realMethod = methodInput ? methodInput.value.toUpperCase() : 'POST';
            if (realMethod === 'PATCH' && routeKeyFromAction(action)) {
                handleMetaUpdate(data);
                return;
            }
        }

        // Channels
        if (action.indexOf('/channels/') !== -1) {
            if (action.indexOf('/refresh') !== -1) { handleChannelRefresh(data); return; }
            if (action.indexOf('/archive') !== -1) { handleChannelArchive(data); return; }
            if (action.indexOf('/restore') !== -1) { handleChannelRestore(data); return; }
            if (action.indexOf('/promote') !== -1) { handleChannelPromote(data); return; }
        }

        // Channel claims
        if (action.indexOf('/channel-claims/') !== -1) {
            if (action.indexOf('/verify-discord') !== -1 || action.indexOf('/verify') !== -1) { handleClaimVerify(data); return; }
            if (action.indexOf('/cancel') !== -1) { handleClaimCancel(data); return; }
            if (action.indexOf('/accept-anyway') !== -1) { handleClaimAcceptAnyway(data); return; }
        }

        // Links
        if (action.indexOf('/links') !== -1) {
            var linkMethodInput = form.querySelector('input[name="_method"]');
            var linkMethod = linkMethodInput ? linkMethodInput.value.toUpperCase() : 'POST';
            if (linkMethod === 'DELETE') { handleLinkDelete(data); return; }
            if (linkMethod === 'POST' && action.indexOf('/links/') === -1) { handleLinkAdd(data); return; }
        }
    });
})();
