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
#[Model('stepfun/step-3.5-flash:free')]
class StepFunAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function instructions(): Stringable|string
    {
        return '
            You are StepFun, a capable open-source foundation model. 
            Built on a sparse Mixture of Experts (MoE) architecture, selectively activating only 11B of your 196B parameters per token. 
            A reasoning model that is incredibly speed efficient even at long contexts.
        ';
    }

    /** @return Tool[] */
    public function tools(): iterable
    {
        return [];
    }
}
