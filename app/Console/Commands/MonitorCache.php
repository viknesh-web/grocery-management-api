<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class MonitorCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:monitor';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor cache usage';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Cache Configuration:');
        $this->line('Driver: ' . config('cache.default'));
        
        if (config('cache.default') === 'redis') {
            try {
                $redis = \Illuminate\Support\Facades\Redis::connection();
                $info = $redis->info('stats');
                $this->line('Redis Stats:');
                $this->line('  - Keys: ' . ($info['db0'] ?? 'N/A'));
                $this->line('  - Hits: ' . ($info['keyspace_hits'] ?? 'N/A'));
                $this->line('  - Misses: ' . ($info['keyspace_misses'] ?? 'N/A'));
            } catch (\Exception $e) {
                $this->error('Could not connect to Redis');
            }
        }

        // Test cache
        $testKey = 'cache:test:' . time();
        Cache::put($testKey, 'test', 10);
        $value = Cache::get($testKey);
        
        if ($value === 'test') {
            $this->info(' Cache is working!');
        } else {
            $this->error(' Cache is not working!');
        }

        Cache::forget($testKey);
    }
}
