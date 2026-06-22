<?php

return [
    'title' => 'Activity log',
    'breadcrumb' => 'Activity log',
    'list_title' => 'Audit trail',
    'show_title' => 'Activity detail',
    'show_breadcrumb' => 'Detail',
    'show_card_title' => 'Entry #:id',
    'user_history_title' => 'Recent activity',
    'empty' => 'No activity recorded yet.',
    'view_detail' => 'View',
    'back_to_list' => 'Back to list',
    'fields' => [
        'log_name' => 'Log',
        'event' => 'Event',
        'subject' => 'Subject',
        'causer' => 'Actor',
        'created_at' => 'Recorded at',
    ],
    'sections' => [
        'changes' => 'Attribute changes',
        'properties' => 'Additional properties',
    ],
    'columns' => [
        'event' => 'Event',
        'subject' => 'Subject',
        'causer' => 'Actor',
        'time' => 'Time',
        'actions' => 'Actions',
        'attribute' => 'Attribute',
        'old_value' => 'Before',
        'new_value' => 'After',
    ],
];
