<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;

final class ProxyRequestBody
{
    /**
     * @param array<string, list<string>> $headers
     *
     * @return string|iterable<string>
     */
    public function prepare(Request $request, array &$headers): string|iterable
    {
        $contentType = strtolower(explode(';', $request->headers->get('Content-Type', ''))[0]);
        $body = $request->getContent();

        if ('multipart/form-data' === $contentType) {
            // PHP/RoadRunner already parsed the upload; do not forward its internal JSON representation.
            if (0 === $request->request->count() && 0 === $request->files->count() && str_starts_with($body, '--')) {
                return $body;
            }

            $fields = [];
            $this->flatten($request->request->all(), '', $fields);
            $this->flatten($request->files->all(), '', $fields);
            $form = new FormDataPart($fields);
            $headers['content-type'] = [$form->getPreparedHeaders()->get('Content-Type')->getBodyAsString()];

            return $form->bodyToIterable();
        }

        if ('application/x-www-form-urlencoded' === $contentType
            && ('' === $body || is_array(json_decode($body, true)))
        ) {
            return http_build_query($request->request->all(), '', '&', PHP_QUERY_RFC1738);
        }

        return $body;
    }

    /**
     * @param array<array-key, mixed>        $values
     * @param array<string, string|DataPart> $fields
     */
    private function flatten(array $values, string $prefix, array &$fields): void
    {
        foreach ($values as $key => $value) {
            $name = '' === $prefix ? (string) $key : $prefix.'['.$key.']';

            if (is_array($value)) {
                $this->flatten($value, $name, $fields);
            } elseif ($value instanceof UploadedFile) {
                $fields[$name] = new DataPart(new File($value->getPathname()), $value->getClientOriginalName(), $value->getClientMimeType());
            } elseif (is_scalar($value) || null === $value) {
                $fields[$name] = (string) $value;
            }
        }
    }
}
