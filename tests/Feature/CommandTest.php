<?php

declare(strict_types=1);

use Eznix86\AI\Memory\Facades\AgentMemory;

test('memory:prune removes expired memories', function (): void {
    AgentMemory::fake();

    AgentMemory::store('Expired', ['user_id' => 'user-cmd'], now()->subDay());
    AgentMemory::store('Active', ['user_id' => 'user-cmd']);

    $this->artisan('memory:prune', ['--context' => ['user_id:user-cmd']])
        ->expectsOutputToContain('Pruned 1 memory')
        ->assertSuccessful();

    $all = AgentMemory::all(['user_id' => 'user-cmd']);
    expect($all)->toHaveCount(1)
        ->and($all->first()->content)->toBe('Active');
});

test('memory:prune dry-run does not delete', function (): void {
    AgentMemory::fake();

    AgentMemory::store('Expired', ['user_id' => 'user-dry'], now()->subDay());

    $this->artisan('memory:prune', ['--context' => ['user_id:user-dry'], '--dry-run' => true])
        ->expectsOutputToContain('Dry run mode')
        ->assertSuccessful();

    // Memory should still exist
    $this->assertDatabaseHas('memories', ['user_id' => 'user-dry']);
});

test('memory:prune with no expired memories reports zero', function (): void {
    AgentMemory::fake();

    AgentMemory::store('Active', ['user_id' => 'user-clean-cmd']);

    $this->artisan('memory:prune', ['--context' => ['user_id:user-clean-cmd']])
        ->expectsOutputToContain('Pruned 0 memories')
        ->assertSuccessful();
});

test('memory:stats displays statistics', function (): void {
    AgentMemory::fake();

    AgentMemory::store('Memory 1', ['user_id' => 'user-stats']);
    AgentMemory::store('Memory 2', ['user_id' => 'user-stats'], now()->subDay()); // expired

    $this->artisan('memory:stats', ['--context' => ['user_id:user-stats']])
        ->expectsOutputToContain('Memory Statistics')
        ->assertSuccessful();
});

test('memory:export outputs JSON', function (): void {
    config(['memory.dedup_threshold' => 1.0]);
    AgentMemory::fake();

    AgentMemory::store('Export me', ['user_id' => 'user-export']);

    $this->artisan('memory:export', ['--context' => ['user_id:user-export']])
        ->expectsOutputToContain('Export me')
        ->assertSuccessful();
});

test('memory:export writes to file', function (): void {
    config(['memory.dedup_threshold' => 1.0]);
    AgentMemory::fake();

    AgentMemory::store('File export', ['user_id' => 'user-file-export']);

    $outputPath = sys_get_temp_dir().'/memory-export-test.json';

    $this->artisan('memory:export', [
        '--context' => ['user_id:user-file-export'],
        '--output' => $outputPath,
    ])->expectsOutputToContain('Exported 1 memories')
        ->assertSuccessful();

    expect(file_exists($outputPath))->toBeTrue();

    $data = json_decode(file_get_contents($outputPath), true);
    expect($data)->toHaveCount(1)
        ->and($data[0]['content'])->toBe('File export');

    unlink($outputPath);
});
