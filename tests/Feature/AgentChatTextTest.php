<?php

use App\Agents\ArceeAgent;
use App\Livewire\AgentChat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Enums\MessageStatus;
use Livewire\Livewire;

test('text agent streams a reply and remembers the conversation across page refresh', function () {
    ArceeAgent::fake(['Hello from Arcee']);

    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(AgentChat::class, ['agentClass' => ArceeAgent::class])
        ->set('input', 'Hi there')
        ->call('sendMessage')
        ->call('streamResponse')
        ->assertSet('isStreaming', false);

    $conversationId = $component->get('conversationId');

    expect($conversationId)->not->toBeNull();
    expect($component->get('messages')[1])->toMatchArray(['role' => 'assistant', 'content' => 'Hello from Arcee']);

    $this->assertDatabaseHas('agent_conversations', [
        'id' => $conversationId,
        'participant_type' => $user->getMorphClass(),
        'participant_id' => $user->id,
    ]);

    $refreshed = Livewire::test(AgentChat::class, ['agentClass' => ArceeAgent::class]);

    $refreshed->assertSet('conversationId', $conversationId);
    expect($refreshed->get('messages'))->toBe([
        ['role' => 'user', 'content' => 'Hi there'],
        ['role' => 'assistant', 'content' => 'Hello from Arcee'],
    ]);
});

test('failed assistant turns are not shown in the chat history', function () {
    ArceeAgent::fake(['Hello from Arcee']);

    $user = User::factory()->create();
    $this->actingAs($user);

    $conversationId = Livewire::test(AgentChat::class, ['agentClass' => ArceeAgent::class])
        ->set('input', 'Hi there')
        ->call('sendMessage')
        ->call('streamResponse')
        ->get('conversationId');

    DB::table('agent_conversation_messages')
        ->where('conversation_id', $conversationId)
        ->where('role', 'assistant')
        ->update(['status' => MessageStatus::Failed->value]);

    $messages = Livewire::test(AgentChat::class, ['agentClass' => ArceeAgent::class])->get('messages');

    expect($messages)->toBe([['role' => 'user', 'content' => 'Hi there']]);
});
