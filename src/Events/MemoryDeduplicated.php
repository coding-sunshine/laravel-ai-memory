<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Events;

use Eznix86\AI\Memory\Models\Memory;
use Illuminate\Foundation\Events\Dispatchable;

class MemoryDeduplicated
{
    use Dispatchable;

    public function __construct(
        public readonly Memory $existing,
        public readonly string $newContent,
    ) {}
}
