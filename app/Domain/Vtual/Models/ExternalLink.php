<?php

namespace App\Domain\Vtual\Models;

use App\Domain\Vtual\Enums\ExternalLinkKind;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ExternalLink extends Model
{
    use HasUuids;

    protected $table = 'vtual_external_links';

    protected $fillable = [
        'linkable_type',
        'linkable_id',
        'label',
        'url',
        'kind',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ExternalLinkKind::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
