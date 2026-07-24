<?php

declare(strict_types=1);

return [
    // Gate / middleware
    'auth_required' => 'Authentication required.',
    'unknown_product' => 'Unknown product domain: :product.',
    'forbidden' => 'This product domain is private. Access is limited to Root, Admin, and its beta testers.',

    // Admin API
    'list_success' => 'Product domain access settings retrieved.',
    'updated' => 'Product domain visibility updated.',
    'invalid_visibility' => 'Visibility must be either public or private.',

    // UI
    'title' => 'Product Domain Access',
    'subtitle' => 'Control which product domains are public or restricted to beta testers.',
    'empty' => 'No product domains registered.',
    'col_domain' => 'Product Domain',
    'col_visibility' => 'Visibility',
    'visibility_public' => 'Public',
    'visibility_private' => 'Private',
    'public_hint' => 'Anyone can access this product domain.',
    'private_hint' => 'Only Root, Admin, and beta testers can access.',
    'beta_role' => 'Beta role: :role',
    'toggle_updated' => 'Visibility updated.',
    'toggle_failed' => 'Could not update visibility.',
];
