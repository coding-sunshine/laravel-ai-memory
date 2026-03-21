<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Tools;

use Eznix86\AI\Memory\Services\MemoryManager;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * @property array<string, mixed> $context
 */
class ForgetMemory implements Tool
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        protected array $context = [],
    ) {}

    /**
     * Set the context for memory operations.
     *
     * @param  array<string, mixed>  $context
     */
    public function context(array $context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Forget (delete) a specific memory by ID. Use this when information is no longer relevant or was stored incorrectly.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $memoryManager = app(MemoryManager::class);

        $deleted = $memoryManager->forget($request['memory_id']);

        return $deleted
            ? 'Memory forgotten successfully.'
            : 'Memory not found.';
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'memory_id' => $schema->integer()
                ->description('The ID of the memory to forget.')
                ->required(),
        ];
    }
}
