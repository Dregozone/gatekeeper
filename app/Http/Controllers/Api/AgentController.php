<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgentPromptRequest;
use Illuminate\Http\JsonResponse;
use Laravel\Ai\Contracts\Agent;

class AgentController extends Controller
{
    /**
     * Resolve the agent class from the route parameter, prompt it, and return a structured JSON response.
     *
     * Route:  POST /api/agents/{agent}
     * Body:   { "message": "...", "conversation_id": "<uuid>" (optional) }
     *
     * The {agent} segment is case-insensitive and must match a class in App\Agents\,
     * e.g. "nvidia" resolves to App\Agents\NvidiaAgent.
     */
    public function prompt(AgentPromptRequest $request, string $agent): JsonResponse
    {
        $agentClass = 'App\\Agents\\'.ucfirst(strtolower($agent)).'Agent';

        if (! class_exists($agentClass) || ! in_array(Agent::class, class_implements($agentClass) ?: [])) {
            return response()->json([
                'error' => 'Agent not found.',
                'available_agents' => $this->availableAgents(),
            ], 404);
        }

        $user = $request->user();
        $instance = new $agentClass;

        $conversationId = $request->input('conversation_id');

        if ($conversationId && method_exists($instance, 'continue')) {
            $instance->continue($conversationId, $user);
        } elseif (method_exists($instance, 'forUser')) {
            $instance->forUser($user);
        }

        $response = $instance->prompt($request->input('message'));

        return response()->json([
            'agent' => str($agentClass)->classBasename()->before('Agent')->toString(),
            'model' => $response->meta->model,
            'message' => $response->text,
            'conversation_id' => $response->conversationId,
            'usage' => [
                'prompt_tokens' => $response->usage->promptTokens,
                'completion_tokens' => $response->usage->completionTokens,
                'total_tokens' => $response->usage->promptTokens + $response->usage->completionTokens,
            ],
        ]);
    }

    /**
     * Return a list of all available agent slugs.
     *
     * Route: GET /api/agents
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'agents' => $this->availableAgents(),
        ]);
    }

    /**
     * Discover all agent class names under App\Agents\ and return their URL slugs.
     *
     * @return array<int, string>
     */
    protected function availableAgents(): array
    {
        return collect(glob(app_path('Agents/*.php')) ?: [])
            ->map(fn (string $file) => 'App\\Agents\\'.basename($file, '.php'))
            ->filter(fn (string $class) => class_exists($class) && in_array(Agent::class, class_implements($class) ?: []))
            ->map(fn (string $class) => strtolower(str($class)->classBasename()->before('Agent')->toString()))
            ->values()
            ->toArray();
    }
}
