<?php

namespace App\Http\Requests\Navigation\Concerns;

use App\Models\Menu;
use Illuminate\Validation\Validator;

/**
 * Shared menu hierarchy rules:
 *  - structural integrity: header(h) is a root, parent(p) sits under a header,
 *    child(c) sits under a parent (strict h -> p -> c nesting);
 *  - cycle safety: a menu may not be re-parented under one of its descendants.
 */
trait ValidatesMenuStructure
{
    /**
     * Each type must sit directly under a parent exactly one rank higher.
     */
    private const EXPECTED_PARENT_TYPE = ['h' => null, 'p' => 'h', 'c' => 'p'];

    protected function assertMenuStructure(Validator $validator, string $type, ?string $parentId): void
    {
        if (! array_key_exists($type, self::EXPECTED_PARENT_TYPE)) {
            return;
        }

        $expected = self::EXPECTED_PARENT_TYPE[$type];

        if ($expected === null) {
            if ($parentId !== null) {
                $validator->errors()->add('parent', 'A header (type "h") is a root item and cannot have a parent.');
            }

            return;
        }

        if ($parentId === null) {
            $validator->errors()->add('parent', "A menu of type \"{$type}\" requires a parent of type \"{$expected}\".");

            return;
        }

        $parentType = Menu::query()->whereKey($parentId)->value('type');

        if ($parentType !== $expected) {
            $validator->errors()->add('parent', "A menu of type \"{$type}\" must be placed under a parent of type \"{$expected}\".");
        }
    }

    protected function assertParentLayoutMatches(Validator $validator, string $layout, ?string $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        $parentLayout = Menu::query()->whereKey($parentId)->value('layout');

        if ($parentLayout !== null && $parentLayout !== $layout) {
            $validator->errors()->add('parent', 'The parent menu must belong to the same layout as this menu.');
        }
    }

    /**
     * Reject visibility-flag combinations that would make a menu visible to no
     * one: guest-only + authenticate (mutually exclusive audiences), and
     * guest-only + roles (roles require an authenticated user).
     */
    protected function assertFlagsCompatible(Validator $validator, bool $guestOnly, bool $authenticate, bool $hasRoles): void
    {
        if ($guestOnly && $authenticate) {
            $validator->errors()->add('guest_only', 'A menu cannot be both guest-only and authenticated; it would be visible to no one.');
        }

        if ($guestOnly && $hasRoles) {
            $validator->errors()->add('guest_only', 'A guest-only menu cannot have role restrictions; roles require an authenticated user.');
        }
    }

    protected function assertParentIsNotDescendant(Validator $validator, string $menuId, ?string $parentId): void
    {
        if ($parentId === null || $parentId === $menuId) {
            return;
        }

        if (in_array($parentId, $this->descendantIds($menuId), true)) {
            $validator->errors()->add('parent', 'A menu cannot be moved under one of its own descendants.');
        }
    }

    /**
     * @return list<string>
     */
    private function descendantIds(string $menuId): array
    {
        $childrenByParent = [];
        foreach (Menu::query()->get(['id', 'parent_id']) as $menu) {
            $childrenByParent[$menu->parent_id][] = $menu->id;
        }

        $descendants = [];
        $stack = [$menuId];

        while ($stack !== []) {
            $current = array_pop($stack);
            foreach ($childrenByParent[$current] ?? [] as $childId) {
                $descendants[] = $childId;
                $stack[] = $childId;
            }
        }

        return $descendants;
    }
}
