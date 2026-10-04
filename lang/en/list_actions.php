<?php

return [
    'view_profile' => 'View profile',
    'view_detail' => 'View',
    'manage' => 'Manage',
    'test' => 'Test',
    'revoke' => 'Revoke',
    'make_default' => 'Make default',
    'activate' => 'Activate',
    'deactivate' => 'Deactivate',
    'set_settlement' => 'Set as settlement',
    'remove' => 'Remove',
    // curated at 2026-09-25T21:30:00+07:00 — vTual claims hub row actions
    'verify_claim' => 'Verify claim',
    'accept_anyway' => 'Accept anyway',
    // curated at 2026-09-27T19:20:00+07:00 — vTual incident triage queue row actions
    'acknowledge_incident' => 'Acknowledge',
    'resolve_incident' => 'Resolve',
    'open_credential' => 'Open credential',
    'open_profile' => 'Open profile',
    'confirm' => [
        'archive_management' => 'Archive this management?',
        'cancel_event' => 'Cancel this event?',
        // curated at 2026-09-25T21:30:00+07:00 — vTual claims hub row actions
        'cancel_claim_title' => 'Cancel claim',
        'cancel_claim' => 'Cancel this claim? The bio token stops working and you will have to start over.',
        'accept_anyway_claim_title' => 'Accept anyway',
        'accept_anyway_claim' => 'Accept this claim without finding the token in the channel bio?',
        'delete_service' => 'Delete this service?',
        'delete_inference' => 'Delete this record? This cannot be undone.',
        'delete_named_title' => 'Delete ":name"',
        'delete_menu' => 'Are you sure you want to delete ":name"?',
        'delete_microstock_credential' => 'Delete this credential?',
        'delete_credential_title' => 'Delete credential',
        'delete_platform_credential' => 'Delete this credential permanently?',
        'revoke_credential_title' => 'Revoke credential',
        'revoke_credential' => 'Revoke this credential? Workers will stop using it until you edit and Test again.',
        'delete_ai_connection_title' => 'Delete connection',
        'delete_ai_connection' => 'Delete this AI connection permanently? Consumers bound to its slug lose their endpoint until you bind another one.',
        'remove_blocklist_title' => 'Remove Entry',
        'remove_blocklist' => 'Remove this hash from the blocklist? Files with this checksum will be able to be shared publicly again.',
        // curated at 2026-09-27T19:20:00+07:00 — vTual incident triage queue row actions
        'resolve_incident_title' => 'Resolve incident',
        'resolve_incident' => 'Mark this incident resolved? It reopens automatically if the failure happens again.',
    ],
];
