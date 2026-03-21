<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Concerns\HasMemories;
use Eznix86\AI\Memory\Contracts\Memorable;
use Eznix86\AI\Memory\Facades\AgentMemory;
use Eznix86\AI\Memory\MemoryProxy;

test('model implementing Memorable can store and recall memories', function (): void {
    AgentMemory::fake();

    $user = new class implements Memorable
    {
        use HasMemories;

        public string $id = 'user-memorable';

        public function memoryContext(): array
        {
            return ['user_id' => $this->id];
        }
    };

    $memory = $user->memories()->store('User prefers dark mode');

    expect($memory->content)->toBe('User prefers dark mode')
        ->and($memory->user_id)->toBe('user-memorable');

    $recalled = $user->memories()->recall('dark mode');

    expect($recalled)->toHaveCount(1)
        ->and($recalled->first()->content)->toBe('User prefers dark mode');
});

test('memories() returns a MemoryProxy', function (): void {
    $user = new class implements Memorable
    {
        use HasMemories;

        public function memoryContext(): array
        {
            return ['user_id' => 'test'];
        }
    };

    expect($user->memories())->toBeInstanceOf(MemoryProxy::class);
});

test('Memorable can forget memories', function (): void {
    AgentMemory::fake();

    $user = new class implements Memorable
    {
        use HasMemories;

        public function memoryContext(): array
        {
            return ['user_id' => 'user-forget-test'];
        }
    };

    $memory = $user->memories()->store('To be forgotten');
    $user->memories()->forget($memory->id);

    $recalled = $user->memories()->recall('forgotten');

    expect($recalled)->toBeEmpty();
});

test('Memorable can forgetAll memories', function (): void {
    config(['memory.dedup_threshold' => 1.0]);
    AgentMemory::fake();

    $user = new class implements Memorable
    {
        use HasMemories;

        public function memoryContext(): array
        {
            return ['user_id' => 'user-forgetall'];
        }
    };

    $user->memories()->store('Memory 1');
    $user->memories()->store('Memory 2');

    $deleted = $user->memories()->forgetAll();

    expect($deleted)->toBe(2);
});

test('Memorable contexts are isolated', function (): void {
    AgentMemory::fake();

    $alice = new class implements Memorable
    {
        use HasMemories;

        public function memoryContext(): array
        {
            return ['user_id' => 'alice-memorable'];
        }
    };

    $bob = new class implements Memorable
    {
        use HasMemories;

        public function memoryContext(): array
        {
            return ['user_id' => 'bob-memorable'];
        }
    };

    $alice->memories()->store('Alice secret');
    $bob->memories()->store('Bob secret');

    $aliceMemories = $alice->memories()->recall('secret');
    $bobMemories = $bob->memories()->recall('secret');

    expect($aliceMemories)->toHaveCount(1)
        ->and($aliceMemories->first()->content)->toBe('Alice secret')
        ->and($bobMemories)->toHaveCount(1)
        ->and($bobMemories->first()->content)->toBe('Bob secret');
});

test('Memorable can store with type and TTL', function (): void {
    AgentMemory::fake();

    $user = new class implements Memorable
    {
        use HasMemories;

        public function memoryContext(): array
        {
            return ['user_id' => 'user-typed'];
        }
    };

    $memory = $user->memories()->store('Temp preference', now()->addWeek(), 'preference');

    expect($memory->type)->toBe('preference')
        ->and($memory->expires_at)->not->toBeNull();
});

test('Memorable can get all memories', function (): void {
    config(['memory.dedup_threshold' => 1.0]);
    AgentMemory::fake();

    $user = new class implements Memorable
    {
        use HasMemories;

        public function memoryContext(): array
        {
            return ['user_id' => 'user-all'];
        }
    };

    $user->memories()->store('Memory 1');
    $user->memories()->store('Memory 2');

    $all = $user->memories()->all();

    expect($all)->toHaveCount(2);
});

test('Memorable can storeMany', function (): void {
    config(['memory.dedup_threshold' => 1.0]);
    AgentMemory::fake();

    $user = new class implements Memorable
    {
        use HasMemories;

        public function memoryContext(): array
        {
            return ['user_id' => 'user-batch-memorable'];
        }
    };

    $memories = $user->memories()->storeMany([
        'First memory',
        'Second memory',
    ]);

    expect($memories)->toHaveCount(2);
});
