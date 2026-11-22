<?php

namespace App\Domain\Archivd\Support\Settings;

use App\Support\Settings\Contracts\SettingContributor;
use App\Support\Settings\SettingType;

/**
 * Archivd's UI-manageable settings.
 *
 * Product knowledge lives in the product: `silverspoonmedia/platform` ships the
 * catalog and the contributor contract, and this class is registered from
 * {@see \App\Providers\Domain\ArchivdServiceProvider::register()}. Removing the
 * product removes the class and its registration, never a package edit.
 */
final class ArchivdSettingContributor implements SettingContributor
{
    /**
     * @return array<string, array{group: string, type: SettingType, config: string}>
     */
    public function definitions(): array
    {
        return [
            'archivd.max_file_size_bytes' => $this->def(SettingType::Int, 'archivd.max_file_size_bytes'),
            'archivd.max_files_per_batch' => $this->def(SettingType::Int, 'archivd.max_files_per_batch'),
            'archivd.max_concurrent_uploads' => $this->def(SettingType::Int, 'archivd.max_concurrent_uploads'),
            'archivd.max_concurrent_parts' => $this->def(SettingType::Int, 'archivd.max_concurrent_parts'),
            'archivd.chunk_size_bytes' => $this->def(SettingType::Int, 'archivd.chunk_size_bytes'),
            'archivd.multipart_threshold_bytes' => $this->def(SettingType::Int, 'archivd.multipart_threshold_bytes'),
            'archivd.max_presigned_parts_per_response' => $this->def(SettingType::Int, 'archivd.max_presigned_parts_per_response'),
            'archivd.presign_ttl_minutes' => $this->def(SettingType::Int, 'archivd.presign_ttl_minutes'),
            'archivd.session_ttl_hours' => $this->def(SettingType::Int, 'archivd.session_ttl_hours'),
            'archivd.stale_upload_grace_hours' => $this->def(SettingType::Int, 'archivd.stale_upload_grace_hours'),
            'archivd.orphan_inactivity_minutes' => $this->def(SettingType::Int, 'archivd.orphan_inactivity_minutes'),
            'archivd.default_quota_bytes' => $this->def(SettingType::Int, 'archivd.default_quota_bytes'),
            'archivd.base_owned_workspace_slots' => $this->def(SettingType::Int, 'archivd.base_owned_workspace_slots'),
            'archivd.disabled_regions' => $this->def(SettingType::Array, 'archivd.disabled_regions'),
            'archivd.signed_download_ttl_minutes' => $this->def(SettingType::Int, 'archivd.signed_download_ttl_minutes'),
            'archivd.signed_download_cache_margin_seconds' => $this->def(SettingType::Int, 'archivd.signed_download_cache_margin_seconds'),
            'archivd.public_download_signature_ttl_minutes' => $this->def(SettingType::Int, 'archivd.public_download_signature_ttl_minutes'),
            'archivd.allowed_mime_prefixes' => $this->def(SettingType::Array, 'archivd.allowed_mime_prefixes'),
            'archivd.download_vault.enabled' => $this->def(SettingType::Bool, 'archivd.download_vault.enabled'),
        ];
    }

    /**
     * @return array{group: string, type: SettingType, config: string}
     */
    private function def(SettingType $type, string $config): array
    {
        return [
            'group' => 'archivd',
            'type' => $type,
            'config' => $config,
        ];
    }
}
