<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupRun;
use App\Services\SupabaseMirrorService;
use Illuminate\Http\JsonResponse;

class SupabaseBackupController extends Controller
{
    public function __construct(
        private readonly SupabaseMirrorService $mirrorService
    ) {}

    /**
     * POST /api/backups/supabase/run
     *
     * Triggers a full mirror backup from MySQL to Supabase.
     * Admin-only (enforced via route middleware: auth:sanctum + super_admin).
     */
    public function run(): JsonResponse
    {
        // Create a tracking record immediately so the admin can see it started
        $backupRun = BackupRun::create([
            'target'               => 'supabase',
            'status'               => 'running',
            'initiated_by_user_id' => auth()->id(),
            'started_at'           => now(),
        ]);

        try {
            $result = $this->mirrorService->run();

            $backupRun->markCompleted(
                $result['total_synced'],
                $result['table_results']
            );

            return response()->json([
                'message'        => 'Backup completed successfully.',
                'records_synced' => $result['total_synced'],
                'tables_synced'  => $result['table_results'],
                'backup_run_id'  => $backupRun->id,
                'finished_at'    => $backupRun->finished_at,
            ]);

        } catch (\Throwable $e) {
            $backupRun->markFailed($e->getMessage());

            return response()->json([
                'message'       => 'Backup failed.',
                'error'         => $e->getMessage(),
                'backup_run_id' => $backupRun->id,
            ], 500);
        }
    }

    /**
     * GET /api/backups/supabase/latest
     *
     * Returns the latest backup run and the latest successful run.
     */
    public function latest(): JsonResponse
    {
        $latest    = BackupRun::where('target', 'supabase')
            ->latest()
            ->first();

        $lastSuccess = BackupRun::where('target', 'supabase')
            ->where('status', 'completed')
            ->latest('finished_at')
            ->first();

        return response()->json([
            'latest'       => $latest ? $this->formatRun($latest) : null,
            'last_success' => $lastSuccess ? $this->formatRun($lastSuccess) : null,
        ]);
    }

    /** Format a BackupRun for API response. */
    private function formatRun(BackupRun $run): array
    {
        return [
            'id'                   => $run->id,
            'status'               => $run->status,
            'target'               => $run->target,
            'initiated_by_user_id' => $run->initiated_by_user_id,
            'started_at'           => $run->started_at,
            'finished_at'          => $run->finished_at,
            'records_synced'       => $run->records_synced,
            'tables_synced'        => $run->tables_synced,
            'error_message'        => $run->error_message,
        ];
    }
}
