<?php

namespace App\Domain\Approval\Models;

use App\Domain\Identity\Models\User;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'instance_id',
        'instance_step_id',
        'action',
        'actor_id',
        'target_user_id',
        'metadata',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class, 'instance_id');
    }

    public function instanceStep(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstanceStep::class, 'instance_step_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
