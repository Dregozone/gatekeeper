<?php

use App\Agents\NvidiaAgent;
use App\Models\User;

test('prompting an agent returns its reply, conversation and token usage', function () {
    NvidiaAgent::fake(['Hello from Nvidia']);

    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson(route('api.agents.prompt', 'nvidia'), ['message' => 'Hi there'])
        ->assertOk()
        ->assertJsonPath('agent', 'Nvidia')
        ->assertJsonPath('message', 'Hello from Nvidia')
        ->assertJsonStructure([
            'model',
            'conversation_id',
            'usage' => ['prompt_tokens', 'completion_tokens', 'total_tokens'],
        ]);

    expect($response->json('conversation_id'))->not->toBeNull();
    expect($response->json('usage.total_tokens'))
        ->toBe($response->json('usage.prompt_tokens') + $response->json('usage.completion_tokens'));

    $this->assertDatabaseHas('agent_conversations', [
        'id' => $response->json('conversation_id'),
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);
});

test('prompting an unknown agent returns not found', function () {
    $this->postJson(route('api.agents.prompt', 'missing'), ['message' => 'Hi there'])
        ->assertNotFound()
        ->assertJsonPath('error', 'Agent not found.');
});
