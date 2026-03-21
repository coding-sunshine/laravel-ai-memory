<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Facades\AgentMemory;
use Eznix86\AI\Memory\Middleware\WithMemory;
use Eznix86\AI\Memory\Tools\ForgetMemory;
use Eznix86\AI\Memory\Tools\RecallMemory;
use Eznix86\AI\Memory\Tools\StoreMemory;
use Eznix86\AI\Memory\Tools\UpdateMemory;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Tools\Request;
use Tests\Fixtures\MemoryAgent;

test('StoreMemory stores a memory and returns confirmation', function (): void {
    AgentMemory::fake();

    $tool = (new StoreMemory)->context(['user_id' => 'user-123']);
    $result = $tool->handle(new Request(['content' => 'User prefers dark mode']));

    expect($result)->toContain('Memory stored successfully')
        ->and($result)->toContain('ID:');

    $this->assertDatabaseHas('memories', [
        'user_id' => 'user-123',
        'content' => 'User prefers dark mode',
    ]);
});

test('StoreMemory stores memory without user context', function (): void {
    AgentMemory::fake();

    $tool = new StoreMemory;
    $result = $tool->handle(new Request(['content' => 'Global fact']));

    expect($result)->toContain('Memory stored successfully');

    $this->assertDatabaseHas('memories', [
        'user_id' => null,
        'content' => 'Global fact',
    ]);
});

test('StoreMemory has correct schema', function (): void {
    $tool = new StoreMemory;
    $schema = $tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('content');
});

test('StoreMemory has a description', function (): void {
    $tool = new StoreMemory;

    expect((string) $tool->description())->not->toBeEmpty();
});

test('RecallMemory recalls relevant memories', function (): void {
    AgentMemory::fake();

    // Pre-store some memories
    AgentMemory::store('User prefers dark mode', ['user_id' => 'user-123']);
    AgentMemory::store('User likes TypeScript', ['user_id' => 'user-123']);

    $tool = (new RecallMemory)->context(['user_id' => 'user-123']);
    $result = $tool->handle(new Request(['query' => 'What does the user prefer?']));

    expect($result)->toContain('User prefers dark mode')
        ->and($result)->toContain('User likes TypeScript');
});

test('RecallMemory returns message when no memories found', function (): void {
    AgentMemory::fake();

    $tool = (new RecallMemory)->context(['user_id' => 'nonexistent']);
    $result = $tool->handle(new Request(['query' => 'anything']));

    expect($result)->toBe('No relevant memories found.');
});

test('RecallMemory respects limit', function (): void {
    AgentMemory::fake();

    // Store 5 memories
    for ($i = 1; $i <= 5; $i++) {
        AgentMemory::store("Memory $i", ['user_id' => 'user-123']);
    }

    $tool = (new RecallMemory)->context(['user_id' => 'user-123'])->limit(2);
    $result = $tool->handle(new Request(['query' => 'memories']));

    // Count the bullet points (each memory is "- content")
    $lines = array_filter(explode("\n", $result), fn ($line): bool => str_starts_with((string) $line, '- '));
    expect(count($lines))->toBe(2);
});

test('RecallMemory has correct schema', function (): void {
    $tool = new RecallMemory;
    $schema = $tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('query');
});

test('RecallMemory has a description', function (): void {
    $tool = new RecallMemory;

    expect((string) $tool->description())->not->toBeEmpty();
});

test('WithMemory middleware prepends memories to prompt', function (): void {
    AgentMemory::fake();

    // Pre-store memories
    AgentMemory::store('User prefers dark mode', ['user_id' => 'user-123']);

    // The dynamic fake closure receives the final prompt string (after middleware)
    $receivedPrompt = null;
    MemoryAgent::fake(function (string $prompt) use (&$receivedPrompt): string {
        $receivedPrompt = $prompt;

        return 'Got it!';
    });

    $agent = new MemoryAgent(['user_id' => 'user-123']);
    $agent->prompt('Help me with my settings');

    expect($receivedPrompt)
        ->toContain('Relevant memories from previous conversations')
        ->toContain('User prefers dark mode')
        ->toContain('Help me with my settings');
});

test('WithMemory middleware passes through when no user context', function (): void {
    AgentMemory::fake();

    $receivedPrompt = null;
    MemoryAgent::fake(function (string $prompt) use (&$receivedPrompt): string {
        $receivedPrompt = $prompt;

        return 'Hello!';
    });

    $agent = new MemoryAgent;
    $agent->prompt('Hello world');

    // Prompt should NOT contain injected memories
    expect($receivedPrompt)
        ->toContain('Hello world')
        ->not->toContain('Relevant memories');
});

