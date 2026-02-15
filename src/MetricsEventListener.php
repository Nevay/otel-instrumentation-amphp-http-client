<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient;

use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Connection\Connection;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\EventListener;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Socket\InternetAddress;
use Amp\Socket\UnixAddress;
use Composer\InstalledVersions;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\API\Metrics\UpDownCounterInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Contrib\Instrumentation\HttpConfig\HttpConfig;
use Throwable;
use function hrtime;
use function in_array;

/**
 * Generates HTTP client request metrics.
 *
 * @see TracingEventListener
 * @see https://opentelemetry.io/docs/specs/semconv/http/http-metrics/
 */
final class MetricsEventListener implements EventListener {

    private const START_OFFSET = '__otel.timing.start';

    private readonly HistogramInterface $requestDuration;
    private readonly HistogramInterface $requestBodySize;
    private readonly HistogramInterface $responseBodySize;
    private readonly HistogramInterface $connectionDuration;
    private readonly UpDownCounterInterface $activeRequests;

    /**
     * @param list<string> $knownHttpMethods case-sensitive list of known http methods
     */
    public function __construct(
        MeterProviderInterface $meterProvider,
        private readonly array $knownHttpMethods = HttpConfig::HTTP_METHODS,
    ) {
        $meter = $meterProvider->getMeter(
            'com.tobiasbachert.instrumentation.amphp-http-client',
            InstalledVersions::getPrettyVersion('tbachert/otel-instrumentation-amphp-http-client'),
            'https://opentelemetry.io/schemas/1.39.0',
        );

        $this->requestDuration = $meter->createHistogram(
            name: 'http.client.request.duration',
            unit: 's',
            description: 'Duration of HTTP client requests',
            advisory: [
                'Attributes' => ['http.request.method', 'server.address', 'server.port', 'error.type', 'http.response.status_code', 'network.protocol.name', 'network.protocol.version'],
                'ExplicitBucketBoundaries' => [0.005, 0.01, 0.025, 0.05, 0.075, 0.1, 0.25, 0.5, 0.75, 1, 2.5, 5, 7.5, 10],
            ],
        );
        $this->requestBodySize = $meter->createHistogram(
            name: 'http.client.request.body.size',
            unit: 'By',
            description: 'Size of HTTP client request bodies',
            advisory: [
                'Attributes' => ['http.request.method', 'server.address', 'server.port', 'error.type', 'http.response.status_code', 'network.protocol.name', 'url.template', 'network.protocol.version'],
            ],
        );
        $this->responseBodySize = $meter->createHistogram(
            name: 'http.client.response.body.size',
            unit: 'By',
            description: 'Size of HTTP client response bodies',
            advisory: [
                'Attributes' => ['http.request.method', 'server.address', 'server.port', 'error.type', 'http.response.status_code', 'network.protocol.name', 'url.template', 'network.protocol.version'],
            ],
        );
        $this->connectionDuration = $meter->createHistogram(
            name: 'http.client.connection.duration',
            unit: 's',
            description: 'The duration of the successfully established outbound HTTP connections',
            advisory: [
                'Attributes' => ['server.address', 'server.port', 'network.peer.address', 'network.protocol.version'],
                'ExplicitBucketBoundaries' => [0.01, 0.02, 0.05, 0.1, 0.2, 0.5, 1, 2, 5, 10, 30, 60, 120, 300],
            ],
        );
        $this->activeRequests = $meter->createUpDownCounter(
            name: 'http.client.active_requests',
            unit: '{requests}',
            description: 'Number of active HTTP requests',
            advisory: [
                'Attributes' => ['server.address', 'server.port', 'url.template', 'http.request.method'],
            ],
        );
    }

    private function requestAttributes(Request $request): array {
        $method = $request->getMethod();
        if (!in_array($method, $this->knownHttpMethods, true)) {
            $method = null;
        }

        $attributes = [
            'http.request.method' => $method ?? '_OTHER',
            'server.address' => $request->getUri()->getHost(),
            'server.port' => $request->getUri()->getPort() ?? match ($request->getUri()->getScheme()) {
                'https' => 443,
                'http' => 80,
                default => null,
            },
            'url.scheme' => $request->getUri()->getScheme(),
        ];

        return $attributes;
    }

    private function requestDuration(Request $request): ?float {
        if (!$request->hasAttribute(self::START_OFFSET)) {
            return null;
        }

        $startOffset = $request->getAttribute(self::START_OFFSET);
        $endOffset = hrtime(true);
        $request->removeAttribute(self::START_OFFSET);

        return ($endOffset - $startOffset) / 1e9;
    }

