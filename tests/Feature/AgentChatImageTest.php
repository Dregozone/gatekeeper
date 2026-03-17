<?php

use App\Agents\ArceeAgent;
use App\Agents\FluxKleinAgent;
use App\Livewire\AgentChat;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('image agent mounts with isImageAgent true', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(AgentChat::class, ['agentClass' => FluxKleinAgent::class]);

    $component
        ->assertSet('isImageAgent', true)
        ->assertSet('agentName', 'FluxKlein')
        ->assertSet('messages', []);
});

test('text agent mounts with isImageAgent false', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(AgentChat::class, ['agentClass' => ArceeAgent::class]);

    $component->assertSet('isImageAgent', false);
});

test('image agent generates image from url response and persists conversation', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'images' => [
                        [
                            'image_url' => [
                                'url' => 'https://cdn.openrouter.ai/generated/image-123.png',
                            ],
                        ],
                    ],
                ],
            ]],
        ]),
    ]);

    $component = Livewire::test(AgentChat::class, ['agentClass' => FluxKleinAgent::class])
        ->set('input', 'a cat sitting on a rainbow')
        ->call('sendMessage')
        ->call('streamResponse');

    $component
        ->assertSet('isStreaming', false)
        ->assertSet('pendingMessage', '');

    $messages = $component->get('messages');

    expect($messages)->toHaveCount(2);
    expect($messages[0])->toMatchArray(['role' => 'user', 'content' => 'a cat sitting on a rainbow']);
    expect($messages[1]['role'])->toBe('assistant');
    expect($messages[1]['type'])->toBe('image');
    expect($messages[1]['content'])->toBe('https://cdn.openrouter.ai/generated/image-123.png');

    // Verify conversation was persisted in the database
    $conversationId = $component->get('conversationId');
    expect($conversationId)->not->toBeNull();

    $this->assertDatabaseHas('agent_conversations', [
        'id' => $conversationId,
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseHas('agent_conversation_messages', [
        'conversation_id' => $conversationId,
        'role' => 'user',
        'content' => 'a cat sitting on a rainbow',
    ]);

    $this->assertDatabaseHas('agent_conversation_messages', [
        'conversation_id' => $conversationId,
        'role' => 'assistant',
        'content' => 'https://cdn.openrouter.ai/generated/image-123.png',
    ]);
});

test('image agent sends modalities parameter in request', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'images' => [
                        [
                            'image_url' => [
                                'url' => 'https://cdn.openrouter.ai/generated/image-456.png',
                            ],
                        ],
                    ],
                ],
            ]],
        ]),
    ]);

    Livewire::test(AgentChat::class, ['agentClass' => FluxKleinAgent::class])
        ->set('input', 'a sunset over mountains')
        ->call('sendMessage')
        ->call('streamResponse');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && data_get($request->data(), 'modalities') === ['image']
            && data_get($request->data(), 'model') === 'black-forest-labs/flux.2-klein-4b';
    });
});

test('image agent falls back to content extraction when images key is absent', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => 'https://cdn.openrouter.ai/generated/fallback.png',
                ],
            ]],
        ]),
    ]);

    $component = Livewire::test(AgentChat::class, ['agentClass' => FluxKleinAgent::class])
        ->set('input', 'a sunset over mountains')
        ->call('sendMessage')
        ->call('streamResponse');

    $messages = $component->get('messages');

    expect($messages[1]['content'])->toBe('https://cdn.openrouter.ai/generated/fallback.png');
    expect($messages[1]['type'])->toBe('image');
});

test('image agent stores base64 data uri to disk', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $this->actingAs($user);

    $base64 = base64_encode(str_repeat('x', 200));
    $dataUri = "data:image/png;base64,{$base64}";

    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'images' => [
                        [
                            'image_url' => [
                                'url' => $dataUri,
                            ],
                        ],
                    ],
                ],
            ]],
        ]),
    ]);

    $component = Livewire::test(AgentChat::class, ['agentClass' => FluxKleinAgent::class])
        ->set('input', 'a dog')
        ->call('sendMessage')
        ->call('streamResponse');

    $messages = $component->get('messages');

    expect($messages[1]['type'])->toBe('image');

    // Verify the image was stored to the public disk
    $storedFiles = Storage::disk('public')->allFiles('generated-images');
    expect($storedFiles)->toHaveCount(1);
    expect($storedFiles[0])->toEndWith('.png');
});

test('image agent conversation persists across page refresh', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'images' => [
                        [
                            'image_url' => [
                                'url' => 'https://cdn.example.com/image.png',
                            ],
                        ],
                    ],
                ],
            ]],
        ]),
    ]);

    // First request: generate the image
    $component = Livewire::test(AgentChat::class, ['agentClass' => FluxKleinAgent::class])
        ->set('input', 'a landscape')
        ->call('sendMessage')
        ->call('streamResponse');

    $conversationId = $component->get('conversationId');
    expect($conversationId)->not->toBeNull();

    // Simulate page refresh by mounting a new component
    $refreshed = Livewire::test(AgentChat::class, ['agentClass' => FluxKleinAgent::class]);

    $refreshed->assertSet('conversationId', $conversationId);

    $messages = $refreshed->get('messages');
    expect($messages)->toHaveCount(2);
    expect($messages[0])->toMatchArray(['role' => 'user', 'content' => 'a landscape']);
    expect($messages[1]['role'])->toBe('assistant');
    expect($messages[1]['type'])->toBe('image');
    expect($messages[1]['content'])->toBe('https://cdn.example.com/image.png');
});

test('image agent send message sets streaming state and clears input', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(AgentChat::class, ['agentClass' => FluxKleinAgent::class])
        ->set('input', 'generate a landscape')
        ->call('sendMessage');

    $component
        ->assertSet('isStreaming', true)
        ->assertSet('input', '')
        ->assertSet('pendingMessage', 'generate a landscape');

    $messages = $component->get('messages');
    expect($messages)->toHaveCount(1);
    expect($messages[0])->toMatchArray(['role' => 'user', 'content' => 'generate a landscape']);
});

test('image agent validates input is required', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $component = Livewire::test(AgentChat::class, ['agentClass' => FluxKleinAgent::class])
        ->set('input', '')
        ->call('sendMessage');

    $component->assertHasErrors(['input' => 'required']);
});
