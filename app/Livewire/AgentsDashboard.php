<?php

namespace App\Livewire;

use Laravel\Ai\Contracts\Agent;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('AI Agents')]
class AgentsDashboard extends Component
{
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
}
