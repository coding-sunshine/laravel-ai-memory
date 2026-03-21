<?php

declare(strict_types=1);

namespace Eznix86\AI\Memory\Services;

use Eznix86\AI\Memory\Events\MemoryDeduplicated;
use Eznix86\AI\Memory\Events\MemoryExpired;
use Eznix86\AI\Memory\Events\MemoryForgotten;
use Eznix86\AI\Memory\Events\MemoryRecalled;
use Eznix86\AI\Memory\Events\MemoryStored;
use Eznix86\AI\Memory\Models\Memory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MemoryManager
{
    /**
     * Store a memory with automatic embedding generation.
     *
     * @param  array<string, mixed>  $context
     */
    public function store(string $content, array $context = [], ?Carbon $ttl = null, ?string $type = null): Memory
    {
        $embedding = Str::of($content)->toEmbeddings();

        $dedupThreshold = (float) config('memory.dedup_threshold', 0.95);

        if ($dedupThreshold < 1.0) {
            $existing = $this->applyContext(Memory::query(), $context)
                ->notExpired()
                ->whereVectorSimilarTo('embedding', $embedding, $dedupThreshold)
                ->first();

            if ($existing) {
                $existing->update([
                    'content' => $content,
                    'embedding' => $embedding,
                    'expires_at' => $ttl,
                    'type' => $type ?? $existing->type,
                ]);

                MemoryDeduplicated::dispatch($existing, $content);

                return $existing;
            }
        }

        $attributes = array_merge($context, [
            'content' => $content,
            'embedding' => $embedding,
        ]);

        if ($ttl !== null) {
            $attributes['expires_at'] = $ttl;
        }

        if ($type !== null) {
            $attributes['type'] = $type;
        }

        $memory = Memory::create($attributes);

        MemoryStored::dispatch($memory);

        $this->enforceContextLimit($context);

        return $memory;
    }

    /**
     * Store multiple memories with automatic embedding generation.
     *
     * @param  array<int, string|array{content: string, type?: string, ttl?: Carbon}>  $items
     * @param  array<string, mixed>  $context
     * @return Collection<int, Memory>
     */
    public function storeMany(array $items, array $context = []): Collection
    {
        $memories = collect();

        foreach ($items as $item) {
            if (is_string($item)) {
                $memories->push($this->store($item, $context));
            } else {
                $memories->push($this->store(
                    $item['content'],
                    $context,
                    $item['ttl'] ?? null,
                    $item['type'] ?? null,
                ));
            }
        }

        return $memories;
    }

    /**
     * Recall memories using vector similarity and reranking.
     *
     * @param  array<string, mixed>  $context
     * @return Collection<int, Memory>
     */
    public function recall(string $query, array $context = [], ?int $limit = null): Collection
    {
        $limit ??= config('memory.recall_limit', 10);
        $threshold = config('memory.similarity_threshold', 0.5);
        $oversampleFactor = config('memory.recall_oversample_factor', 2);

        $queryEmbedding = Str::of($query)->toEmbeddings();

        $memories = $this->applyContext(Memory::query(), $context)
            ->notExpired()
            ->whereVectorSimilarTo('embedding', $queryEmbedding, $threshold)
            ->limit($limit * $oversampleFactor)
            ->get();

        if ($memories->isEmpty()) {
            return collect();
        }

        $result = $memories
            ->rerank('content', $query)
            ->take($limit);

        MemoryRecalled::dispatch($result, $query);

        return $result;
    }

    /**
     * Get all memories with optional context filtering.
     *
     * @param  array<string, mixed>  $context
     * @return \Illuminate\Database\Eloquent\Collection<int, Memory>
     */
    public function all(array $context = [], int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return $this->applyContext(Memory::query(), $context)
            ->notExpired()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Delete a specific memory.
     */
    public function forget(int $memoryId): bool
    {
        $deleted = Memory::find($memoryId)?->delete() ?? false;

        if ($deleted) {
            MemoryForgotten::dispatch($memoryId);
        }

        return $deleted;
    }

    /**
     * Delete all memories matching the given context.
     *
     * @param  array<string, mixed>  $context
     */
    public function forgetAll(array $context = []): int
    {
        return (int) $this->applyContext(Memory::query(), $context)->delete();
    }

    /**
     * Prune expired memories and enforce per-context limits.
     *
     * @param  array<string, mixed>  $context
     */
    public function prune(array $context = []): int
    {
        $pruned = 0;

        // Remove expired memories
        $query = $this->applyContext(Memory::query(), $context)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());

        $expiredCount = $query->count();

        if ($expiredCount > 0) {
            $query->delete();
            $pruned += $expiredCount;

            MemoryExpired::dispatch($expiredCount);
        }

        // Enforce per-context limits
        $maxPerContext = (int) config('memory.max_per_context', 0);

        if ($maxPerContext > 0 && ! empty($context)) {
            $pruned += $this->pruneExcessMemories($context, $maxPerContext);
        }

        return $pruned;
    }

    /**
     * Apply context filters to a query builder.
     *
     * @param  mixed  $query
     * @param  array<string, mixed>  $context
     */
    protected function applyContext($query, array $context): mixed
    {
        foreach ($context as $field => $value) {
            $query->where($field, $value);
        }

        return $query;
    }

    /**
     * Enforce the per-context memory limit after a store operation.
     *
     * @param  array<string, mixed>  $context
     */
    protected function enforceContextLimit(array $context): void
    {
        $maxPerContext = (int) config('memory.max_per_context', 0);

        if ($maxPerContext <= 0 || empty($context)) {
            return;
        }

        $this->pruneExcessMemories($context, $maxPerContext);
    }

    /**
     * Remove excess memories beyond the limit for a given context.
     *
     * @param  array<string, mixed>  $context
     */
    protected function pruneExcessMemories(array $context, int $maxPerContext): int
    {
        $count = $this->applyContext(Memory::query(), $context)->notExpired()->count();

        if ($count <= $maxPerContext) {
            return 0;
        }

        $excess = $count - $maxPerContext;

        $strategy = config('memory.pruning_strategy', 'oldest');

        $query = $this->applyContext(Memory::query(), $context)->notExpired();

        if ($strategy === 'oldest') {
            $idsToDelete = $query->orderBy('created_at', 'asc')
                ->limit($excess)
                ->pluck('id');
        } else {
            $idsToDelete = $query->orderBy('created_at', 'asc')
                ->limit($excess)
                ->pluck('id');
        }

        return Memory::whereIn('id', $idsToDelete)->delete();
    }
}
