<?php

namespace App\Domain\Approval\Models;

use App\Domain\Identity\Models\User;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalDelegationLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'delegation_id',
        'action',
        'performed_by',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function delegation(): BelongsTo
    {
        return $this->belongsTo(ApprovalDelegation::class, 'delegation_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
