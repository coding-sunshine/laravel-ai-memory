<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Tools;

use Eznix86\AI\Memory\Models\Memory;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * @property array<string, mixed> $context
 */
class UpdateMemory implements Tool
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
        return 'Update an existing memory by ID with new content. Use this to correct or refine previously stored information.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        /** @var Memory|null $memory */
        $memory = Memory::find($request['memory_id']);

        if (! $memory) {
            return 'Memory not found.';
        }

        $embedding = Str::of($request['content'])->toEmbeddings();

        $memory->update([
            'content' => $request['content'],
            'embedding' => $embedding,
        ]);

        return "Memory updated successfully (ID: {$memory->id}).";
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
                ->description('The ID of the memory to update.')
                ->required(),
            'content' => $schema->string()
                ->description('The new content for the memory.')
                ->required(),
        ];
    }
}
