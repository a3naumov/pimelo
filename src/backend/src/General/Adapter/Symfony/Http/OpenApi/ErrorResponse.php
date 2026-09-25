<?php

declare(strict_types=1);

namespace App\General\Adapter\Symfony\Http\OpenApi;

use App\General\Adapter\Symfony\Http\OpenApi\Model\Error;
use App\General\Adapter\Symfony\Http\OpenApi\Model\Problem;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use OpenApi\Generator;

final class ErrorResponse extends OA\Response
{
    public function __construct(int $response, ?string $description = null, ?string $example = null)
    {
        $description ??= match ($response) {
            400 => 'Malformed JSON.',
            404 => 'Resource not found. A malformed UUID is rejected by routing with a Symfony error response.',
            415 => 'The request body must use application/json.',
            422 => 'Missing or invalid request properties.',
            500 => 'Unexpected failure. Details are logged and are not included in the response.',
            default => 'The request could not be completed.',
        };

        $content = 404 === $response
            ? new OA\JsonContent(oneOf: [
                new OA\Schema(ref: new Model(type: Error::class)),
                new OA\Schema(ref: new Model(type: Problem::class)),
            ])
            : new OA\JsonContent(ref: new Model(type: \in_array($response, [400, 415, 422], true) ? Problem::class : Error::class));

        $content->example = null !== $example
            ? ['error' => $example]
            : (500 === $response ? ['error' => 'Internal server error.'] : Generator::UNDEFINED);

        parent::__construct(response: $response, description: $description, content: $content);
    }
}
