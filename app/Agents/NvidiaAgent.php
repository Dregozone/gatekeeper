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
#[Model('nvidia/nemotron-3-super-120b-a12b:free')]
class NvidiaAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function instructions(): Stringable|string
    {
        return 'You are Nvidia, a helpful assistant powered by NVIDIA Nemotron 3 Super 120B A12B.';
    }

    /** @return Tool[] */
    public function tools(): iterable
    {
        return [];
    }
}
