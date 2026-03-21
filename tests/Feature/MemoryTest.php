<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Events\MemoryDeduplicated;
use Eznix86\AI\Memory\Events\MemoryExpired;
use Eznix86\AI\Memory\Events\MemoryForgotten;
use Eznix86\AI\Memory\Events\MemoryRecalled;
use Eznix86\AI\Memory\Events\MemoryStored;
use Eznix86\AI\Memory\Facades\AgentMemory;
use Eznix86\AI\Memory\Models\Memory as MemoryModel;
use Illuminate\Support\Facades\Event;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Laravel\Ai\Reranking;
use Laravel\Ai\Responses\Data\RankedDocument;

test('can store memories with user context', function (): void {
    AgentMemory::fake();

    $memory = AgentMemory::store('User prefers dark mode', ['user_id' => 'user-123']);

    expect($memory)
        ->toBeInstanceOf(MemoryModel::class)
        ->and($memory->user_id)->toBe('user-123')
        ->and($memory->content)->toBe('User prefers dark mode')
        ->and($memory->embedding)->toBeArray()
        ->and(count($memory->embedding))->toBe(1536);

    $this->assertDatabaseHas('memories', [
        'user_id' => 'user-123',
        'content' => 'User prefers dark mode',
    ]);

    Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->contains('User prefers dark mode'));
});

test('can recall memories using semantic search', function (): void {
    AgentMemory::fake([
        [
            new RankedDocument(index: 0, document: 'User likes dark themes', score: 0.9),
            new RankedDocument(index: 1, document: 'Application should use dark mode', score: 0.8),
        ],
    ]);

    // Store some memories
    AgentMemory::store('User likes dark themes', ['user_id' => 'user-123']);
    AgentMemory::store('Application should use dark mode', ['user_id' => 'user-123']);

    // Recall memories
    $memories = AgentMemory::recall("What are the user's UI preferences?", ['user_id' => 'user-123'], 2);

    expect($memories)->toHaveCount(2)
        ->and($memories->first()->content)->toBe('User likes dark themes')
        ->and($memories->last()->content)->toBe('Application should use dark mode');

    Reranking::assertReranked(fn ($prompt) => $prompt->contains("What are the user's UI preferences?"));
});

test('can filter memories by user context', function (): void {
    AgentMemory::fake();

    // Store memories for different users
    AgentMemory::store('User A preference', ['user_id' => 'user-a']);
    AgentMemory::store('User B preference', ['user_id' => 'user-b']);

    // Should only recall memories for specific user
    $memoriesA = AgentMemory::recall('preference', ['user_id' => 'user-a']);

    // User A should only see their own memory
    expect($memoriesA)->toHaveCount(1)
        ->and($memoriesA->first()->content)->toBe('User A preference');
});

test('can store memories without user context', function (): void {
    AgentMemory::fake();

    $memory = AgentMemory::store('Global system setting');

    expect($memory->user_id)->toBeNull()
        ->and($memory->content)->toBe('Global system setting');
});

test('can delete specific memory', function (): void {
    AgentMemory::fake();

    $memory = AgentMemory::store('Memory to delete', ['user_id' => 'user-123']);

    $deleted = AgentMemory::forget($memory->id);

    expect($deleted)->toBeTrue();
    $this->assertDatabaseMissing('memories', ['id' => $memory->id]);
});

test('can delete all memories for user', function (): void {
    AgentMemory::fake();

    // Store memories for different users
    AgentMemory::store('Memory 1', ['user_id' => 'user-123']);
    AgentMemory::store('Memory 2', ['user_id' => 'user-123']);
    AgentMemory::store('Memory 3', ['user_id' => 'user-456']);

    $deletedCount = AgentMemory::forgetAll(['user_id' => 'user-123']);

    expect($deletedCount)->toBe(2);
    $this->assertDatabaseMissing('memories', ['user_id' => 'user-123']);
    $this->assertDatabaseHas('memories', ['user_id' => 'user-456']);
});

test('can get all memories for user', function (): void {
    AgentMemory::fake();

    // Store memories for different users
    AgentMemory::store('Memory 1', ['user_id' => 'user-123']);
    AgentMemory::store('Memory 2', ['user_id' => 'user-123']);
    AgentMemory::store('Memory 3', ['user_id' => 'user-456']);

    $memories = AgentMemory::all(['user_id' => 'user-123']);

    expect($memories)->toHaveCount(2);
});

test('recall respects limit parameter', function (): void {
    AgentMemory::fake();

    // Store multiple memories
    for ($i = 1; $i <= 5; $i++) {
        AgentMemory::store("Memory $i", ['user_id' => 'user-123']);
    }

    $memories = AgentMemory::recall('memory', ['user_id' => 'user-123'], 3);

    expect($memories)->toHaveCount(3);
});

