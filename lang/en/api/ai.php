<?php

return [
    'connection' => [
        'list_success' => 'AI connections retrieved successfully.',
        'show_success' => 'AI connection retrieved successfully.',
        'created' => 'AI connection created. Run Test to activate it.',
        'updated' => 'AI connection updated. Run Test to activate it again.',
        'deleted' => 'AI connection deleted successfully.',
        'default_updated' => 'Default AI connection updated.',
        'slug_required' => 'Connection slug is required.',
        'slug_taken' => 'That connection slug is already in use.',
        'label_required' => 'Connection label is required.',
        'endpoint_invalid' => 'The endpoint must be a valid http(s) URL.',
        'test_model_required' => 'A test model is required to probe this connection.',
        'payload_invalid' => 'The stored secret payload is invalid. Re-enter the API key.',
        'payload_missing' => 'The secret payload is missing the required field: :field.',
        'payload_unknown_key' => 'Unexpected secret field: :field.',
        'default_requires_active' => 'Only an active (tested) connection can become the default.',
        'delete_requires_default_handover' => 'Promote another connection to default before deleting this one.',
        'test_not_configured' => 'Connection is missing an endpoint, API key, or test model.',
        'test_unreachable' => 'Could not reach the endpoint: :error',
        'test_unauthorized' => 'The endpoint rejected the API key (401/403).',
        'test_http_failed' => 'The endpoint answered with HTTP :status.',
        'test_http_failed_with_body' => 'The endpoint answered with HTTP :status: :detail',
        'test_passed' => 'Connection test passed.',
    ],
];
