<?php

namespace Database\Factories;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    protected $model = Menu::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'name' => fake()->unique()->words(2, true),
            'icon' => null,
            'route' => null,
            'type' => 'c',
            // Parked at NULL by default so multiple factory rows in the same
            // (parent_id, type) group never collide with the unique index.
            // Use the repository when contiguous ordering matters.
            'order' => null,
            'is_authenticate' => null,
            'is_guest_only' => null,
        ];
    }

    public function header(): static
    {
        return $this->state(fn () => ['type' => 'h', 'parent_id' => null]);
    }

    public function parentItem(Menu|string|null $parent = null): static
    {
        return $this->state(fn () => [
            'type' => 'p',
            'parent_id' => $parent instanceof Menu ? $parent->id : $parent,
        ]);
    }

    public function child(Menu|string|null $parent = null): static
    {
        return $this->state(fn () => [
            'type' => 'c',
            'parent_id' => $parent instanceof Menu ? $parent->id : $parent,
        ]);
    }

    public function authenticated(): static
    {
        return $this->state(fn () => ['is_authenticate' => true]);
    }

    public function guestOnly(): static
    {
        return $this->state(fn () => ['is_guest_only' => true]);
    }
}
