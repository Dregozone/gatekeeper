<?php

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Laravel\Ai\Streaming\Events\TextDelta;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AgentChat extends Component
{
    /** @var string The fully-qualified class name of the agent. */
    #[Locked]
    public string $agentClass = '';

    /** @var string The display-friendly name derived from the agent class. */
    #[Locked]
    public string $agentName = '';

    /** @var string|null The active conversation UUID persisted across requests. */
    #[Locked]
    public ?string $conversationId = null;

    /**
     * The conversation message history for display.
     *
     * @var array<int, array{role: string, content: string}>
     */
    #[Locked]
    public array $messages = [];

    /** @var string The user's current draft message. */
    public string $input = '';

    /** @var string The message being processed, persisted between the send and stream requests. */
    #[Locked]
    public string $pendingMessage = '';

    /** @var bool Whether a streaming response is currently in progress. */
    #[Locked]
    public bool $isStreaming = false;

    /** @var string The accumulated streaming text, replaced with final message on completion. */
    #[Locked]
    public string $currentResponse = '';

    /**
     * Mount the component, resolve the agent name, and load any existing conversation history.
     */
    public function mount(string $agentClass): void
    {
        $this->agentClass = $agentClass;
        $this->agentName = str($agentClass)->classBasename()->before('Agent')->toString();

        $user = auth()->user();

        $this->conversationId = DB::table('agent_conversations as ac')
            ->join('agent_conversation_messages as acm', 'ac.id', '=', 'acm.conversation_id')
            ->where('ac.user_id', $user->id)
            ->where('acm.agent', $agentClass)
            ->orderByDesc('ac.updated_at')
            ->value('ac.id');

        if ($this->conversationId) {
            $this->messages = DB::table('agent_conversation_messages')
                ->where('conversation_id', $this->conversationId)
                ->orderBy('created_at')
                ->get(['role', 'content'])
                ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
                ->toArray();
        }
    }

    /**
     * Validate the input, optimistically append the user message, and trigger streaming.
     */
    public function sendMessage(): void
    {
        $this->validate(['input' => 'required|string|max:10000']);

        if ($this->isStreaming) {
            return;
        }

        $this->pendingMessage = $this->input;
        $this->messages[] = ['role' => 'user', 'content' => $this->input];
        $this->input = '';
        $this->isStreaming = true;
        $this->currentResponse = '';

        $this->js('$wire.streamResponse()');
    }

    /**
     * Stream the AI agent's response back to the browser chunk by chunk.
     *
     * Called client-side via $this->js() after sendMessage() completes its
     * initial request, allowing wire:stream to push deltas in real-time.
     */
    public function streamResponse(): void
    {
        if (! $this->pendingMessage || ! $this->isStreaming) {
            return;
        }

        $user = auth()->user();
        $pendingMessage = $this->pendingMessage;

        $agent = new $this->agentClass;

        if ($this->conversationId && method_exists($agent, 'continue')) {
            $agent->continue($this->conversationId, $user);
        } elseif (method_exists($agent, 'forUser')) {
            $agent->forUser($user);
        }

        $stream = $agent->stream($pendingMessage);

        foreach ($stream as $event) {
            if ($event instanceof TextDelta) {
                $this->stream(to: 'response', content: $event->delta);
                $this->currentResponse .= $event->delta;
            }
        }

        // After foreach, the RememberConversation middleware thenCallbacks have fired,
        // so conversationId is now populated on the stream response for new conversations.
        if (! $this->conversationId && $stream->conversationId) {
            $this->conversationId = $stream->conversationId;
        }

        $this->messages[] = ['role' => 'assistant', 'content' => $this->currentResponse];
        $this->currentResponse = '';
        $this->isStreaming = false;
        $this->pendingMessage = '';
    }
}
