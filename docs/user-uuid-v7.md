# User primary keys (UUID v7)

The `users` table uses a **UUID v7** primary key instead of an auto-incrementing integer. Session rows that reference a user store the same UUID type in `sessions.user_id`.

This document explains what was changed, why UUID v7 was chosen, and how to apply the same pattern to new models.

---

## Summary of changes

| Location | Change |
|----------|--------|
| `database/migrations/0001_01_01_000000_create_users_table.php` | `users.id` is `uuid` primary key; `sessions.user_id` is `foreignUuid` |
| `app/Domain/Identity/Models/User.php` | `HasUuids` trait generates UUID v7 on create |

---

## Why UUID v7 (not integer or random UUID v4)

| Approach | Trade-off |
|----------|-----------|
| **Big integer `id()`** | Small and fast, but predictable (enumeration), awkward across distributed systems, and poor as a public identifier. |
| **UUID v4 (random)** | Globally unique and opaque, but random insertion order hurts B-tree indexes (fragmentation, slower writes at scale). |
| **UUID v7 (time-ordered)** | Still opaque and unique, but the leading timestamp keeps new rows roughly sequential in the index—closer to integer performance without sacrificing distributed-friendly IDs. |

Laravel 13’s `HasUuids` trait generates **UUID v7** by default (RFC 9562). IDs are assigned in application code when a model is created, not by a database `AUTO_INCREMENT` or `gen_random_uuid()` default.

**Do not** rely on PostgreSQL’s `gen_random_uuid()` for v7—it produces v4. Always let Eloquent (`HasUuids`) or explicit application code set the ID.

---

## Schema

### `users`

```php
$table->uuid('id')->primary();
```

### `sessions` (same migration file)

```php
$table->foreignUuid('user_id')->nullable()->index();
```

Foreign keys and indexes on `user_id` must use a UUID column type whenever they point at `users.id`.

---

## Model configuration

`App\Domain\Identity\Models\User` uses the framework trait:

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;
}
```

Effects (handled by the trait):

- `$incrementing = false`
- `$keyType = 'string'`
- `id` is set automatically in `creating` via UUID v7

Factories and `User::create([...])` do **not** need to pass `id` unless you intentionally supply one.

Example ID shape: `019e8766-857b-7259-a580-a21e1ddfa27e` (version digit `7` in the third segment).

---

## Local development and migrations

If the database was created **before** this change, existing `users.id` columns are still `bigint`. Altering the column in place on a populated table is a separate migration task; for local/dev databases it is usually simpler to reset:

```bash
php artisan migrate:fresh
```

That drops all tables and re-runs migrations. **All data is lost**—use only when that is acceptable.

To confirm UUID generation after migrating:

```bash
php artisan tinker --execute="echo App\Domain\Identity\Models\User::factory()->create()->id;"
```

---

## Adding UUID v7 to another model

1. **Migration** — primary key as UUID, and any referencing columns as `uuid` / `foreignUuid`:

   ```php
   Schema::create('orders', function (Blueprint $table) {
       $table->uuid('id')->primary();
       $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
       // ...
   });
   ```

2. **Model** — add `HasUuids`:

   ```php
   use Illuminate\Database\Eloquent\Concerns\HasUuids;

   class Order extends Model
   {
       use HasUuids;
   }
   ```

3. **Validation** — route parameters and form rules should expect a UUID string, e.g. `uuid` rule on `id` fields.

4. **API responses** — serialize `id` as a string (JSON default for Eloquent string keys).

If you must keep **ordered UUID v4** (legacy `Str::orderedUuid()` behavior), use `HasVersion4Uuids` instead of `HasUuids`—not recommended for new tables in this project.

---

## Related tables and features

When introducing new tables that belong to a user:

- Use `foreignUuid('user_id')` (or `uuid('user_id')` with an explicit foreign key) referencing `users.id`.
- JWT subjects, policies, and route model binding accept string UUIDs the same way as integer IDs once the model uses `HasUuids`.

`password_reset_tokens` continues to key off `email`, not `user_id`; no change was required there.

---

## Related documentation

- [JWT Authentication & RBAC](./jwt-authentication.md) — JWT `sub` claim and permission pivot tables use user UUIDs
- [User Management & Hierarchical RBAC](./user-management-hrbac.md) — user CRUD routes use UUID `{user}` binding
- [API Routing](./api-routing.md) — versioned API surface
- [CRUD Architecture & Repository Pattern](./crud-architecture.md) — layer responsibilities

---

## References

- [Laravel Eloquent: UUIDs](https://laravel.com/docs/eloquent#uuid-and-ulid-keys)
- [RFC 9562 — UUIDs (including version 7)](https://www.rfc-editor.org/rfc/rfc9562.html)
- Laravel 12+ upgrade note: `HasUuids` now defaults to UUID v7; use `HasVersion4Uuids` only when preserving v4/ordered-uuid behavior.