test('handles non-existent memory deletion gracefully', function (): void {
    $deleted = AgentMemory::forget(999);

    expect($deleted)->toBeFalse();
});

test('memory model uses correct table and casts', function (): void {
    $memory = new MemoryModel([
        'user_id' => 'test',
        'content' => 'test content',
        'embedding' => [0.1, 0.2, 0.3],
    ]);

    expect($memory->getTable())->toBe('memories')
        ->and($memory->getFillable())->toBe(['user_id', 'content', 'embedding', 'type', 'expires_at'])
        ->and($memory->getCasts())->toHaveKey('embedding', 'array')
        ->and($memory->getCasts())->toHaveKey('expires_at', 'datetime');
});

test('recall returns empty collection when no memories exist', function (): void {
    AgentMemory::fake();

    $memories = AgentMemory::recall('anything', ['user_id' => 'user-nonexistent']);

    expect($memories)->toBeEmpty();

    Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->contains('anything'));
});

// ──────────────────────────────────────────────────────────────────
// Config Tests
// ──────────────────────────────────────────────────────────────────

test('config values have sensible defaults', function (): void {
    expect(config('memory.dimensions'))->toBe(1536)
        ->and(config('memory.similarity_threshold'))->toBe(0.5)
        ->and(config('memory.recall_limit'))->toBe(10)
        ->and(config('memory.middleware_recall_limit'))->toBe(5)
        ->and(config('memory.recall_oversample_factor'))->toBe(2)
        ->and(config('memory.table'))->toBe('memories')
        ->and(config('memory.dedup_threshold'))->toBe(0.95)
        ->and(config('memory.max_per_context'))->toBe(0)
        ->and(config('memory.pruning_strategy'))->toBe('oldest');
});

test('memory model uses configured table name', function (): void {
    config(['memory.table' => 'custom_memories']);

    $memory = new MemoryModel;

    expect($memory->getTable())->toBe('custom_memories');

    // Reset
    config(['memory.table' => 'memories']);
});

// ──────────────────────────────────────────────────────────────────
// Full Flow Integration Tests
// ──────────────────────────────────────────────────────────────────

test('full flow: store then recall returns relevant memories', function (): void {
    AgentMemory::fake();

    // Step 1: Store multiple memories over time
    AgentMemory::store('User works at Acme Corp', ['user_id' => 'user-42']);
    AgentMemory::store('User prefers PHP over Python', ['user_id' => 'user-42']);
    AgentMemory::store('User timezone is UTC+4', ['user_id' => 'user-42']);

    // Step 2: Recall with a query
    $results = AgentMemory::recall('What company does the user work at?', ['user_id' => 'user-42']);

    // Step 3: Verify we get results
    expect($results)->not->toBeEmpty()
        ->and($results->pluck('content')->toArray())->toContain('User works at Acme Corp');

    // Step 4: Verify embedding generation was called for store + recall
    Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt): bool => $prompt->contains('User works at Acme Corp'));
});

test('full flow: store, forget, recall no longer returns deleted memory', function (): void {
    AgentMemory::fake();

    // Store
    $memory = AgentMemory::store('Outdated preference', ['user_id' => 'user-42']);

    // Forget
    AgentMemory::forget($memory->id);

    // Recall should not find the deleted memory
    $results = AgentMemory::recall('preference', ['user_id' => 'user-42']);

    expect($results)->toBeEmpty();
});

test('full flow: forgetAll clears all memories then recall returns empty', function (): void {
    AgentMemory::fake();

    AgentMemory::store('Memory A', ['user_id' => 'user-42']);
    AgentMemory::store('Memory B', ['user_id' => 'user-42']);

    AgentMemory::forgetAll(['user_id' => 'user-42']);

    $results = AgentMemory::recall('anything', ['user_id' => 'user-42']);

    expect($results)->toBeEmpty();
});

// ──────────────────────────────────────────────────────────────────
// TTL / Expiration Tests
// ──────────────────────────────────────────────────────────────────

test('can store memory with TTL', function (): void {
    AgentMemory::fake();

    $ttl = now()->addWeek();
    $memory = AgentMemory::store('Temporary fact', ['user_id' => 'user-123'], $ttl);

    expect($memory->expires_at)->not->toBeNull()
        ->and($memory->expires_at->toDateTimeString())->toBe($ttl->toDateTimeString());

    $this->assertDatabaseHas('memories', [
        'user_id' => 'user-123',
        'content' => 'Temporary fact',
    ]);
});

