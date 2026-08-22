<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->convertPayloadColumns('text');

        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->string('token_blind_index', 64)->nullable()->after('token');
            $table->index('token_blind_index');
        });
    }

    public function down(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropIndex(['token_blind_index']);
            $table->dropColumn('token_blind_index');
        });

        $this->convertPayloadColumns('json');
    }

    private function assertPayloadsAreJson(): void
    {
        foreach (DB::table('invoices')->get([
            'gateway_request_payload',
            'gateway_response_payload',
        ]) as $invoice) {
            foreach (['gateway_request_payload', 'gateway_response_payload'] as $column) {
                if ($invoice->{$column} === null) {
                    continue;
                }

                json_decode((string) $invoice->{$column});

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException(
                        "Cannot convert invoices.{$column} to json: at least one value is not valid JSON."
                    );
                }
            }
        }
    }

    private function convertPayloadColumns(string $targetType): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($targetType === 'text') {
            if ($driver === 'pgsql') {
                DB::statement(
                    'alter table "invoices" alter column "gateway_request_payload" type text using "gateway_request_payload"::text, '
                    .'alter column "gateway_response_payload" type text using "gateway_response_payload"::text'
                );
            } else {
                Schema::table('invoices', function (Blueprint $table): void {
                    $table->text('gateway_request_payload')->nullable()->change();
                    $table->text('gateway_response_payload')->nullable()->change();
                });
            }

            return;
        }

        if ($targetType === 'json' && $driver === 'pgsql') {
            $this->assertPayloadsAreJson();
            DB::statement(
                'alter table "invoices" alter column "gateway_request_payload" type json using "gateway_request_payload"::json, '
                .'alter column "gateway_response_payload" type json using "gateway_response_payload"::json'
            );

            return;
        }

        Schema::table('invoices', function (Blueprint $table) use ($targetType): void {
            $table->{$targetType}('gateway_request_payload')->nullable()->change();
            $table->{$targetType}('gateway_response_payload')->nullable()->change();
        });
    }
};
