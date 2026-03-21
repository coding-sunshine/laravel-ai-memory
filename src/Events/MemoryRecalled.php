<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Events;

use Eznix86\AI\Memory\Models\Memory;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Collection;

class MemoryRecalled
{
    use Dispatchable;

    /**
     * @param  Collection<int, Memory>  $memories
     */
    public function __construct(
        public readonly Collection $memories,
        public readonly string $query,
    ) {}
}