test('recall excludes expired memories', function (): void {
    AgentMemory::fake();

    // Store a memory that already expired
    AgentMemory::store('Expired fact', ['user_id' => 'user-123'], now()->subDay());
    AgentMemory::store('Active fact', ['user_id' => 'user-123']);

    $memories = AgentMemory::recall('fact', ['user_id' => 'user-123']);

    expect($memories)->toHaveCount(1)
        ->and($memories->first()->content)->toBe('Active fact');
});

test('all() excludes expired memories', function (): void {
    AgentMemory::fake();

    AgentMemory::store('Expired memory', ['user_id' => 'user-123'], now()->subHour());
    AgentMemory::store('Active memory', ['user_id' => 'user-123']);

    $memories = AgentMemory::all(['user_id' => 'user-123']);

    expect($memories)->toHaveCount(1)
        ->and($memories->first()->content)->toBe('Active memory');
});

test('store without TTL has null expires_at', function (): void {
    AgentMemory::fake();

    $memory = AgentMemory::store('Permanent fact', ['user_id' => 'user-123']);

    expect($memory->expires_at)->toBeNull();
});

// ──────────────────────────────────────────────────────────────────
// Type / Tags Tests
// ──────────────────────────────────────────────────────────────────

test('can store memory with type', function (): void {
    AgentMemory::fake();

    $memory = AgentMemory::store('User likes dark mode', ['user_id' => 'user-123'], type: 'preference');

    expect($memory->type)->toBe('preference');

    $this->assertDatabaseHas('memories', [
        'user_id' => 'user-123',
        'content' => 'User likes dark mode',
        'type' => 'preference',
    ]);
});

test('can filter recall by type via context', function (): void {
    AgentMemory::fake();

    AgentMemory::store('User likes dark mode', ['user_id' => 'user-123'], type: 'preference');
    AgentMemory::store('The earth is round', ['user_id' => 'user-123'], type: 'fact');

    $preferences = AgentMemory::recall('dark', ['user_id' => 'user-123', 'type' => 'preference']);

    expect($preferences)->toHaveCount(1)
        ->and($preferences->first()->content)->toBe('User likes dark mode');
});

test('store without type has null type', function (): void {
    AgentMemory::fake();

    $memory = AgentMemory::store('Untyped memory');

    expect($memory->type)->toBeNull();
});

// ──────────────────────────────────────────────────────────────────
// Deduplication Tests
// ──────────────────────────────────────────────────────────────────

test('deduplication updates existing memory when similarity exceeds threshold', function (): void {
    // With fake embeddings, all vectors are identical (cosine similarity ≈ 1.0)
    // so dedup should trigger (default threshold is 0.95)
    AgentMemory::fake();

    $first = AgentMemory::store('User prefers dark mode', ['user_id' => 'user-123']);
    $second = AgentMemory::store('User prefers dark mode updated', ['user_id' => 'user-123']);

    // Should have updated the first memory, not created a new one
    expect($second->id)->toBe($first->id)
        ->and($second->content)->toBe('User prefers dark mode updated');

    // Only one memory should exist
    $all = AgentMemory::all(['user_id' => 'user-123']);
    expect($all)->toHaveCount(1);
});

test('deduplication does not trigger when disabled', function (): void {
    config(['memory.dedup_threshold' => 1.0]);
    AgentMemory::fake();

    AgentMemory::store('Memory A', ['user_id' => 'user-123']);
    AgentMemory::store('Memory B', ['user_id' => 'user-123']);

    $all = AgentMemory::all(['user_id' => 'user-123']);
    expect($all)->toHaveCount(2);
});

test('deduplication respects context isolation', function (): void {
    AgentMemory::fake();

    AgentMemory::store('Same content', ['user_id' => 'user-a']);
    AgentMemory::store('Same content', ['user_id' => 'user-b']);

    // Each user should have their own memory
    $allA = AgentMemory::all(['user_id' => 'user-a']);
    $allB = AgentMemory::all(['user_id' => 'user-b']);

    expect($allA)->toHaveCount(1)
        ->and($allB)->toHaveCount(1);
});

// ──────────────────────────────────────────────────────────────────
// Per-Context Limits & Pruning Tests
// ──────────────────────────────────────────────────────────────────

test('enforces per-context memory limit on store', function (): void {
    config(['memory.max_per_context' => 3, 'memory.dedup_threshold' => 1.0]);
    AgentMemory::fake();

    for ($i = 1; $i <= 5; $i++) {
        AgentMemory::store("Memory $i", ['user_id' => 'user-limit']);
    }

    $all = AgentMemory::all(['user_id' => 'user-limit']);
    expect($all)->toHaveCount(3);
});

