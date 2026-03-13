<div
    class="flex h-full min-h-[500px] flex-col overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"
    x-data="{
        init() {
            this.scrollToBottom();
            const observer = new MutationObserver(() => this.scrollToBottom());
            observer.observe(this.$refs.messages, { childList: true, subtree: true, characterData: true });
        },
        scrollToBottom() {
            this.$nextTick(() => {
                this.$refs.messages.scrollTop = this.$refs.messages.scrollHeight;
            });
        },
    }"
>
    {{-- Header --}}
    <div class="flex shrink-0 items-center gap-3 border-b border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-700 dark:bg-zinc-800">
        <div class="flex size-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">
            {{ substr($agentName, 0, 1) }}
        </div>
        <flux:heading size="sm" class="font-semibold">{{ $agentName }}</flux:heading>
        @if ($isStreaming)
            <span class="ml-auto animate-pulse text-xs text-blue-500">Thinking…</span>
        @endif
    </div>

    {{-- Messages --}}
    <div
        x-ref="messages"
        class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4"
    >
        @forelse ($messages as $index => $message)
            @if ($message['role'] === 'user')
                <div class="flex justify-end" wire:key="msg-{{ $index }}">
                    <div class="max-w-[80%] rounded-2xl rounded-tr-sm bg-blue-600 px-4 py-2.5 text-sm text-white">
                        {{ $message['content'] }}
                    </div>
                </div>
            @else
                <div class="flex justify-start" wire:key="msg-{{ $index }}">
                    <div class="max-w-[80%] whitespace-pre-wrap rounded-2xl rounded-tl-sm bg-zinc-100 px-4 py-2.5 text-sm text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">
                        {{ $message['content'] }}
                    </div>
                </div>
            @endif
        @empty
            <div class="flex h-full items-center justify-center">
                <flux:text size="sm" class="text-zinc-400">Start a conversation with {{ $agentName }}</flux:text>
            </div>
        @endforelse

        @if ($isStreaming)
            <div class="flex justify-start">
                <div class="max-w-[80%] whitespace-pre-wrap rounded-2xl rounded-tl-sm bg-zinc-100 px-4 py-2.5 text-sm text-zinc-900 dark:bg-zinc-800 dark:text-zinc-100">
                    <span wire:stream="response">{{ $currentResponse }}</span><span class="animate-pulse opacity-70">▌</span>
                </div>
            </div>
        @endif
    </div>

    {{-- Input --}}
    <div class="shrink-0 border-t border-zinc-200 p-3 dark:border-zinc-700">
        <form wire:submit="sendMessage" class="flex items-end gap-2">
            <div class="flex-1">
                <flux:textarea
                    wire:model="input"
                    placeholder="Message {{ $agentName }}… (Shift+Enter for newline)"
                    rows="2"
                    class="resize-none"
                    x-on:keydown.enter="!$event.shiftKey && ($event.preventDefault(), $wire.sendMessage())"
                    :disabled="$isStreaming"
                />
            </div>
            <flux:button
                type="submit"
                variant="primary"
                icon="arrow-up"
                :disabled="$isStreaming"
            />
        </form>
    </div>
</div>
