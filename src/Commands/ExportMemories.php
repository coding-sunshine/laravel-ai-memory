<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Commands;

use Eznix86\AI\Memory\Models\Memory;
use Illuminate\Console\Command;

class ExportMemories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'memory:export
                            {--context=* : Context filters in key:value format (e.g., user_id:123)}
                            {--output= : Output file path (defaults to stdout)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export memories as JSON';

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

        $memories = $query->orderBy('created_at', 'desc')
            ->get(['id', 'user_id', 'content', 'type', 'expires_at', 'created_at', 'updated_at']);

        $json = $memories->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        /** @var string|null $output */
        $output = $this->option('output');

        if ($output) {
            file_put_contents($output, $json);
            $this->info("Exported {$memories->count()} memories to {$output}");
        } else {
            $this->line($json);
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
