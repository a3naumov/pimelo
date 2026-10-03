<?php

declare(strict_types=1);

namespace App\Http;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ServiceProxy
{
    /**
     * @param array<string, string> $upstreams
     */
    public function __construct(
        private HttpClientInterface $client,
        private ProxyHeaders $headers,
        private ProxyRequestBody $body,
        private LoggerInterface $logger,
        private array $upstreams,
        private float $timeout,
        private float $maxDuration,
    ) {
    }

    public function forward(Request $request): Response
    {
        // Preserve raw escaping, duplicate query parameters and their order.
        $uri = $request->getRequestUri();
        preg_match('#^/([^/?]+)([^?]*)(.*)$#s', $uri, $matches);
        $service = $matches[1] ?? '';
        $origin = $this->upstreams[$service] ?? null;

        if (null === $origin) {
            return new JsonResponse(['error' => 'Unknown service.'], Response::HTTP_NOT_FOUND);
        }

        $origin = rtrim($origin, '/');
        $path = ('' === $matches[2] ? '/' : $matches[2]).$matches[3];
        $headers = $this->headers->filter($request->headers->all());
        // Explicit encoding prevents transparent decompression and mismatched response metadata.
        $headers['accept-encoding'] = ['identity'];
        $headers['x-forwarded-for'] = [$request->getClientIp() ?? ''];
        $headers['x-forwarded-proto'] = [$request->getScheme()];
        $headers['x-forwarded-host'] = [$request->getHttpHost()];
        $headers['x-forwarded-prefix'] = ['/'.$service];
        $upstream = null;

        try {
            $body = $this->body->prepare($request, $headers);
            $upstream = $this->client->request($request->getMethod(), $origin.$path, [
                'headers' => $headers,
                'body' => $body,
                'buffer' => false,
                'max_redirects' => 0,
                'timeout' => $this->timeout,
                'max_duration' => $this->maxDuration,
                'extra' => ['trace_content' => false],
            ]);
            $status = $upstream->getStatusCode();
            $responseHeaders = $this->headers->filter($upstream->getHeaders(false));

            if (isset($responseHeaders['location'])) {
                $responseHeaders['location'] = array_map(
                    fn (string $location): string => $this->headers->location($location, $origin, $path, $request->getSchemeAndHttpHost(), '/'.$service),
                    $responseHeaders['location'],
                );
            }
        } catch (TransportExceptionInterface $exception) {
            $upstream?->cancel();
            $this->logger->error('Upstream request failed.', ['service' => $service, 'exception' => $exception]);

            return new JsonResponse(
                ['error' => $exception instanceof TimeoutExceptionInterface ? 'Upstream timed out.' : 'Upstream unavailable.'],
                $exception instanceof TimeoutExceptionInterface ? Response::HTTP_GATEWAY_TIMEOUT : Response::HTTP_BAD_GATEWAY,
            );
        }

        if ($request->isMethod('HEAD') || in_array($status, [204, 304], true)) {
            $upstream->cancel();

            return new Response('', $status, $responseHeaders);
        }

        return new StreamedResponse(function () use ($upstream, $service): void {
            try {
                foreach ($this->client->stream($upstream) as $chunk) {
                    echo $chunk->getContent();
                }
            } catch (TransportExceptionInterface $exception) {
                // Headers may already be sent. Never append an error document to a partial download.
                $this->logger->error('Upstream response stream interrupted.', ['service' => $service, 'exception' => $exception]);
            } finally {
                $upstream->cancel();
            }
        }, $status, $responseHeaders);
    }
}
