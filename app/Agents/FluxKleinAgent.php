<?php

namespace App\Agents;

use App\Contracts\GeneratesImages;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::OpenRouter)]
#[Model('black-forest-labs/flux.2-klein-4b')]
#[Timeout(120)]
class FluxKleinAgent implements Agent, GeneratesImages
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return 'Generate an image based on the user\'s text prompt.';
    }

    /**
     * Generate an image via a direct HTTP call to OpenRouter.
     *
     * The standard Prism text handler cannot parse multimodal image
     * responses, so we bypass it and call the API directly.
     *
     * FLUX models require 'modalities' => ['image'] to generate images.
     * The image data is returned under choices.0.message.images rather
     * than the standard choices.0.message.content path.
     */
    public function generateImage(string $prompt): string
    {
        $response = Http::withToken(config('ai.providers.openrouter.key'))
            ->timeout(120)
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => 'black-forest-labs/flux.2-klein-4b',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'modalities' => ['image'],
            ])
            ->throw();

        $data = $response->json();

        // FLUX returns images under choices.0.message.images
        $imageUrl = data_get($data, 'choices.0.message.images.0.image_url.url');

        if ($imageUrl) {
            return $imageUrl;
        }

        // Fallback: check content in case the response format varies
        return $this->extractImageData(
            data_get($data, 'choices.0.message.content')
        );
    }

    /**
     * Extract the image URL or data URI from the API response content.
     *
     * OpenRouter image models may return content as a multimodal array
     * or as a plain string containing a URL or data URI.
     */
    protected function extractImageData(mixed $content): string
    {
        if (is_array($content)) {
            foreach ($content as $part) {
                $type = data_get($part, 'type');

                if ($type === 'image_url') {
                    return data_get($part, 'image_url.url', '');
                }

                if ($type === 'image') {
                    return data_get($part, 'image', '');
                }
            }

            return '';
        }

        if (is_string($content)) {
            return trim($content);
        }

        return '';
    }
}
