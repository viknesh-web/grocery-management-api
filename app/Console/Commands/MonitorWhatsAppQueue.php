<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorWhatsAppQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:monitor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor WhatsApp queue status';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $pending = DB::table('jobs')
            ->where('queue', 'whatsapp')
            ->count();

        $failed = DB::table('failed_jobs')
            ->where('queue', 'whatsapp')
            ->orWhere('queue', 'like', '%whatsapp%')
            ->count();

        $this->info("WhatsApp Queue Status:");
        $this->line("Pending: {$pending}");
        $this->line("Failed: {$failed}");

        if ($failed > 0) {
            $this->warn("There are {$failed} failed jobs. Run 'php artisan queue:retry all' to retry.");
        }
    }
}
