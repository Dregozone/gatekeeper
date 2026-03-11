<div class="flex h-full w-full flex-col">
    @if (count($this->agents) > 0)
        <div class="grid min-h-0 flex-1 gap-4 md:grid-cols-2">
            @foreach ($this->agents as $agentClass)
                <livewire:agent-chat :agentClass="$agentClass" :key="$agentClass" />
            @endforeach
        </div>
    @else
        <div class="flex h-full items-center justify-center">
            <flux:text class="text-zinc-400">No agents configured yet. Add an agent class to <code>app/Agents/</code> to get started.</flux:text>
        </div>
    @endif
</div>
