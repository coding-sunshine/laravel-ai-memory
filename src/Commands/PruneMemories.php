<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Commands;

use Eznix86\AI\Memory\Models\Memory;
use Eznix86\AI\Memory\Services\MemoryManager;
use Illuminate\Console\Command;

class PruneMemories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memory:prune
                            {--context=* : Context filters in key:value format (e.g., user_id:123)}
                            {--dry-run : Preview what would be pruned without deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune expired memories and enforce per-context limits';

    /**
     * Execute the console command.
     */
    public function handle(MemoryManager $manager): int
    {
        $context = $this->parseContext();

        if ($this->option('dry-run')) {
            $this->info('Dry run mode — no memories will be deleted.');
            $this->previewPrune($context);

            return self::SUCCESS;
        }

        $pruned = $manager->prune($context);

        $this->info("Pruned {$pruned} ".($pruned === 1 ? 'memory' : 'memories').'.');

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

    /**
     * Preview what would be pruned.
     *
     * @param  array<string, string>  $context
     */
    protected function previewPrune(array $context): void
    {
        $query = Memory::query();

        foreach ($context as $field => $value) {
            $query->where($field, $value);
        }

        $expired = (clone $query)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->count();

        $this->info("Expired memories to prune: {$expired}");

        $maxPerContext = (int) config('memory.max_per_context', 0);

        if ($maxPerContext > 0 && ! empty($context)) {
            $total = (clone $query)->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })->count();

            $excess = max(0, $total - $maxPerContext);
            $this->info("Excess memories beyond limit ({$maxPerContext}): {$excess}");
        }
    }
}