test('WithMemory middleware passes through when no memories exist', function (): void {
    AgentMemory::fake();

    $receivedPrompt = null;
    MemoryAgent::fake(function (string $prompt) use (&$receivedPrompt): string {
        $receivedPrompt = $prompt;

        return 'Hello!';
    });

    $agent = new MemoryAgent(['user_id' => 'user-no-memories']);
    $agent->prompt('Hello world');

    // Prompt should NOT contain injected memories
    expect($receivedPrompt)
        ->toContain('Hello world')
        ->not->toContain('Relevant memories');
});

test('MemoryAgent can be faked and prompted', function (): void {
    AgentMemory::fake();

    MemoryAgent::fake([
        'I remember you prefer dark mode!',
    ]);

    $agent = new MemoryAgent(['user_id' => 'user-123']);
    $response = $agent->prompt('What do I prefer?');

    expect($response->text)->toBe('I remember you prefer dark mode!');

    MemoryAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('What do I prefer?'));
});

test('MemoryAgent has memory tools configured', function (): void {
    $agent = new MemoryAgent(['user_id' => 'user-123']);
    $tools = iterator_to_array($agent->tools());

    expect($tools)->toHaveCount(2)
        ->and($tools[0])->toBeInstanceOf(RecallMemory::class)
        ->and($tools[1])->toBeInstanceOf(StoreMemory::class);
});

test('MemoryAgent has WithMemory middleware', function (): void {
    $agent = new MemoryAgent(['user_id' => 'user-123']);
    $middleware = $agent->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(WithMemory::class);
});

test('MemoryAgent fake with dynamic response', function (): void {
    AgentMemory::fake();

    MemoryAgent::fake(fn (string $prompt): string => "You asked: {$prompt}");

    $agent = new MemoryAgent(['user_id' => 'user-123']);
    $response = $agent->prompt('Remember my name is Bruno');

    expect($response->text)->toContain('You asked:')
        ->and($response->text)->toContain('Remember my name is Bruno');
});

test('MemoryAgent assertNotPrompted works', function (): void {
    AgentMemory::fake();

    MemoryAgent::fake();

    $agent = new MemoryAgent(['user_id' => 'user-123']);
    $agent->prompt('Hello');

    MemoryAgent::assertNotPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Goodbye'));
});

test('StoreMemory and RecallMemory work together', function (): void {
    AgentMemory::fake();

    // Store via tool
    $storeTool = (new StoreMemory)->context(['user_id' => 'user-flow']);
    $storeResult = $storeTool->handle(new Request(['content' => 'User prefers dark mode']));

    expect($storeResult)->toContain('Memory stored successfully');

    // Recall via tool
    $recallTool = (new RecallMemory)->context(['user_id' => 'user-flow']);
    $recallResult = $recallTool->handle(new Request(['query' => 'preferences']));

    expect($recallResult)->toContain('User prefers dark mode');
});

test('WithMemory middleware uses stored memories from tools', function (): void {
    AgentMemory::fake();

    // Store a memory using the tool
    $storeTool = (new StoreMemory)->context(['user_id' => 'user-mid']);
    $storeTool->handle(new Request(['content' => 'User lives in Mauritius']));

    // The dynamic fake closure receives the final prompt string (after middleware)
    $receivedPrompt = null;
    MemoryAgent::fake(function (string $prompt) use (&$receivedPrompt): string {
        $receivedPrompt = $prompt;

        return 'Mauritius it is!';
    });

    $agent = new MemoryAgent(['user_id' => 'user-mid']);
    $agent->prompt('Where does the user live?');

    expect($receivedPrompt)
        ->toContain('Relevant memories from previous conversations')
        ->toContain('User lives in Mauritius')
        ->toContain('Where does the user live?');
});

test('RecallMemory returns empty message when user has no memories', function (): void {
    AgentMemory::fake();

    $tool = (new RecallMemory)->context(['user_id' => 'user-empty']);
    $result = $tool->handle(new Request(['query' => 'anything']));

    expect($result)->toBe('No relevant memories found.');
});

test('tools respect user isolation', function (): void {
    AgentMemory::fake();

    // Store memories for different users via tools
    $storeAlice = (new StoreMemory)->context(['user_id' => 'alice']);
    $storeAlice->handle(new Request(['content' => 'Alice secret data']));

    $storeBob = (new StoreMemory)->context(['user_id' => 'bob']);
    $storeBob->handle(new Request(['content' => 'Bob secret data']));

    // Bob should NOT see Alice's memories
    $recallBob = (new RecallMemory)->context(['user_id' => 'bob']);
    $result = $recallBob->handle(new Request(['query' => 'secret']));

    expect($result)->toContain('Bob secret data')
        ->and($result)->not->toContain('Alice secret data');
});

