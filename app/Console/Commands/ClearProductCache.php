<?php

namespace App\Console\Commands;

use App\Services\CacheService;
use Illuminate\Console\Command;

class ClearProductCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all product caches';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        CacheService::clearProductCache();
        $this->info('Product cache cleared successfully!');
    }
}
