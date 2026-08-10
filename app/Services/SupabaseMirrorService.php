<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SupabaseMirrorService
{
    private string $supabaseUrl;
    private string $serviceRoleKey;
    private int    $batchSize;

    /**
     * Explicit column list per table.
     * - Keeps only the columns that exist in both MySQL and the Supabase mirror schema.
     * - Excludes sensitive fields (password, remember_token).
     * - Uses null to mean "select *" (only for tables with no exclusions and exact schema match).
     */
    private array $tableColumns = [
        'businesses' => [
            'id', 'parent_id', 'name', 'email', 'phone', 'address',
            'trial_ends_at', 'subscription_status', 'subscription_plan',
            'total_revenue', 'last_payment_date', 'last_payment_status',
            'paystack_customer_code', 'paystack_subscription_code',
            'paystack_email_token', 'subscription_ends_at',
            'created_at', 'updated_at',
        ],
        'users' => [
            // password and remember_token deliberately excluded
            'id', 'name', 'email', 'role', 'permissions', 'status',
            'contact', 'last_login_at', 'email_verified_at',
            'business_id', 'created_at', 'updated_at',
        ],
        'clients' => [
            'id', 'name', 'contact', 'location', 'email', 'total_debt',
            'business_id', 'created_at', 'updated_at',
        ],
        'products' => [
            'id', 'name', 'category', 'description', 'cost_price', 'sell_price',
            'quantity', 'last_added_qty', 'base_unit', 'low_stock_alert',
            'business_id', 'created_at', 'updated_at',
        ],
        'product_units' => [
            'id', 'product_id', 'unit_name', 'quantity_in_base', 'price',
            'is_bulk', 'bulk_discount_pct', 'business_id', 'created_at', 'updated_at',
        ],
        'wholesale_products' => [
            'id', 'name', 'category', 'description', 'cost_price', 'sell_price',
            'quantity', 'last_added_qty', 'base_unit', 'low_stock_alert',
            'business_id', 'created_at', 'updated_at',
        ],
        'wholesale_product_units' => [
            'id', 'wholesale_product_id', 'unit_name', 'quantity_in_base', 'price',
            'is_bulk', 'bulk_discount_pct', 'business_id', 'created_at', 'updated_at',
        ],
        'retail_sales' => [
            'id', 'user_id', 'receipt_number', 'total_amount', 'total_cost', 'profit',
            'payment_method', 'momo_number', 'status', 'business_id', 'created_at', 'updated_at',
        ],
        'retail_sale_items' => [
            'id', 'retail_sale_id', 'product_id', 'product_name', 'unit_name',
            'quantity', 'quantity_base', 'unit_price', 'cost_price', 'subtotal',
            'business_id', 'created_at', 'updated_at',
        ],
        'wholesale_sales' => [
            'id', 'user_id', 'client_id', 'receipt_number', 'total_amount', 'total_cost',
            'profit', 'payment_method', 'momo_number', 'amount_paid', 'debt', 'status',
            'business_id', 'created_at', 'updated_at',
        ],
        'wholesale_sale_items' => [
            'id', 'wholesale_sale_id', 'wholesale_product_id', 'product_name', 'unit_name',
            'quantity', 'quantity_base', 'unit_price', 'cost_price', 'subtotal',
            'business_id', 'created_at', 'updated_at',
        ],
        'expenses' => [
            'id', 'title', 'amount', 'category', 'note', 'expense_date',
            'recorded_by', 'business_id', 'created_at', 'updated_at',
        ],
        'reversals' => [
            'id', 'retail_sale_id', 'user_id', 'reason', 'reversed_items',
            'amount_reversed', 'cost_reversed', 'is_partial',
            'business_id', 'created_at', 'updated_at',
        ],
        'debts' => [
            'id', 'wholesale_sale_id', 'client_id', 'amount_paid', 'old_debt',
            'new_debt', 'note', 'business_id', 'created_at', 'updated_at',
        ],
        'stock_transfers' => [
            'id', 'business_id', 'from_type', 'from_product_id', 'from_product_name',
            'source_unit_id', 'source_unit_name', 'source_unit_quantity_in_base',
            'source_base_unit', 'to_type', 'to_product_id', 'to_product_name',
            'to_business_id', 'auto_created', 'display_quantity', 'quantity',
            'note', 'transferred_by', 'created_at', 'updated_at',
        ],
    ];

    /** Warn if any single table exceeds this row count (suggest async job). */
    private const WARN_TABLE_ROWS = 5000;

    /** Warn if total rows across all tables exceeds this. */
    private const WARN_TOTAL_ROWS = 10000;

    public function __construct()
    {
        $this->supabaseUrl    = rtrim((string) config('services.supabase.url', ''), '/');
        $this->serviceRoleKey = (string) config('services.supabase.service_role_key', '');
        $this->batchSize      = (int) config('services.supabase.mirror_batch_size', 500);
    }

    /**
     * Run the full mirror sync across all tables.
     *
     * @return array{ total_synced: int, table_results: array }
     * @throws \RuntimeException if Supabase credentials are not configured.
     */
    public function run(): array
    {
        $this->validateConfig();
        $this->preflight();

        $tableResults = [];
        $totalSynced  = 0;

        foreach (array_keys($this->tableColumns) as $table) {
            $result               = $this->syncTable($table);
            $tableResults[$table] = $result;

            if ($result['status'] === 'ok') {
                $totalSynced += $result['synced'];
            }
        }

        return [
            'total_synced'  => $totalSynced,
            'table_results' => $tableResults,
        ];
    }

    /**
     * Sync a single table from MySQL → Supabase in batches.
     */
    private function syncTable(string $table): array
    {
        Log::info("[SupabaseMirror] Syncing: {$table}");

        $columns = $this->tableColumns[$table];
        $synced  = 0;
        $offset  = 0;

        try {
            do {
                $rows = DB::table($table)
                    ->select($columns)
                    ->orderBy('id')
                    ->offset($offset)
                    ->limit($this->batchSize)
                    ->get()
                    ->toArray();

                if (empty($rows)) {
                    break;
                }

                $payload = array_map(fn($row) => (array) $row, $rows);

                $this->upsertBatch($table, $payload);

                $synced += count($rows);
                $offset  += $this->batchSize;

            } while (count($rows) === $this->batchSize);

            Log::info("[SupabaseMirror] Done {$table}: {$synced} records.");
            return ['synced' => $synced, 'status' => 'ok'];

        } catch (\Throwable $e) {
            Log::error("[SupabaseMirror] Failed on {$table}: " . $e->getMessage());
            return ['synced' => $synced, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * POST a batch to Supabase REST API with upsert.
     * Retries once on 5xx before throwing.
     *
     * @throws \RuntimeException
     */
    private function upsertBatch(string $table, array $payload, int $attempt = 1): void
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->serviceRoleKey}",
            'apikey'        => $this->serviceRoleKey,
            'Content-Type'  => 'application/json',
            'Prefer'        => 'resolution=merge-duplicates',
        ])->post("{$this->supabaseUrl}/rest/v1/{$table}?on_conflict=id", $payload);

        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $body   = $response->body();

        // Retry once on server errors
        if ($status >= 500 && $attempt === 1) {
            Log::warning("[SupabaseMirror] Supabase {$status} on {$table}, retrying...");
            sleep(2);
            $this->upsertBatch($table, $payload, 2);
            return;
        }

        throw new \RuntimeException(
            "Supabase upsert failed for table '{$table}' (HTTP {$status}): {$body}"
        );
    }

    /**
     * Pre-flight: count rows and log warnings if thresholds are crossed.
     */
    private function preflight(): void
    {
        $totalRows = 0;

        foreach (array_keys($this->tableColumns) as $table) {
            try {
                $count      = DB::table($table)->count();
                $totalRows += $count;

                if ($count > self::WARN_TABLE_ROWS) {
                    Log::warning(
                        "[SupabaseMirror] Table '{$table}' has {$count} rows " .
                        "(threshold: " . self::WARN_TABLE_ROWS . "). Consider a queued job."
                    );
                }
            } catch (\Throwable $e) {
                Log::warning("[SupabaseMirror] Pre-flight count failed for {$table}: " . $e->getMessage());
            }
        }

        if ($totalRows > self::WARN_TOTAL_ROWS) {
            Log::warning(
                "[SupabaseMirror] Total rows ~{$totalRows} exceed threshold " .
                self::WARN_TOTAL_ROWS . ". Consider moving sync to a queued job."
            );
        }

        Log::info("[SupabaseMirror] Pre-flight complete. ~{$totalRows} rows to sync.");
    }

    /**
     * @throws \RuntimeException
     */
    private function validateConfig(): void
    {
        if (empty($this->supabaseUrl) || empty($this->serviceRoleKey)) {
            throw new \RuntimeException(
                'Supabase is not configured. Set SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY in your .env file.'
            );
        }
    }
}
