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
#[Model('arcee-ai/trinity-large-preview:free')]
class ArceeAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function instructions(): Stringable|string
    {
        return 'You are Arcee, a helpful assistant powered by Arcee AI Trinity Large Preview.';
    }

    /** @return Tool[] */
    public function tools(): iterable
    {
        return [];
    }
}
