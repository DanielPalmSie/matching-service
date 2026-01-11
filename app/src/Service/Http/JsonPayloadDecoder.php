<?php

declare(strict_types=1);

namespace App\Service\Http;

use App\Service\Exception\ValidationException;
use Symfony\Component\HttpFoundation\Request;

class JsonPayloadDecoder
{
    /**
     * @return array<string, mixed>
     */
    public function decode(Request $request, string $errorMessage = 'Invalid JSON body.'): array
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            throw new ValidationException($errorMessage);
        }

        return $payload;
    }
}