    public function requestStart(Request $request): void {
        // no-op
    }

    public function requestFailed(Request $request, Throwable $exception): void {
        if (($duration = $this->requestDuration($request)) === null) {
            return;
        }

        $attributes = $this->requestAttributes($request);
        $this->activeRequests->add(-1, $attributes);

        $attributes['error.type'] = $exception::class;

        $context = $this->requestContext($request);
        $this->requestDuration->record($duration, $attributes, $context);

        if ($request->hasHeader('content-length')) {
            $this->requestBodySize->record(+$request->getHeader('content-length'), $attributes, $context);
        }
    }

    public function requestEnd(Request $request, Response $response): void {
        // no-op
    }

    public function requestRejected(Request $request): void {
        // no-op
    }

    public function applicationInterceptorStart(Request $request, ApplicationInterceptor $interceptor): void {
        // no-op
    }

    public function applicationInterceptorEnd(Request $request, ApplicationInterceptor $interceptor, Response $response): void {
        // no-op
    }

    public function networkInterceptorStart(Request $request, NetworkInterceptor $interceptor): void {
        // no-op
    }

    public function networkInterceptorEnd(Request $request, NetworkInterceptor $interceptor, Response $response): void {
        // no-op
    }

    public function connectionAcquired(Request $request, Connection $connection, int $streamCount): void {
        if ($streamCount !== 1) {
            return;
        }

        $attributes = [
            'server.address' => $request->getUri()->getHost(),
            'server.port' => $request->getUri()->getPort() ?? match ($request->getUri()->getScheme()) {
                'https' => 443,
                'http' => 80,
                default => null,
            },
            'network.peer.address' => null,
            'network.protocol.version' => null,
            'url.scheme' => $request->getUri()->getScheme(),
        ];

        $remoteAddress = $connection->getRemoteAddress();
        if ($remoteAddress instanceof InternetAddress) {
            $attributes['network.peer.address'] = $remoteAddress->getAddress();
        }
        if ($remoteAddress instanceof UnixAddress) {
            $attributes['network.peer.address'] = $remoteAddress->toString();
        }

        $context = $this->requestContext($request);
        $this->connectionDuration->record($connection->getConnectDuration(), $attributes, $context);
    }

    public function push(Request $request): void {
        // no-op
    }

    public function requestHeaderStart(Request $request, Stream $stream): void {
        $request->setAttribute(self::START_OFFSET, hrtime(true));

        $attributes = $this->requestAttributes($request);
        $this->activeRequests->add(1, $attributes);
    }

    public function requestHeaderEnd(Request $request, Stream $stream): void {
        // no-op
    }

    public function requestBodyStart(Request $request, Stream $stream): void {
        // no-op
    }

    public function requestBodyProgress(Request $request, Stream $stream): void {
        // no-op
    }

    public function requestBodyEnd(Request $request, Stream $stream): void {
        // no-op
    }

    public function responseHeaderStart(Request $request, Stream $stream): void {
        // no-op
    }

    public function responseHeaderEnd(Request $request, Stream $stream, Response $response): void {
        if (($duration = $this->requestDuration($request)) === null) {
            return;
        }

        $attributes = $this->requestAttributes($request);
        $this->activeRequests->add(-1, $attributes);

        $attributes['http.response.status_code'] = $response->getStatus();
        $attributes['network.protocol.version'] = $response->getProtocolVersion();
        if ($response->getStatus() >= 400) {
            $attributes['error.type'] = (string) $response->getStatus();
        }

        $context = $this->requestContext($request);
        $this->requestDuration->record($duration, $attributes, $context);

        if ($request->hasHeader('content-length')) {
            $this->requestBodySize->record(+$request->getHeader('content-length'), $attributes, $context);
        }
        if ($response->hasHeader('content-length')) {
            $this->responseBodySize->record(+$response->getHeader('content-length'), $attributes, $context);
        }
    }

    public function responseBodyStart(Request $request, Stream $stream, Response $response): void {
        // no-op
    }

    public function responseBodyProgress(Request $request, Stream $stream, Response $response): void {
        // no-op
    }

    public function responseBodyEnd(Request $request, Stream $stream, Response $response): void {
        // no-op
    }

    private function requestContext(Request $request): ContextInterface|false {
        if (!$request->hasAttribute(ContextInterface::class)) {
            return false;
        }

        return $request->getAttribute(ContextInterface::class);
    }
}
