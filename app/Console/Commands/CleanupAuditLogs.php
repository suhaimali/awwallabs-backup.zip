<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

class CleanupAuditLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:cleanup {--days= : Days of report audit history to keep (default: config audit.retention_days)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old audit logs to prevent database bloat';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) ($this->option('days') ?: config('audit.retention_days', 1825));
        if ($days < 30) {
            $this->error('Refusing to keep less than 30 days of audit history.');
            return self::FAILURE;
        }
        $date = Carbon::now()->subDays($days);
        
        $this->info("Deleting audit logs older than {$days} days ({$date->toDateString()})...");
        
        $deletedCount = \App\Models\TestReportAudit::where('created_at', '<', $date)->delete();
        
        $this->info("Successfully deleted {$deletedCount} old audit logs.");
    }
}
