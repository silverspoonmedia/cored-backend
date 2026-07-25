<?php

namespace App\Domain\FileHosting\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FileHostingHashBlocklist extends Model
{
    use HasUuids;

    protected $table = 'file_hosting_hash_blocklist';

    protected $fillable = [
        'sha256',
        'reason',
        'created_by',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
