<?php

namespace App\Models;

use Database\Factories\MenuFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'menus';

    protected $fillable = [
        'parent_id',
        'name',
        'icon',
        'route',
        'type',
        'order',
        'is_authenticate',
        'is_guest_only',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_authenticate' => 'boolean',
            'is_guest_only' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'menu_roles');
    }

    public function includedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'menu_user_includes');
    }

    public function excludedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'menu_user_excludes');
    }

    public function scopeHeaders(Builder $query): Builder
    {
        return $query->where('type', 'h')->whereNull('parent_id');
    }

    public function scopeParents(Builder $query): Builder
    {
        return $query->where('type', 'p');
    }

    public function scopeChildren(Builder $query): Builder
    {
        return $query->where('type', 'c');
    }

    public function scopeByParent(Builder $query, ?string $parentId): Builder
    {
        return $parentId === null
            ? $query->whereNull('parent_id')
            : $query->where('parent_id', $parentId);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Shape used when building the runtime navigation tree.
     *
     * @return array<string, mixed>
     */
    public function toNavigationArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'icon' => $this->icon,
            'route' => $this->route,
            'type' => $this->type,
            'order' => $this->order,
            'children' => [],
        ];
    }
}
