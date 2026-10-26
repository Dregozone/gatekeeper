<?php

use App\Agents\ArceeAgent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('migration converts laravel ai 0.2 conversations to the 1.x schema', function () {
    $migration = require database_path('migrations/2026_10_26_081943_upgrade_agent_conversation_tables_for_laravel_ai_v1.php');
    $migration->down();

    $user = User::factory()->create();
    $now = now();

    DB::table('agent_conversations')->insert([
        'id' => 'conversation-1',
        'user_id' => $user->id,
        'title' => 'Hi there',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $legacyMessage = [
        'conversation_id' => 'conversation-1',
        'user_id' => $user->id,
        'agent' => ArceeAgent::class,
        'attachments' => '[]',
        'tool_calls' => '[]',
        'tool_results' => '[]',
        'usage' => '{}',
        'created_at' => $now,
        'updated_at' => $now,
    ];

    DB::table('agent_conversation_messages')->insert([
        [...$legacyMessage, 'id' => 'message-1', 'role' => 'user', 'content' => 'Hi there', 'meta' => '{}'],
        [...$legacyMessage, 'id' => 'message-2', 'role' => 'assistant', 'content' => 'Hello!', 'meta' => '{"reasoning":"Greet back."}'],
    ]);

    $migration->up();

    expect(Schema::hasColumns('agent_conversation_messages', ['participant_type', 'participant_id', 'steps', 'status']))->toBeTrue();
    expect(Schema::hasColumns('agent_conversation_messages', ['user_id', 'tool_calls', 'tool_results']))->toBeFalse();

    $this->assertDatabaseHas('agent_conversations', [
        'id' => 'conversation-1',
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);

    $assistant = DB::table('agent_conversation_messages')->where('id', 'message-2')->first();

    expect($assistant->participant_type)->toBe($user->getMorphClass());
    expect($assistant->status)->toBe('completed');
    expect(json_decode($assistant->meta, true))->toBe([]);
    expect(json_decode($assistant->steps, true))->toBe([[
        'content' => 'Hello!',
        'tool_calls' => [],
        'reasoning' => 'Greet back.',
        'replay_blocks' => [],
        'provider_tool_calls' => [],
    ]]);

    expect(DB::table('agent_conversation_messages')->where('id', 'message-1')->value('steps'))->toBe('[]');
});
