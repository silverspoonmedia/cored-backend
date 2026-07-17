<?php

return [
    'title' => 'Error log',
    'breadcrumb' => 'Error log',
    'list_title' => 'Application errors',
    'show_title' => 'Error detail',
    'show_breadcrumb' => 'Detail',
    'show_card_title' => 'Error group',
    'empty' => 'No errors recorded yet.',
    'view_detail' => 'View',
    'back_to_list' => 'Back to list',
    'status_updated' => 'Status updated.',
    'fields' => [
        'status' => 'Status',
        'level' => 'Level',
        'message' => 'Message',
        'exception_class' => 'Exception',
        'location' => 'Location',
        'occurrence_count' => 'Occurrences',
        'first_seen_at' => 'First seen',
        'last_seen_at' => 'Last seen',
        'fingerprint' => 'Fingerprint',
        'resolved_by' => 'Resolved by',
    ],
    'sections' => [
        'context' => 'Context',
        'trace' => 'Stack trace',
    ],
    'columns' => [
        'level' => 'Level',
        'message' => 'Message',
        'count' => 'Count',
        'status' => 'Status',
        'last_seen' => 'Last seen',
        'actions' => 'Actions',
    ],
    'filters' => [
        'all_statuses' => 'All statuses',
        'all_levels' => 'All levels',
        'all_types' => 'All types',
        'search' => 'Search message or class…',
        'apply' => 'Filter',
    ],
    'statuses' => [
        'open' => 'Open',
        'resolved' => 'Resolved',
        'ignored' => 'Ignored',
    ],
    'types' => [
        'exception' => 'Exception',
        'log' => 'Log',
    ],
    'actions' => [
        'resolve' => 'Mark resolved',
        'ignore' => 'Ignore',
        'reopen' => 'Reopen',
    ],
];
