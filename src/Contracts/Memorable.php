<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Contracts;

use Eznix86\AI\Memory\MemoryProxy;

interface Memorable
{
    /**
     * Get the memory context for this model.
     *
     * @return array<string, mixed>
     */
    public function memoryContext(): array;

    /**
     * Get a memory proxy scoped to this model's context.
     */
    public function memories(): MemoryProxy;
}
