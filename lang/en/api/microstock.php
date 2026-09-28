<?php

return [
    'workspace_loaded' => 'Microstock workspace loaded.',
    'asset_not_found' => 'Asset not found.',
    'metadata_not_found' => 'No metadata yet for this asset. Run analysis first.',
    'metadata_loaded' => 'Metadata loaded.',
    'metadata_saved' => 'Metadata saved.',
    'analysis_queued' => 'AI analysis queued.',
    'title_required' => 'Title is required.',
    'ai_not_configured' => 'AI :stage provider is not configured.',
    'ai_failed' => 'AI metadata generation failed.',
    'ai_invalid_response' => 'AI returned an invalid metadata response.',
    'credentials_loaded' => 'Credentials loaded.',
    'credential_saved' => 'Credential saved.',
    'credential_deleted' => 'Credential deleted.',
    'credential_not_found' => 'Credential not found.',
    'credential_invalid' => 'Marketplace and label are required.',
    'credential_secret_required' => 'Credential secret is required.',
    'workspace_not_activated' => 'Microstock is not activated yet. Register the free Microstock workspace from the Services page.',
    'slot_limit' => 'Microstock workspace slot limit reached (:used/:limit). Buy an additional slot to create more.',
    'workspace_created' => 'Microstock workspace created.',
    'slots_loaded' => 'Microstock slot status loaded.',
    'collection_not_found' => 'Collection not found.',
    'collection_already_locked' => 'This collection is already locked.',
    'collection_not_locked' => 'Lock the collection before running analysis.',
    'collection_empty' => 'This collection has no files to analyze.',
    'collection_not_ready' => 'The collection is not ready to export yet.',
    'collection_nothing_finalized' => 'No finalized metadata in this collection to export.',
    'collection_csv_missing' => 'The exported CSV for this collection was not found.',
    'collection_locked' => 'Collection locked.',
    'billing_mode_required' => 'Select a billing mode (Free or Paid) for this collection.',
    'collection_processing' => 'Collection analysis started.',
    'collection_status' => 'Collection status loaded.',
    'collection_exported' => 'Collection exported.',
    // curated at 2026-09-27T20:30:00+07:00 - en curation wave
    'upload_disabled' => 'Marketplace upload is turned off on this platform.',
    // curated at 2026-09-27T20:30:00+07:00 - en curation wave
    'upload_marketplace_unsupported' => 'Uploads to :marketplace are not supported yet.',
    // curated at 2026-09-27T20:30:00+07:00 - en curation wave
    'upload_not_ready' => 'Curate the collection first — only a reviewed or exported collection can be uploaded.',
    // curated at 2026-09-27T20:30:00+07:00 - en curation wave
    'upload_nothing_finalized' => 'No finalized metadata in this collection to upload.',
    // curated at 2026-09-27T20:30:00+07:00 - en curation wave
    'upload_filename_invalid' => 'The filename :name cannot be uploaded. Rename it to letters, digits, dots, dashes or underscores.',
    // curated at 2026-09-27T20:30:00+07:00 - en curation wave
    'upload_filename_duplicate' => 'Two files in this collection share the filename :name. Rename one of them.',
    // curated at 2026-09-27T20:30:00+07:00 - en curation wave
    'upload_credential_incomplete' => 'The marketplace credential is missing its host or username.',
    // curated at 2026-09-27T20:30:00+07:00 - en curation wave
    'upload_auth_failed' => 'The marketplace rejected these credentials.',
    // curated at 2026-09-27T20:30:00+07:00 - en curation wave
    'upload_host_key_mismatch' => 'The marketplace host key does not match the configured fingerprint.',
    // curated at 2026-09-27T20:30:00+07:00 - en curation wave
    'upload_remote_path_failed' => 'The remote folder :path could not be created.',
    // curated at 2026-09-27T20:30:00+07:00 - en curation wave
    'upload_failed_manifest' => 'The metadata file :name could not be uploaded.',
    // curated at 2026-09-27T20:30:00+07:00 - en curation wave
    'upload_failed_file' => 'The file :name could not be uploaded.',
    // curated at 2026-09-27T20:30:00+07:00 - en curation wave
    'upload_failed' => 'The marketplace upload failed.',
    // curated at 2026-09-27T20:30:00+07:00 - en curation wave
    'upload_queued' => 'Marketplace upload queued.',
    // curated at 2026-09-27T20:30:00+07:00 - en curation wave
    'upload_status_loaded' => 'Upload status loaded.',
    'insufficient_ai_credits' => 'Insufficient AI credits. Purchase an AI credit pack from Services before running analysis.',
    'billing_loaded' => 'AI billing status loaded.',
    'ledger_loaded' => 'Billing ledger loaded.',
    'usage_events_loaded' => 'AI usage events loaded.',
    'ledger' => [
        'purchase' => 'AI credit purchase — :service',
        'purchase_fallback' => 'AI credits',
        'analysis_debit' => 'AI analysis — :file',
        'analysis_debit_with_folder' => 'AI analysis — :file (:folder)',
        'analysis_debit_fallback' => 'AI analysis',
        'adjustment' => 'Balance adjustment',
    ],
    'admin_billing_overview_loaded' => 'Platform billing overview loaded.',
    'admin_usage_events_loaded' => 'Platform usage events loaded.',
    'admin_billing_forbidden' => 'You are not allowed to view platform billing.',
];
