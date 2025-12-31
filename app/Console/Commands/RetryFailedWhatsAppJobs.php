<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class RetryFailedWhatsAppJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:retry-failed {--limit=10}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry failed WhatsApp jobs';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $limit = (int) $this->option('limit');

        $failedJobs = DB::table('failed_jobs')
            ->where('queue', 'whatsapp')
            ->orWhere('queue', 'like', '%whatsapp%')
            ->limit($limit)
            ->get();

        if ($failedJobs->isEmpty()) {
            $this->info('No failed WhatsApp jobs to retry.');
            return;
        }

        $this->info("Retrying {$failedJobs->count()} failed WhatsApp jobs...");

        foreach ($failedJobs as $job) {
            Artisan::call('queue:retry', ['id' => $job->id]);
        }

        $this->info('Done!');
    }
}
