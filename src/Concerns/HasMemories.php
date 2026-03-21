<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Concerns;

use Eznix86\AI\Memory\MemoryProxy;
use Eznix86\AI\Memory\Services\MemoryManager;

/** @phpstan-ignore trait.unused */
trait HasMemories
{
    /**
     * Get a memory proxy scoped to this model's context.
     */
    public function memories(): MemoryProxy
    {
        return new MemoryProxy(
            app(MemoryManager::class),
            $this->memoryContext(),
        );
    }
}
