<?php

namespace App\Domain\ErrorLog\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Grouped persistent application error (exception or Log::error+).
 *
 * Distinct from Spatie activity_log (audit trail) and activity_notifications (bell).
 */
class ErrorLog extends Model
{
    use HasUuids;

    public const TYPE_EXCEPTION = 'exception';

    public const TYPE_LOG = 'log';

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_IGNORED = 'ignored';

    protected $table = 'error_logs';

    protected $fillable = [
        'fingerprint',
        'type',
        'level',
        'exception_class',
        'message',
        'file',
        'line',
        'trace',
        'context',
        'occurrence_count',
        'first_seen_at',
        'last_seen_at',
        'status',
        'resolved_at',
        'resolved_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'line' => 'integer',
            'occurrence_count' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
