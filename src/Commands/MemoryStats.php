<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Commands;

use Eznix86\AI\Memory\Models\Memory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MemoryStats extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memory:stats
                            {--context=* : Context filters in key:value format (e.g., user_id:123)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display memory statistics';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $context = $this->parseContext();

        $query = Memory::query();

        foreach ($context as $field => $value) {
            $query->where($field, $value);
        }

        $total = $query->count();
        $expired = (clone $query)->whereNotNull('expires_at')->where('expires_at', '<=', now())->count();
        $active = (clone $query)->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->count();

        $this->info('Memory Statistics');
        $this->info('=================');
        $this->info("Total memories: {$total}");
        $this->info("Active memories: {$active}");
        $this->info("Expired memories: {$expired}");

        // Type breakdown
        $types = (clone $query)->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        if (! empty($types)) {
            $this->newLine();
            $this->info('By type:');
            foreach ($types as $type => $count) {
                $label = $type ?: '(none)';
                $this->info("  {$label}: {$count}");
            }
        }

        // Per-user breakdown (if no specific context filter)
        if (empty($context)) {
            $userCounts = Memory::select('user_id', DB::raw('count(*) as count'))
                ->groupBy('user_id')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'user_id')
                ->toArray();

            if (! empty($userCounts)) {
                $this->newLine();
                $this->info('Top contexts (by user_id):');
                foreach ($userCounts as $userId => $count) {
                    $label = $userId ?: '(none)';
                    $this->info("  {$label}: {$count}");
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * Parse context options into an associative array.
     *
     * @return array<string, string>
     */
    protected function parseContext(): array
    {
        $context = [];

        /** @var array<int, string> $contextOptions */
        $contextOptions = $this->option('context');

        foreach ($contextOptions as $pair) {
            if (str_contains($pair, ':')) {
                [$key, $value] = explode(':', $pair, 2);
                $context[$key] = $value;
            }
        }

        return $context;
    }
}
