<?php

namespace App\Domain\Service\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $table = 'currencies';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'is_active',
        'is_settlement',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'is_active' => 'boolean',
            'is_settlement' => 'boolean',
        ];
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }
}
