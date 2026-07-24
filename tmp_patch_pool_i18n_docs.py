# -*- coding: utf-8 -*-
from pathlib import Path

# ja api
ja = Path("lang/ja/api/vtual.php")
jt = ja.read_text(encoding="utf-8")
if "refresh_queued" not in jt:
    # insert after refreshed line in channel block
    import re
    m = re.search(r"('refreshed'\s*=>\s*'[^']*',\n)", jt)
    if not m:
        # try any refreshed
        parts = list(re.finditer(r"        'refreshed' => .*\n", jt))
        if not parts:
            raise SystemExit("ja refreshed not found")
        span = parts[-1]
        insert = span.group(0) + "        'refresh_queued' => 'チャンネル更新をキューに追加しました（他の保留中リクエストとまとめて処理）。',\n"
        jt = jt[: span.start()] + insert + jt[span.end() :]
    else:
        jt = jt.replace(
            m.group(1),
            m.group(1) + "        'refresh_queued' => 'チャンネル更新をキューに追加しました（他の保留中リクエストとまとめて処理）。',\n",
            1,
        )
    ja.write_text(jt, encoding="utf-8")
    print("ja api OK")
else:
    print("ja already has refresh_queued")

# docs
doc = Path("docs/domains/vtual/README.md")
dt = doc.read_text(encoding="utf-8")

old_ch = "Refresh re-syncs; soft remove = **`archived`**; **restore** returns archived → **active** (no auto re-fetch)."
new_ch = (
    "Refresh **enqueues** channel fetch pool (batch 50 / max wait 15m); soft remove = **`archived`**; "
    "**restore** returns archived → **active** (no auto re-fetch)."
)
if old_ch in dt:
    dt = dt.replace(old_ch, new_ch)
    print("concept channel line OK")
elif "channel fetch pool" in dt:
    print("concept already mentions pool")
else:
    # fallback partial
    dt = dt.replace(
        "Refresh re-syncs;",
        "Refresh **enqueues** channel fetch pool (batch 50 / max wait 15m);",
        1,
    )
    print("concept partial replace")

dt = dt.replace(
    "| POST | `/channels/{id}/refresh` | edit creator | Re-resolve by `external_id`; set `active` |",
    "| POST | `/channels/{id}/refresh` | edit creator | Enqueue pool refresh by `external_id` (async batch) |",
)

old_refresh = (
    "**Refresh / verify** (`POST /api/v1/vtual/channels/{id}/refresh`): re-fetch by stored `external_id` only; "
    "updates `handle`, `display_name`, `url`, `last_synced_at`, sets status **`active`**. "
    "Does not change `external_id`. Rejects archived channels."
)
new_refresh = (
    "**Refresh** (`POST /api/v1/vtual/channels/{id}/refresh`): enqueues `vtual_channel_fetch_pool` by stored "
    "`external_id` (no immediate platform HTTP). Snapshot updates on pool flush. Rejects archived channels.\n\n"
    "**Channel fetch pool:** non-interactive only (refresh + scheduled resync). Flush when pending unique ids "
    "per platform ≥ `config(vtual.channel_pool.threshold)` (50) **or** oldest pending age ≥ "
    "`max_wait_minutes` (15). Single `Cache::lock` + claim-to-`processing` prevents double HTTP for the same set. "
    "Interactive claim/seed/verify/handle resolve stay sync. Command: `php artisan vtual:flush-channel-pool` "
    "(`--force`, `--platform=`, `--resync`). Schedule: every minute flush + hourly `--resync`."
)
if old_refresh in dt:
    dt = dt.replace(old_refresh, new_refresh)
    print("refresh section OK")
elif "Channel fetch pool" in dt:
    print("pool section exists")
else:
    # insert after Archive line area
    marker = "**Archive** (`POST /api/v1/vtual/channels/{id}/archive`)"
    if marker in dt:
        # replace any short refresh paragraph before archive
        import re
        dt2, n = re.subn(
            r"\*\*Refresh[^*].*?(?=\n\n\*\*Archive)",
            new_refresh + "\n\n",
            dt,
            count=1,
            flags=re.DOTALL,
        )
        if n:
            dt = dt2
            print("refresh via regex OK")
        else:
            dt = dt.replace(marker, new_refresh + "\n\n" + marker, 1)
            print("refresh inserted before archive")
    else:
        print("WARN: could not place refresh docs")

if "`ChannelFetchPoolServiceTest`" not in dt:
    dt = dt.replace(
        "| `ChannelServiceTest` |",
        "| `ChannelFetchPoolServiceTest` | Pool threshold/age/force flush; coalesce; single HTTP; apply snapshot |\n"
        "| `ChannelServiceTest` |",
    )
    print("tests table OK")

# update ChannelServiceTest description line
dt = dt.replace(
    "| `ChannelServiceTest` | `seedForStaff` (manage any), non-staff denied, `activateFromDto`, refresh/archive |",
    "| `ChannelServiceTest` | `seedForStaff` (manage any), non-staff denied, `activateFromDto`, refresh enqueues pool, archive/restore |",
)

doc.write_text(dt, encoding="utf-8")
print("docs done")
