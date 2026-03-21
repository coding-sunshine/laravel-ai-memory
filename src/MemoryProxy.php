<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory;

use Eznix86\AI\Memory\Models\Memory;
use Eznix86\AI\Memory\Services\MemoryManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MemoryProxy
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        protected MemoryManager $manager,
        protected array $context,
    ) {}

    /**
     * Store a memory within this model's context.
     */
    public function store(string $content, ?Carbon $ttl = null, ?string $type = null): Memory
    {
        return $this->manager->store($content, $this->context, $ttl, $type);
    }

    /**
     * Recall memories within this model's context.
     *
     * @return Collection<int, Memory>
     */
    public function recall(string $query, ?int $limit = null): Collection
    {
        return $this->manager->recall($query, $this->context, $limit);
    }

    /**
     * Get all memories within this model's context.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Memory>
     */
    public function all(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return $this->manager->all($this->context, $limit);
    }

    /**
     * Delete a specific memory.
     */
    public function forget(int $memoryId): bool
    {
        return $this->manager->forget($memoryId);
    }

    /**
     * Delete all memories within this model's context.
     */
    public function forgetAll(): int
    {
        return $this->manager->forgetAll($this->context);
    }

    /**
     * Prune expired and excess memories within this model's context.
     */
    public function prune(): int
    {
        return $this->manager->prune($this->context);
    }

    /**
     * Store multiple memories within this model's context.
     *
     * @param  array<int, string|array{content: string, type?: string, ttl?: Carbon}>  $items
     * @return Collection<int, Memory>
     */
    public function storeMany(array $items): Collection
    {
        return $this->manager->storeMany($items, $this->context);
    }
}
