<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Identity\Models\User;
use App\Domain\Vtual\Enums\ManagementMemberRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ManagementMember extends Model
{
    use HasUuids;
    use LogsActivity;

    protected $table = 'vtual_management_members';

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('vtual');
    }

    protected $fillable = [
        'management_id',
        'user_id',
        'role',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ManagementMemberRole::class,
        ];
    }

    /**
     * @return BelongsTo<Management, $this>
     */
    public function management(): BelongsTo
    {
        return $this->belongsTo(Management::class, 'management_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Delegated per-creator capability grants held by this member.
     *
     * @return HasMany<ManagementCreatorGrant, $this>
     */
    public function creatorGrants(): HasMany
    {
        return $this->hasMany(ManagementCreatorGrant::class, 'member_id');
    }
}
