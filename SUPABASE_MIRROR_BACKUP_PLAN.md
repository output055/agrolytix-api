# Supabase Mirror Backup Implementation Plan

## Goal

Create a manual, admin-only Phase 1 backup feature that mirrors the live VPS MySQL data into a readable Supabase Postgres database for recovery and reporting.

The current VPS MySQL database remains the source of truth. Supabase is only a mirror target.

## Safety Principles

- Do not modify existing production business tables.
- Read from MySQL using `SELECT` queries only.
- Write mirrored records only to Supabase.
- Add only one local tracking table, `backup_runs`, to the Laravel MySQL database.
- Do not delete records from Supabase during Phase 1.
- Use chunked reads to avoid heavy memory use and reduce production load.
- Keep the backup button admin-only.
- Start with manual backups before adding a schedule.

## Phase 1 Scope

Phase 1 includes:

- Supabase SQL schema for mirror tables.
- Laravel `backup_runs` table/model for status tracking.
- Laravel Supabase mirror service.
- Admin-only API endpoints.
- Admin-only frontend backup panel/button.
- Manual full-table upsert backup.

Phase 1 does not include:

- Automatic scheduling.
- Deleting stale rows from Supabase.
- Restore automation.
- Incremental-only sync.
- Realtime replication.

## Data Strategy

The sync will copy data from MySQL to Supabase using upserts.

Each mirrored table should preserve the original MySQL `id` value. This makes repeated backup runs idempotent: the same record updates the existing Supabase row instead of creating duplicates.

Recommended batch size:

- Start with `500` records per batch.
- Reduce to `100` if the VPS database is under load.
- Increase later only after manual backups are stable.

**Async threshold:**

- If total records across all tables exceed **10,000**, or any single table exceeds **5,000 rows**, move the sync to a queued Laravel job before going to production.
- Check record counts with a pre-flight query before the run and log a warning if thresholds are crossed.

Recommended conflict key:

- `id` for most tables.
- Composite keys only if a table does not have a reliable single primary key.
- `stock_transfers` — verify its primary key before assuming `id` is sufficient; it may need a composite conflict key if it uses a compound PK.

## Tables To Mirror

Mirror these tables first:

- `businesses`
- `users`
- `clients`
- `products`
- `product_units`
- `wholesale_products`
- `wholesale_product_units`
- `retail_sales`
- `retail_sale_items`
- `wholesale_sales`
- `wholesale_sale_items`
- `expenses`
- `reversals`
- `debts`
- `stock_transfers`

## Supabase Schema Plan

Create a SQL file in the repo, for example:

```text
database/supabase/mirror_schema.sql
```

The SQL should create equivalent Postgres tables.

Type mapping:

- MySQL `bigint unsigned` -> Postgres `bigint`
- MySQL `decimal(12,2)` -> Postgres `numeric(12,2)`
- MySQL `varchar` / `text` -> Postgres `text`
- MySQL `json` -> Postgres `jsonb`
- MySQL `timestamp` / `datetime` -> Postgres `timestamptz`
- MySQL enums -> Postgres `text` for Phase 1

Supabase tables should include:

- Original `id`
- Original timestamps where present
- `mirrored_at timestamptz not null default now()`

For Phase 1, avoid foreign key constraints in Supabase unless the sync order is fully reliable. Reporting can still use joins by ID.

**Row-Level Security (RLS):**

- Enable RLS on all mirror tables in Supabase.
- Do **not** create any public SELECT policies on mirror tables.
- The `service_role` key used by Laravel bypasses RLS, so writes will always work.
- This ensures mirror data is never accidentally exposed to anonymous or public Supabase queries.
- SQL to apply after creating each table:
  ```sql
  ALTER TABLE <table_name> ENABLE ROW LEVEL SECURITY;
  -- No public policies — only service_role can access.
  ```

## Local Backup Tracking

Add a Laravel migration for `backup_runs`.

Suggested fields:

```text
id
target                string, e.g. supabase
status                string: running, completed, failed
initiated_by_user_id  unsigned bigint nullable (FK to users.id — for audit trail)
started_at            timestamp nullable
finished_at           timestamp nullable
records_synced        unsigned integer default 0
tables_synced         json nullable  — rich per-table result object (see below)
error_message         text nullable
created_at
updated_at
```

