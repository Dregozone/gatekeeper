<div>
    {{-- Chat windows area — height reduced to leave room for the floating toolbar --}}
    <div class="flex h-[calc(100dvh-13.5rem)] w-full flex-col overflow-hidden lg:h-[calc(100dvh-11rem)]">
        @if (count($this->selectedAgents) > 0)
            <div @class([
                'grid min-h-0 flex-1 gap-4',
                'grid-cols-1' => count($this->selectedAgents) === 1,
                'grid-cols-1 md:grid-cols-2' => count($this->selectedAgents) === 2 || count($this->selectedAgents) === 4,
                'grid-cols-2 lg:grid-cols-3' => count($this->selectedAgents) === 3,
            ])>
                @foreach ($this->selectedAgents as $agentClass)
                    <livewire:agent-chat :agentClass="$agentClass" :key="$agentClass" />
                @endforeach
            </div>
        @elseif (count($this->agents) === 0)
            <div class="flex h-full items-center justify-center">
                <flux:text class="text-zinc-400">No agents configured yet. Add an agent class to <code>app/Agents/</code> to get started.</flux:text>
            </div>
        @else
            <div class="flex h-full select-none flex-col items-center justify-center gap-4 text-center">
                <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-zinc-100 dark:bg-zinc-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-zinc-400 dark:text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 0 1 .778-.332 48.294 48.294 0 0 0 5.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-semibold text-zinc-600 dark:text-zinc-400">No agents selected</p>
                    <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-600">Choose up to 4 agents using the toolbar below</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Floating glass toolbar — fixed at bottom-centre, always visible when agents exist --}}
    @if (count($this->agents) > 0)
        <div class="toolbar-enter pointer-events-none fixed inset-x-0 bottom-6 z-50 flex justify-center">
            <div class="pointer-events-auto">
                {{-- Glass container --}}
                <div
                    class="relative flex items-center gap-2.5 rounded-2xl border border-white/15 px-4 py-3 backdrop-blur-2xl backdrop-saturate-150"
                    style="background: rgba(22, 22, 26, 0.78); box-shadow: 0 12px 48px rgba(0,0,0,0.4), 0 2px 12px rgba(0,0,0,0.25), inset 0 1px 0 rgba(255,255,255,0.1), inset 0 -1px 0 rgba(0,0,0,0.2);"
                >
                    {{-- Top glass highlight shimmer --}}
                    <div class="pointer-events-none absolute inset-x-3 top-0 h-px bg-gradient-to-r from-transparent via-white/30 to-transparent"></div>

                    {{-- Agent buttons --}}
                    @foreach ($this->agentsMeta as $agent)
                        @php
                            $isSelected = $agent['selected'];
                            $isDisabled = ! $isSelected && count($this->selectedAgents) >= 4;
                        @endphp

                        <button
                            wire:click="toggleAgent({{ $agent['index'] }})"
                            @disabled($isDisabled)
                            @class([
                                'group relative flex h-16 w-16 shrink-0 flex-col items-center justify-center gap-1.5 overflow-hidden rounded-xl text-white transition-all duration-200 ease-out',
                                'scale-[1.08] shadow-lg' => $isSelected,
                                'opacity-90 hover:scale-[1.05] hover:opacity-100 active:scale-95 cursor-pointer' => ! $isSelected && ! $isDisabled,
                                'cursor-not-allowed opacity-20 saturate-0' => $isDisabled,
                            ])
                            style="background-color: {{ $agent['color']['bg'] }};{{ $isSelected ? 'box-shadow: 0 0 0 2px rgba(255,255,255,0.7), 0 6px 28px rgba(' . $agent['color']['glow'] . ', 0.7), 0 2px 8px rgba(' . $agent['color']['glow'] . ', 0.4);' : '' }}"
                            title="{{ $agent['name'] }}"
                        >
                            {{-- Inner glass gradient when selected --}}
                            @if ($isSelected)
                                <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/25 via-white/5 to-transparent"></div>
                            @endif

                            {{-- Agent name --}}
                            <span class="relative z-10 break-words px-1 text-center text-[9px] font-bold leading-tight tracking-wide drop-shadow-sm">
                                {{ $agent['name'] }}
                            </span>

                            {{-- Selected checkmark / unselected ring --}}
                            <div class="relative z-10">
                                @if ($isSelected)
                                    <div class="flex h-3.5 w-3.5 items-center justify-center rounded-full bg-white shadow">
                                        <svg viewBox="0 0 10 10" class="h-2 w-2" fill="none">
                                            <path d="M2 5.5l2.5 2 3.5-4" stroke="{{ $agent['color']['bg'] }}" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                @else
                                    <div class="h-3 w-3 rounded-full border-2 border-white/40 bg-white/10"></div>
                                @endif
                            </div>
                        </button>
                    @endforeach

                    {{-- Divider --}}
                    <div class="mx-0.5 h-10 w-px self-center" style="background: rgba(255,255,255,0.12);"></div>

                    {{-- Selection counter --}}
                    <div class="flex min-w-[2.5rem] flex-col items-center justify-center gap-0.5">
                        <span class="text-xl font-bold tabular-nums leading-none" style="color: rgba(255,255,255,0.85);">
                            {{ count($this->selectedAgents) }}
                        </span>
                        <span class="text-[9px] font-semibold uppercase tracking-widest" style="color: rgba(255,255,255,0.35);">
                            / 4
                        </span>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

