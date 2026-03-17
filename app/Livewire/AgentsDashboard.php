<?php

namespace App\Livewire;

use Laravel\Ai\Contracts\Agent;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('AI Agents')]
class AgentsDashboard extends Component
{
    /** @var array<int, class-string<Agent>> The currently visible agent class names, in selection order. */
    public array $selectedAgents = [];

    /**
     * Discover all agent classes registered in app/Agents/.
     *
     * Scans the directory for PHP files that implement the Agent contract,
     * so new agents are automatically surfaced without any registration step.
     *
     * @return array<int, class-string<Agent>>
     */
    #[Computed]
    public function agents(): array
    {
        return collect(glob(app_path('Agents/*.php')) ?: [])
            ->map(fn (string $file) => 'App\\Agents\\'.basename($file, '.php'))
            ->filter(function (string $class) {
                return class_exists($class)
                    && in_array(Agent::class, class_implements($class) ?: []);
            })
            ->values()
            ->toArray();
    }

    /**
     * Agents enriched with display metadata: name, colour palette entry, selection state, and index.
     *
     * @return array<int, array{index: int, class: class-string<Agent>, name: string, color: array{bg: string, glow: string}, selected: bool}>
     */
    #[Computed]
    public function agentsMeta(): array
    {
        $palette = [
            ['bg' => '#0ea5e9', 'glow' => '14, 165, 233'],   // Sky
            ['bg' => '#8b5cf6', 'glow' => '139, 92, 246'],   // Violet
            ['bg' => '#f59e0b', 'glow' => '245, 158, 11'],   // Amber
            ['bg' => '#10b981', 'glow' => '16, 185, 129'],   // Emerald
            ['bg' => '#f43f5e', 'glow' => '244, 63, 94'],    // Rose
        ];

        return collect($this->agents)
            ->map(fn (string $class, int $index) => [
                'index' => $index,
                'class' => $class,
                'name' => str($class)->classBasename()->before('Agent')->toString(),
                'color' => $palette[$index % count($palette)],
                'selected' => in_array($class, $this->selectedAgents),
            ])
            ->values()
            ->toArray();
    }

    /**
     * Toggle an agent window on or off.
     * Enforces a maximum of four concurrent chat windows.
     */
    public function toggleAgent(int $index): void
    {
        $agents = $this->agents;

        if (! array_key_exists($index, $agents)) {
            return;
        }

        $agentClass = $agents[$index];

        if (in_array($agentClass, $this->selectedAgents)) {
            $this->selectedAgents = array_values(
                array_filter($this->selectedAgents, fn (string $a) => $a !== $agentClass)
            );
        } elseif (count($this->selectedAgents) < 4) {
            $this->selectedAgents[] = $agentClass;
        }
    }
}
