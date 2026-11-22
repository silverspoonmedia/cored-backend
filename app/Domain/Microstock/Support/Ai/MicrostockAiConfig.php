<?php

namespace App\Domain\Microstock\Support\Ai;

/**
 * Microstock's AI bindings, published into the `silverspoonmedia/ai` seams.
 *
 * The AI package owns the transport, the ledger contract and the admin dashboard;
 * Microstock owns two consumers (vision, thinking) and the `microstock_ai_usage_events`
 * table that prices them. Nothing here is imported by a package: the provider
 * merges these arrays into `ai.consumers` and `ai.ledger_fk_targets`.
 */
final class MicrostockAiConfig
{
    /**
     * Consumer rows for `ai.consumers` (see `packages/ai/config/ai.php`).
     *
     * @return list<array{slug: string, label: string, connection_setting: string, model_setting: string, endpoint_env: string, api_key_env: string}>
     */
    public static function consumers(): array
    {
        return [
            [
                'slug' => 'microstock-vision',
                'label' => 'Microstock vision',
                'connection_setting' => 'microstock.ai.vision.connection',
                'model_setting' => 'microstock.ai.vision.model',
                'endpoint_env' => 'MICROSTOCK_AI_VISION_ENDPOINT',
                'api_key_env' => 'MICROSTOCK_AI_VISION_API_KEY',
            ],
            [
                'slug' => 'microstock-thinking',
                'label' => 'Microstock thinking',
                'connection_setting' => 'microstock.ai.thinking.connection',
                'model_setting' => 'microstock.ai.thinking.model',
                'endpoint_env' => 'MICROSTOCK_AI_THINKING_ENDPOINT',
                'api_key_env' => 'MICROSTOCK_AI_THINKING_API_KEY',
            ],
        ];
    }

    /**
     * Ledger tables and the foreign keys that point back at the pricing catalog.
     *
     * The migration creates the catalog always and links each row only when its
     * table already exists, so a kernel-only install links nothing.
     *
     * @return list<array{table: string, columns: list<array{column: string, references: string, on: string, on_delete: string}>}>
     */
    public static function ledgerForeignKeys(): array
    {
        return [
            [
                'table' => 'microstock_ai_usage_events',
                'columns' => [
                    [
                        'column' => 'ai_model_rate_snapshot_id',
                        'references' => 'id',
                        'on' => 'ai_model_rate_snapshots',
                        'on_delete' => 'null',
                    ],
                    [
                        'column' => 'inference_provider_id',
                        'references' => 'id',
                        'on' => 'ai_inference_providers',
                        'on_delete' => 'null',
                    ],
                ],
            ],
        ];
    }
}
