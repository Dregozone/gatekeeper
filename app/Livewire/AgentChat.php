<?php

namespace App\Livewire;

use App\Contracts\GeneratesImages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    /** @var bool Whether this agent generates images instead of text. */
    #[Locked]
    public bool $isImageAgent = false;

    /**
     * The conversation message history for display.
     *
     * @var array<int, array{role: string, content: string, type?: string}>
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
        $this->isImageAgent = is_subclass_of($agentClass, GeneratesImages::class);

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
                ->get(['role', 'content', 'meta'])
                ->map(function ($m) {
                    $meta = json_decode($m->meta, true) ?: [];
                    $message = ['role' => $m->role, 'content' => $m->content];

                    if (isset($meta['type'])) {
                        $message['type'] = $meta['type'];
                    }

                    return $message;
                })
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

        if ($this->isImageAgent) {
            $this->generateImage();

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

    /**
     * Generate an image using the agent, store it, and persist the conversation.
     */
    protected function generateImage(): void
    {
        $agent = new $this->agentClass;

        $imageData = $agent->generateImage($this->pendingMessage);

        $imageUrl = $this->storeGeneratedImage($imageData);

        $this->persistImageConversation($this->pendingMessage, $imageUrl);

        $this->messages[] = [
            'role' => 'assistant',
            'content' => $imageUrl,
            'type' => 'image',
        ];

        $this->isStreaming = false;
        $this->pendingMessage = '';
    }

    /**
     * Store an image to the public disk and return its URL.
     *
     * Handles base64 data URIs, raw base64 strings, and remote URLs.
     * Falls back to treating the data as a direct URL if no other format matches.
     */
    protected function storeGeneratedImage(string $imageData): string
    {
        // Base64 data URI: data:image/png;base64,...
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/s', $imageData, $matches)) {
            $extension = $matches[1] === 'jpeg' ? 'jpg' : $matches[1];

            return $this->storeImageToDisk(
                base64_decode($matches[2]),
                $extension,
            );
        }

        // Remote URL: download and store locally so it persists
        if (filter_var($imageData, FILTER_VALIDATE_URL)) {
            return $imageData;
        }

        // Raw base64 without data URI prefix
        $decoded = base64_decode($imageData, true);

        if ($decoded !== false && strlen($decoded) > 100) {
            return $this->storeImageToDisk($decoded, 'png');
        }

        return $imageData;
    }

    /**
     * Write raw image bytes to the public disk and return the URL.
     */
    protected function storeImageToDisk(string $bytes, string $extension): string
    {
        $filename = 'generated-images/'.Str::random(40).'.'.$extension;

        Storage::disk('public')->put($filename, $bytes);

        return Storage::disk('public')->url($filename);
    }

    /**
     * Manually persist the image conversation to the database.
     *
     * The RemembersConversations middleware only works with the text streaming
     * pipeline, so image conversations are stored directly.
     */
    protected function persistImageConversation(string $prompt, string $imageUrl): void
    {
        $userId = auth()->id();
        $now = now();

        if (! $this->conversationId) {
            $this->conversationId = (string) Str::uuid();

            DB::table('agent_conversations')->insert([
                'id' => $this->conversationId,
                'user_id' => $userId,
                'title' => Str::limit($prompt, 100),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('agent_conversations')
                ->where('id', $this->conversationId)
                ->update(['updated_at' => $now]);
        }

        $baseMessage = [
            'conversation_id' => $this->conversationId,
            'user_id' => $userId,
            'agent' => $this->agentClass,
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '{}',
        ];

        DB::table('agent_conversation_messages')->insert([
            ...$baseMessage,
            'id' => (string) Str::uuid(),
            'role' => 'user',
            'content' => $prompt,
            'meta' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('agent_conversation_messages')->insert([
            ...$baseMessage,
            'id' => (string) Str::uuid(),
            'role' => 'assistant',
            'content' => $imageUrl,
            'meta' => json_encode(['type' => 'image']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
