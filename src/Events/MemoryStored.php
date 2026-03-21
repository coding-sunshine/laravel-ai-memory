<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Events;

use Eznix86\AI\Memory\Models\Memory;
use Illuminate\Foundation\Events\Dispatchable;

class MemoryStored
{
    use Dispatchable;

    public function __construct(
        public readonly Memory $memory,
    ) {}
}
