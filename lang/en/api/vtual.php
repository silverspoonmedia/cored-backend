<?php

return [
    'creator' => [
        'list_success' => 'Creators loaded.',
        'show_success' => 'Creator loaded.',
        'created' => 'Creator created.',
        'updated' => 'Creator updated.',
        'archived' => 'Creator archived.',
        'name_required' => 'Display name is required.',
        'slug_taken' => 'This creator slug is already taken.',
    ],
    'management' => [
        'list_success' => 'Managements loaded.',
        'show_success' => 'Management loaded.',
        'created' => 'Management created.',
        'updated' => 'Management updated.',
        'name_required' => 'Management name is required.',
        'slug_taken' => 'This management slug is already taken.',
        'creator_assigned' => 'Creator assigned to management.',
        'creator_unassigned' => 'Creator unassigned from management.',
        'assignment_not_found' => 'Active management assignment not found.',
    ],
    'channel' => [
        'list_success' => 'Channels loaded.',
        'show_success' => 'Channel loaded.',
        'created' => 'Channel registered.',
        'platform_unknown' => 'Unknown platform.',
        'platform_disabled' => 'This platform is not enabled yet.',
        'external_id_required' => 'External channel id is required.',
        'external_id_taken' => 'A channel with this platform id already exists.',
    ],
    'platform' => [
        'list_success' => 'Platforms loaded.',
    ],
    'policy' => [
        'denied' => 'You are not allowed to perform this action.',
        'access_denied' => 'You cannot access this vTual resource.',
        'mutate_denied' => 'You cannot modify this vTual resource.',
        'members_denied' => 'You cannot manage members for this resource.',
        'assign_denied' => 'You cannot assign creators for this management.',
    ],
];