**`tables_synced` JSON schema:**

Store a rich per-table result so you know exactly what happened per table:

```json
{
  "businesses":    { "synced": 120, "status": "ok" },
  "retail_sales":  { "synced": 0,   "status": "failed", "error": "Supabase 500: ..." },
  "expenses":      { "synced": 342, "status": "ok" }
}
```

This table is the only MySQL write required by Phase 1.

## Laravel Backend Design

### Environment Variables

Add to `.env.example`:

```env
SUPABASE_URL=https://your-project-ref.supabase.co
SUPABASE_SERVICE_ROLE_KEY=
SUPABASE_MIRROR_BATCH_SIZE=500
```

The service-role key must stay server-side only. Never expose it to Angular.

### Service

Add a service such as:

```text
app/Services/SupabaseMirrorService.php
```

Responsibilities:

- Pre-flight check: query total row count per table and log a warning if async thresholds are crossed.
- Read each source table in chunks.
- Transform MySQL records into Supabase-compatible payloads.
- Upsert each batch into Supabase REST API.
- **Retry once on 5xx errors** before marking a table as failed.
- Track per-table synced counts and status in the rich JSON format.
- Throw clear exceptions on failed Supabase responses after retry.

Suggested REST endpoint pattern:

```text
POST {SUPABASE_URL}/rest/v1/{table}?on_conflict=id
```

Headers:

```text
Authorization: Bearer {SUPABASE_SERVICE_ROLE_KEY}
apikey: {SUPABASE_SERVICE_ROLE_KEY}
Content-Type: application/json
Prefer: resolution=merge-duplicates
```

### Controller

Add an admin-only controller such as:

```text
app/Http/Controllers/Api/SupabaseBackupController.php
```

Endpoints:

```text
POST /api/backups/supabase/run
GET  /api/backups/supabase/latest
```

Behavior:

- `run` creates a `backup_runs` row with status `running`, recording the `initiated_by_user_id` from `auth()->id()`.
- It executes the mirror sync.
- On success, marks status `completed` with the rich `tables_synced` JSON.
- On error, marks status `failed` and stores the error message.
- `latest` returns the latest run and the latest successful run.

Phase 1 can run synchronously from the button if the dataset is small (under threshold). If timeout risk appears, move the sync to a queued job before enabling the UI for production.

## Frontend Design

Add an admin-only panel in an existing admin area, likely dashboard or billing/settings.

Panel contents:

- Last successful backup time.
- Latest backup status.
- Records synced.
- Per-table breakdown (from `tables_synced` JSON).
- Error message if the latest run failed.
- Button: `Back Up to Supabase`.

Button behavior:

- Disabled while request is running.
- Calls `POST /api/backups/supabase/run`.
- Refreshes latest status after completion.
- Shows toast on success/failure.

Workers/retailers must not see this panel.

## Manual Test Plan

Before testing on production:

1. Create a Supabase project.
2. Create mirror tables by running `database/supabase/mirror_schema.sql`.
3. Enable RLS on all mirror tables (included in the SQL file).
4. Add Supabase env vars to Laravel `.env`.
5. Run:

```bash
php artisan config:clear
```

6. Call:

```bash
php artisan route:list
```

7. Use the admin UI button or test endpoint.
8. Verify records appear in Supabase tables.
9. Re-run backup and confirm records update instead of duplicating.
10. **Cleanup step (for shared/dev Supabase projects):** Before testing idempotency from scratch, truncate all mirror tables in Supabase:
    ```sql
    TRUNCATE businesses, users, clients, products, ... RESTART IDENTITY;
    ```
    Then re-run and confirm a clean insert works correctly.

Production safety check:

- Run first backup during a quieter period.
- Watch Laravel logs.
- Confirm app users can continue making sales during backup.

## Future Phase 2

After Phase 1 is proven:

- Add Laravel Scheduler command.
- Run nightly or hourly backups.
- Use `updated_at` for incremental sync.
- Add retry behavior.
- Add notifications for failed backups via **Laravel Notifications** — default channel: email to the super-admin address. Optionally add a Slack webhook channel. Decide the channel before starting Phase 2 to avoid stalling.

## Future Phase 3

Disaster recovery tooling:

- Restore script from Supabase back to MySQL.
- Point-in-time export from Supabase mirror.
- Admin download/export tools.
