<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Events;

use Illuminate\Foundation\Events\Dispatchable;

class MemoryExpired
{
    use Dispatchable;

    public function __construct(
        public readonly int $count,
    ) {}
}
