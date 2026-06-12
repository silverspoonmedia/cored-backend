<?php

return [
    'list_success' => 'Notifications retrieved successfully.',
    'unread_count_success' => 'Unread notification count retrieved successfully.',
    'marked_read' => 'Notification marked as read.',
    'marked_all_read' => 'All notifications marked as read.',
    'not_visible' => 'You are not authorized to access this notification.',
    // Message templates per notification type. Replacements come from the
    // notification `data` payload (:name, :email, :actor, :role, …).
    'types' => [
        'user_created' => 'A new user :name (:email) was added by :actor.',
        'account_updated' => 'Your account was updated by :actor.',
    ],
];
