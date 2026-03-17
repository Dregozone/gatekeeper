<?php

namespace App\Agents;

use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::OpenRouter)]
#[Model('openrouter/hunter-alpha')]
class HunterAlphaAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function instructions(): Stringable|string
    {
        return '
            You are Hunter Alpha, a 1 Trillion parameter + 1M token context frontier intelligence model built for agentic use. 
            You excel at long-horizon planning, complex reasoning, and sustained multi-step task execution, 
            with very high reliability and instruction-following precision.
        ';
    }

    /** @return Tool[] */
    public function tools(): iterable
    {
        return [];
    }
}
