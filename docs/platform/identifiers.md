# Identifiers (UUID v7)

Domain models use **UUID v7** primary keys (not auto-increment integers). Foreign keys are UUID columns end to end.

## Why UUID v7

| Approach | Trade-off |
|----------|-----------|
| Big integer | Fast but enumerable; awkward as public ID |
| UUID v4 | Unique but random B-tree inserts |
| **UUID v7** | Opaque + time-ordered index locality |

Laravel 13 `HasUuids` generates **UUID v7** (RFC 9562) in application code on create. **Do not** use DB `gen_random_uuid()` for v7 (that is v4).

## Schema

```php
$table->uuid('id')->primary();
$table->foreignUuid('user_id')->nullable()->index(); // or ->constrained()
```

## Model

```php
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;
}
```

Sets `$incrementing = false`, `$keyType = 'string'`, auto-fills `id` on create. Factories need not pass `id`.

## Adding UUID to a new model

1. Migration: `uuid` PK + `foreignUuid` for references
2. Model: `HasUuids`
3. Validation: `uuid` rule on route/form IDs
4. API: serialize `id` as string

Legacy ordered v4 only if required: `HasVersion4Uuids` — not for new tables.

## Already on UUID

`users`, Spatie roles/permissions pivots, File Hosting / Service / Payment tables that use UUIDs, sessions `user_id`, JWT `sub`.

## Local reset

If schema was pre-UUID, reset local DB intentionally:

```bash
# Requires ALLOW_DESTRUCTIVE_DB=true — see database-safety.md
php artisan migrate:fresh --seed
```

## Related

- [domains/identity/README.md](../domains/identity/README.md)
- [database-safety.md](database-safety.md)
- [Laravel Eloquent UUIDs](https://laravel.com/docs/eloquent#uuid-and-ulid-keys)
