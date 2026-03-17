<?php

use App\Agents\ArceeAgent;
use App\Livewire\AgentsDashboard;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('dashboard renders agent names in the toolbar', function () {
    Livewire::test(AgentsDashboard::class)
        ->assertSee('Arcee')
        ->assertSee('HunterAlpha');
});

test('no agents are selected by default', function () {
    Livewire::test(AgentsDashboard::class)
        ->assertSet('selectedAgents', []);
});

test('toggling an agent adds it to selectedAgents', function () {
    Livewire::test(AgentsDashboard::class)
        ->call('toggleAgent', 0)
        ->assertSet('selectedAgents', [ArceeAgent::class]);
});

test('toggling a selected agent removes it', function () {
    Livewire::test(AgentsDashboard::class)
        ->call('toggleAgent', 0)
        ->call('toggleAgent', 0)
        ->assertSet('selectedAgents', []);
});

test('cannot select more than four agents', function () {
    Livewire::test(AgentsDashboard::class)
        ->call('toggleAgent', 0)
        ->call('toggleAgent', 1)
        ->call('toggleAgent', 2)
        ->call('toggleAgent', 3)
        ->call('toggleAgent', 4)
        ->assertCount('selectedAgents', 4);
});

test('toggling an out-of-bounds index is a no-op', function () {
    Livewire::test(AgentsDashboard::class)
        ->call('toggleAgent', 999)
        ->assertSet('selectedAgents', []);
});

test('agentsMeta returns correct name and selection state', function () {
    $component = Livewire::test(AgentsDashboard::class)
        ->call('toggleAgent', 0);

    $meta = $component->instance()->agentsMeta;

    expect($meta[0]['name'])->toBe('Arcee');
    expect($meta[0]['selected'])->toBeTrue();
    expect($meta[1]['selected'])->toBeFalse();
});

test('agentsMeta assigns unique colours to each agent', function () {
    $meta = Livewire::test(AgentsDashboard::class)->instance()->agentsMeta;

    $uniqueBgs = array_unique(array_column(array_column($meta, 'color'), 'bg'));

    expect(count($uniqueBgs))->toBe(count($meta));
});