// ──────────────────────────────────────────────────────────────────
// UpdateMemory Tool Tests
// ──────────────────────────────────────────────────────────────────

test('UpdateMemory updates an existing memory', function (): void {
    AgentMemory::fake();

    $memory = AgentMemory::store('Original content', ['user_id' => 'user-update']);

    $tool = (new UpdateMemory)->context(['user_id' => 'user-update']);
    $result = $tool->handle(new Request([
        'memory_id' => $memory->id,
        'content' => 'Updated content',
    ]));

    expect($result)->toContain('Memory updated successfully')
        ->and($result)->toContain("ID: {$memory->id}");

    $this->assertDatabaseHas('memories', [
        'id' => $memory->id,
        'content' => 'Updated content',
    ]);
});

test('UpdateMemory returns not found for invalid ID', function (): void {
    $tool = new UpdateMemory;
    $result = $tool->handle(new Request([
        'memory_id' => 99999,
        'content' => 'Something',
    ]));

    expect($result)->toBe('Memory not found.');
});

test('UpdateMemory has correct schema', function (): void {
    $tool = new UpdateMemory;
    $schema = $tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('memory_id')
        ->and($schema)->toHaveKey('content');
});

test('UpdateMemory has a description', function (): void {
    $tool = new UpdateMemory;

    expect((string) $tool->description())->not->toBeEmpty();
});

// ──────────────────────────────────────────────────────────────────
// ForgetMemory Tool Tests
// ──────────────────────────────────────────────────────────────────

test('ForgetMemory deletes an existing memory', function (): void {
    AgentMemory::fake();

    $memory = AgentMemory::store('To be forgotten', ['user_id' => 'user-forget']);

    $tool = (new ForgetMemory)->context(['user_id' => 'user-forget']);
    $result = $tool->handle(new Request(['memory_id' => $memory->id]));

    expect($result)->toBe('Memory forgotten successfully.');
    $this->assertDatabaseMissing('memories', ['id' => $memory->id]);
});

test('ForgetMemory returns not found for invalid ID', function (): void {
    $tool = new ForgetMemory;
    $result = $tool->handle(new Request(['memory_id' => 99999]));

    expect($result)->toBe('Memory not found.');
});

test('ForgetMemory has correct schema', function (): void {
    $tool = new ForgetMemory;
    $schema = $tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('memory_id');
});

test('ForgetMemory has a description', function (): void {
    $tool = new ForgetMemory;

    expect((string) $tool->description())->not->toBeEmpty();
});

// ──────────────────────────────────────────────────────────────────
// StoreMemory with Type Tests
// ──────────────────────────────────────────────────────────────────

test('StoreMemory stores memory with type', function (): void {
    AgentMemory::fake();

    $tool = (new StoreMemory)->context(['user_id' => 'user-type']);
    $result = $tool->handle(new Request([
        'content' => 'Prefers dark mode',
        'type' => 'preference',
    ]));

    expect($result)->toContain('Memory stored successfully');

    $this->assertDatabaseHas('memories', [
        'user_id' => 'user-type',
        'content' => 'Prefers dark mode',
        'type' => 'preference',
    ]);
});

test('StoreMemory schema includes type field', function (): void {
    $tool = new StoreMemory;
    $schema = $tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('content')
        ->and($schema)->toHaveKey('type');
});

// ──────────────────────────────────────────────────────────────────
// RecallMemory with Type Tests
// ──────────────────────────────────────────────────────────────────

test('RecallMemory filters by type', function (): void {
    AgentMemory::fake();

    AgentMemory::store('Prefers dark mode', ['user_id' => 'user-type'], type: 'preference');
    AgentMemory::store('The sky is blue', ['user_id' => 'user-type'], type: 'fact');

    $tool = (new RecallMemory)->context(['user_id' => 'user-type']);
    $result = $tool->handle(new Request([
        'query' => 'mode',
        'type' => 'preference',
    ]));

    expect($result)->toContain('Prefers dark mode')
        ->and($result)->not->toContain('The sky is blue');
});

test('RecallMemory schema includes type field', function (): void {
    $tool = new RecallMemory;
    $schema = $tool->schema(new JsonSchemaTypeFactory);

    expect($schema)->toHaveKey('query')
        ->and($schema)->toHaveKey('type');
});
