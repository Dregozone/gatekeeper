<?php

namespace App\Contracts;

interface GeneratesImages
{
    /**
     * Generate an image from the given text prompt.
     *
     * @return string The image data (URL or base64 data URI).
     */
    public function generateImage(string $prompt): string;
}