test('prune removes expired memories', function (): void {
    AgentMemory::fake();

    AgentMemory::store('Expired', ['user_id' => 'user-prune'], now()->subDay());
    AgentMemory::store('Active', ['user_id' => 'user-prune']);

    $pruned = AgentMemory::prune(['user_id' => 'user-prune']);

    expect($pruned)->toBe(1);

    $all = AgentMemory::all(['user_id' => 'user-prune']);
    expect($all)->toHaveCount(1)
        ->and($all->first()->content)->toBe('Active');
});

test('prune returns zero when nothing to prune', function (): void {
    AgentMemory::fake();

    AgentMemory::store('Active memory', ['user_id' => 'user-clean']);

    $pruned = AgentMemory::prune(['user_id' => 'user-clean']);

    expect($pruned)->toBe(0);
});

// ──────────────────────────────────────────────────────────────────
// Batch Operations Tests
// ──────────────────────────────────────────────────────────────────

test('storeMany stores multiple memories', function (): void {
    config(['memory.dedup_threshold' => 1.0]);
    AgentMemory::fake();

    $memories = AgentMemory::storeMany([
        'First memory',
        'Second memory',
        'Third memory',
    ], ['user_id' => 'user-batch']);

    expect($memories)->toHaveCount(3);

    $all = AgentMemory::all(['user_id' => 'user-batch']);
    expect($all)->toHaveCount(3);
});

test('storeMany supports array items with type and ttl', function (): void {
    config(['memory.dedup_threshold' => 1.0]);
    AgentMemory::fake();

    $ttl = now()->addWeek();

    $memories = AgentMemory::storeMany([
        ['content' => 'Typed memory', 'type' => 'preference'],
        ['content' => 'TTL memory', 'ttl' => $ttl],
        'Simple string memory',
    ], ['user_id' => 'user-batch']);

    expect($memories)->toHaveCount(3)
        ->and($memories[0]->type)->toBe('preference')
        ->and($memories[1]->expires_at)->not->toBeNull()
        ->and($memories[2]->type)->toBeNull();
});

// ──────────────────────────────────────────────────────────────────
// Events Tests
// ──────────────────────────────────────────────────────────────────

test('MemoryStored event is dispatched on store', function (): void {
    AgentMemory::fake();
    Event::fake(MemoryStored::class);

    AgentMemory::store('Test memory', ['user_id' => 'user-events']);

    Event::assertDispatched(MemoryStored::class, function ($event) {
        return $event->memory->content === 'Test memory';
    });
});

test('MemoryRecalled event is dispatched on recall', function (): void {
    AgentMemory::fake();
    Event::fake(MemoryRecalled::class);

    AgentMemory::store('Stored memory', ['user_id' => 'user-events']);
    AgentMemory::recall('query', ['user_id' => 'user-events']);

    Event::assertDispatched(MemoryRecalled::class, function ($event) {
        return $event->query === 'query';
    });
});

test('MemoryForgotten event is dispatched on forget', function (): void {
    AgentMemory::fake();
    Event::fake(MemoryForgotten::class);

    $memory = AgentMemory::store('To forget', ['user_id' => 'user-events']);
    AgentMemory::forget($memory->id);

    Event::assertDispatched(MemoryForgotten::class, function ($event) use ($memory) {
        return $event->memoryId === $memory->id;
    });
});

test('MemoryDeduplicated event is dispatched when dedup triggers', function (): void {
    AgentMemory::fake();
    Event::fake(MemoryDeduplicated::class);

    AgentMemory::store('Original content', ['user_id' => 'user-events']);
    AgentMemory::store('Updated content', ['user_id' => 'user-events']);

    Event::assertDispatched(MemoryDeduplicated::class, function ($event) {
        return $event->newContent === 'Updated content';
    });
});

test('MemoryExpired event is dispatched during prune', function (): void {
    AgentMemory::fake();
    Event::fake(MemoryExpired::class);

    AgentMemory::store('Expired', ['user_id' => 'user-events'], now()->subDay());
    AgentMemory::prune(['user_id' => 'user-events']);

    Event::assertDispatched(MemoryExpired::class, function ($event) {
        return $event->count === 1;
    });
});

test('memories are isolated between users in full flow', function (): void {
    AgentMemory::fake();

    // Store for different users
    AgentMemory::store('Alice likes cats', ['user_id' => 'alice']);
    AgentMemory::store('Bob likes dogs', ['user_id' => 'bob']);

    // Alice should only see her memories
    $aliceMemories = AgentMemory::recall('pets', ['user_id' => 'alice']);
    expect($aliceMemories)->toHaveCount(1)
        ->and($aliceMemories->first()->content)->toBe('Alice likes cats');

    // Bob should only see his memories
    $bobMemories = AgentMemory::recall('pets', ['user_id' => 'bob']);
    expect($bobMemories)->toHaveCount(1)
        ->and($bobMemories->first()->content)->toBe('Bob likes dogs');
});
