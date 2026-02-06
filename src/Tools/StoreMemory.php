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
class StoreMemory implements Tool
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
        return 'Store a new memory from the conversation. Use this to save important user preferences, facts, or decisions for future recall.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $memoryManager = app(MemoryManager::class);

        $memory = $memoryManager->store(
            $request['content'],
            $this->context,
        );

        return "Memory stored successfully (ID: {$memory->id}).";
    }

    /**
     * Get the tool's schema definition.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string()
                ->description('The content to store as a memory. Should be a concise, meaningful statement.')
                ->required(),
        ];
    }
}
