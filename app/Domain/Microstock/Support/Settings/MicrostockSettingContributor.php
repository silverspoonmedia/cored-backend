<?php

namespace App\Domain\Microstock\Support\Settings;

use App\Providers\Domain\MicrostockServiceProvider;
use App\Support\Settings\Contracts\SettingContributor;
use App\Support\Settings\SettingType;
use Illuminate\Validation\Rule;

/**
 * Microstock's UI-manageable settings.
 *
 * Product knowledge lives in the product: `silverspoonmedia/platform` ships the
 * catalog and the contributor contract, and this class is registered from
 * {@see MicrostockServiceProvider::register()}. Removing the
 * product removes the class and its registration, never a package edit.
 *
 * `vision_image.delivery` declares its own enum rule here, so the kernel request
 * class names no product key.
 */
final class MicrostockSettingContributor implements SettingContributor
{
    /**
     * @return array<string, array{group: string, type: SettingType, config: string, rules?: list<mixed>}>
     */
    public function definitions(): array
    {
        return [
            'microstock.daily_free.enabled' => $this->def(SettingType::Bool, 'microstock.daily_free.enabled'),
            'microstock.daily_free.limit_per_user' => $this->def(SettingType::Int, 'microstock.daily_free.limit_per_user'),
            'microstock.daily_free.timezone' => $this->def(SettingType::String, 'microstock.daily_free.timezone'),
            'microstock.ai_billing.markup_multiplier' => $this->def(SettingType::Float, 'microstock.ai_billing.markup_multiplier'),
            'microstock.ai_billing.estimated_billable_micro_usd_per_asset' => $this->def(SettingType::Int, 'microstock.ai_billing.estimated_billable_micro_usd_per_asset'),
            'microstock.ai_billing.estimate_thinking_attempt_factor' => $this->def(SettingType::Float, 'microstock.ai_billing.estimate_thinking_attempt_factor'),
            'microstock.ai.image_max_px' => $this->def(SettingType::Int, 'microstock.ai.image_max_px'),
            'microstock.ai.image_jpeg_quality' => $this->def(SettingType::Int, 'microstock.ai.image_jpeg_quality'),
            'microstock.ai.max_request_image_bytes' => $this->def(SettingType::Int, 'microstock.ai.max_request_image_bytes'),
            'microstock.ai.http_retries' => $this->def(SettingType::Int, 'microstock.ai.http_retries'),
            'microstock.ai.thinking_visual_attempts' => $this->def(SettingType::Int, 'microstock.ai.thinking_visual_attempts'),
            'microstock.ai.batch_chunk' => $this->def(SettingType::Int, 'microstock.ai.batch_chunk'),
            'microstock.ai.batch_min_files' => $this->def(SettingType::Int, 'microstock.ai.batch_min_files'),
            'microstock.ai.phase.normal_after' => $this->def(SettingType::Int, 'microstock.ai.phase.normal_after'),
            'microstock.ai.phase.grace_after' => $this->def(SettingType::Int, 'microstock.ai.phase.grace_after'),
            'microstock.ai.stage.vision_grace_after' => $this->def(SettingType::Int, 'microstock.ai.stage.vision_grace_after'),
            'microstock.ai.stage.thinking_grace_after' => $this->def(SettingType::Int, 'microstock.ai.stage.thinking_grace_after'),
            'microstock.ai.stage.thinking_backlog_after' => $this->def(SettingType::Int, 'microstock.ai.stage.thinking_backlog_after'),
            'microstock.ai.vision_image.delivery' => $this->def(
                SettingType::String,
                'microstock.ai.vision_image.delivery',
                null,
                ['string', Rule::in(['base64', 'url'])],
            ),
            'microstock.ai.vision_image.variant_kind' => $this->def(SettingType::String, 'microstock.ai.vision_image.variant_kind'),
            'microstock.ai.vision_image.signed_url_ttl_minutes' => $this->def(SettingType::Int, 'microstock.ai.vision_image.signed_url_ttl_minutes'),
            'microstock.ai.inference_provider_slug' => $this->def(SettingType::String, 'microstock.ai.inference_provider_slug'),
            'microstock.ai.vision.connection' => $this->def(SettingType::String, 'microstock.ai.vision.connection'),
            'microstock.ai.thinking.connection' => $this->def(SettingType::String, 'microstock.ai.thinking.connection'),
            'microstock.ai.vision.model' => $this->def(SettingType::String, 'microstock.ai.vision.model'),
            'microstock.ai.vision.free_model' => $this->def(SettingType::String, 'microstock.ai.vision.free_model'),
            'microstock.ai.vision.timeout' => $this->def(SettingType::Int, 'microstock.ai.vision.timeout'),
            'microstock.ai.thinking.model' => $this->def(SettingType::String, 'microstock.ai.thinking.model'),
            'microstock.ai.thinking.free_model' => $this->def(SettingType::String, 'microstock.ai.thinking.free_model'),
            'microstock.ai.thinking.timeout' => $this->def(SettingType::Int, 'microstock.ai.thinking.timeout'),
            'microstock.ai.upstream.window_seconds' => $this->def(SettingType::Int, 'microstock.ai.upstream.window_seconds'),
            'microstock.ai.upstream.token_budget' => $this->def(SettingType::Int, 'microstock.ai.upstream.token_budget'),
            'microstock.ai.upstream.max_concurrent_vision' => $this->def(SettingType::Int, 'microstock.ai.upstream.max_concurrent_vision'),
            'microstock.ai.upstream.max_concurrent_thinking' => $this->def(SettingType::Int, 'microstock.ai.upstream.max_concurrent_thinking'),
            'microstock.ai.upstream.collection_vision_concurrency' => $this->def(SettingType::Int, 'microstock.ai.upstream.collection_vision_concurrency'),
            'microstock.ai.upstream.collection_thinking_concurrency' => $this->def(SettingType::Int, 'microstock.ai.upstream.collection_thinking_concurrency'),
            'microstock.ai.upstream.solo_vision_concurrency' => $this->def(SettingType::Int, 'microstock.ai.upstream.solo_vision_concurrency'),
            'microstock.ai.upstream.solo_thinking_concurrency' => $this->def(SettingType::Int, 'microstock.ai.upstream.solo_thinking_concurrency'),
            'microstock.ai.upstream.release_after_seconds' => $this->def(SettingType::Int, 'microstock.ai.upstream.release_after_seconds'),
            'microstock.csv_disk' => $this->def(SettingType::String, 'microstock.csv_disk'),
        ];
    }

    /**
     * @param  list<string>|null  $options
     * @param  list<mixed>|null  $rules
     * @return array{group: string, type: SettingType, config: string, options?: list<string>, rules?: list<mixed>}
     */
    private function def(SettingType $type, string $config, ?array $options = null, ?array $rules = null): array
    {
        $definition = [
            'group' => 'microstock',
            'type' => $type,
            'config' => $config,
        ];

        if ($options !== null && $options !== []) {
            $definition['options'] = $options;
        }

        if ($rules !== null && $rules !== []) {
            $definition['rules'] = $rules;
        }

        return $definition;
    }
}
