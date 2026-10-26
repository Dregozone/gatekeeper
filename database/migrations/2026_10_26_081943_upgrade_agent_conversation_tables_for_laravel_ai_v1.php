<?php

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Enums\MessageStatus;
use Laravel\Ai\Migrations\AiMigration;

/**
 * Bring the conversation tables created by laravel/ai 0.2 up to the 1.x schema.
 *
 * Combines the upgrade guide's "Polymorphic Conversation Participants" (0.10) and
 * "Conversation Messages Now Store Steps" (1.0) migrations. The 0.10 `approval_state`
 * column is skipped because 1.0 replaces it with `status`.
 */
return new class extends AiMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $conversationsTable = config('ai.conversations.tables.conversations', 'agent_conversations');
        $messagesTable = config('ai.conversations.tables.messages', 'agent_conversation_messages');
        $participantType = (new User)->getMorphClass();

        Schema::connection($this->getConnection())->table($conversationsTable, function (Blueprint $table) {
            $table->dropIndex(['user_id', 'updated_at']);
            $table->renameColumn('user_id', 'participant_id');
            $table->string('participant_type')->nullable()->after('id');
        });

        Schema::connection($this->getConnection())->table($messagesTable, function (Blueprint $table) {
            $table->dropIndex('conversation_index');
            $table->dropIndex(['user_id']);
            $table->renameColumn('user_id', 'participant_id');
            $table->string('participant_type')->nullable()->after('conversation_id');
            $table->longText('steps')->nullable();
            $table->string('status', 25)->default(MessageStatus::Completed->value);
        });

        $this->query($conversationsTable)->whereNotNull('participant_id')->update(['participant_type' => $participantType]);
        $this->query($messagesTable)->whereNotNull('participant_id')->update(['participant_type' => $participantType]);
        $this->query($messagesTable)->where('role', 'user')->update(['steps' => '[]']);

        $this->query($messagesTable)
            ->select('conversation_id')
            ->distinct()
            ->orderBy('conversation_id')
            ->chunk(100, function (Collection $conversations) use ($messagesTable) {
                foreach ($conversations as $conversation) {
                    $this->backfill($messagesTable, $conversation->conversation_id);
                }
            });

        $this->query($messagesTable)->whereNull('steps')->update(['steps' => '[]']);

        Schema::connection($this->getConnection())->table($conversationsTable, function (Blueprint $table) {
            $table->index(['participant_type', 'participant_id', 'updated_at'], 'participant_updated_at_index');
        });

        Schema::connection($this->getConnection())->table($messagesTable, function (Blueprint $table) {
            $table->longText('steps')->nullable(false)->change();
            $table->dropColumn(['tool_calls', 'tool_results']);
            $table->index(['conversation_id', 'participant_type', 'participant_id', 'updated_at'], 'conversation_index');
            $table->index(['participant_type', 'participant_id', 'agent'], 'participant_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Tool calls are restored as empty arrays, as the 0.2 columns cannot be rebuilt from `steps`.
     */
    public function down(): void
    {
        $conversationsTable = config('ai.conversations.tables.conversations', 'agent_conversations');
        $messagesTable = config('ai.conversations.tables.messages', 'agent_conversation_messages');

        Schema::connection($this->getConnection())->table($conversationsTable, function (Blueprint $table) {
            $table->dropIndex('participant_updated_at_index');
            $table->dropColumn('participant_type');
            $table->renameColumn('participant_id', 'user_id');
        });

        Schema::connection($this->getConnection())->table($messagesTable, function (Blueprint $table) {
            $table->dropIndex('conversation_index');
            $table->dropIndex('participant_index');
            $table->dropColumn(['participant_type', 'steps', 'status']);
            $table->renameColumn('participant_id', 'user_id');
            $table->text('tool_calls')->default('[]');
            $table->text('tool_results')->default('[]');
        });

        Schema::connection($this->getConnection())->table($conversationsTable, function (Blueprint $table) {
            $table->index(['user_id', 'updated_at']);
        });

        Schema::connection($this->getConnection())->table($messagesTable, function (Blueprint $table) {
            $table->index(['conversation_id', 'user_id', 'updated_at'], 'conversation_index');
            $table->index(['user_id']);
        });
    }

    /**
     * Rewrite one conversation's assistant rows as steps, each result landing on the call that made it.
     */
    protected function backfill(string $table, string $conversationId): void
    {
        $rows = $this->query($table)
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->orderBy('id')
            ->get();

        $results = $rows->flatMap(fn (object $row) => $this->decoded($row->tool_results))->keyBy('id');

        foreach ($rows as $row) {
            $meta = $this->decoded($row->meta);

            $calls = collect($this->decoded($row->tool_calls))
                ->filter(fn (array $call) => $results->has($call['id'] ?? ''))
                ->map(fn (array $call) => [
                    ...$call,
                    'result' => $results[$call['id']]['result'] ?? null,
                    ...array_filter([
                        'denied' => $results[$call['id']]['denied'] ?? false,
                        'failed' => $results[$call['id']]['failed'] ?? false,
                    ]),
                ])
                ->values()
                ->all();

            $content = (string) $row->content;

            $steps = $calls !== [] && $content !== ''
                ? [$this->step('', $calls), $this->step($content, [], $meta['reasoning'] ?? '')]
                : [$this->step($content, $calls, $meta['reasoning'] ?? '')];

            unset($meta['provider_steps'], $meta['provider_content_blocks'], $meta['reasoning']);

            $this->query($table)->where('id', $row->id)->update([
                'steps' => json_encode($steps),
                'meta' => json_encode((object) $meta),
            ]);
        }
    }

    /**
     * Build a conversation step from its content, tool calls, and reasoning.
     *
     * @param  list<array<string, mixed>>  $calls
     * @return array{content: string, tool_calls: list<array<string, mixed>>, reasoning: string, replay_blocks: array<int, mixed>, provider_tool_calls: array<int, mixed>}
     */
    protected function step(string $content, array $calls = [], string $reasoning = ''): array
    {
        return [
            'content' => $content,
            'tool_calls' => $calls,
            'reasoning' => $reasoning,
            'replay_blocks' => [],
            'provider_tool_calls' => [],
        ];
    }

    /**
     * Decode a JSON value into an array, returning an empty array for invalid or empty input.
     *
     * @return array<array-key, mixed>
     */
    protected function decoded(?string $json): array
    {
        return is_array($decoded = json_decode($json ?? '', true)) ? $decoded : [];
    }

    /**
     * Create a query builder for the given table on the AI connection.
     */
    protected function query(string $table): Builder
    {
        return DB::connection($this->getConnection())->table($table);
    }
};
