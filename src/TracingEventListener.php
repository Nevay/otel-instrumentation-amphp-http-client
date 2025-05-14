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
use Nevay\OTelInstrumentation\AmphpHttpClient\Internal\HttpMessagePropagationSetter;
use Nevay\OTelInstrumentation\AmphpHttpClient\Internal\RequestSharedState;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use Throwable;
use function array_combine;
use function array_map;
use function assert;
use function in_array;
use function sprintf;
use function strtolower;

/**
 * Traces HTTP client requests.
 *
 * Spans start before the first request byte is sent and end after the HTTP response headers are fully read. They do not
 * include reading the response body.
 *
 * The client span and context will be available as attributes on processed requests:
 * ```
 * $response->getRequest()->getAttribute(SpanInterface::class);
 * $response->getRequest()->getAttribute(ContextInterface::class);
 * ```
 *
 * @see https://opentelemetry.io/docs/specs/semconv/http/http-spans/
 */
final class TracingEventListener implements EventListener {

    private readonly TracerInterface $tracer;
    private readonly array $captureRequestHeaders;
    private readonly array $captureResponseHeaders;

    /**
     * @param list<string> $captureRequestHeaders list of request headers to capture
     * @param list<string> $captureResponseHeaders list of response headers to capture
     * @param bool $captureUrlSchemeAttribute whether the `url.scheme` attribute should be captured
     * @param bool $captureUserAgentAttribute whether the `user_agent.original` attribute should be captured
     * @param list<string> $knownHttpMethods case-sensitive list of known http methods
     */
    public function __construct(
        TracerProviderInterface $tracerProvider,
        private readonly TextMapPropagatorInterface $propagator,
        array $captureRequestHeaders = [],
        array $captureResponseHeaders = [],
        private readonly bool $captureUrlSchemeAttribute = false,
        private readonly bool $captureUserAgentAttribute = false,
        private readonly array $knownHttpMethods = ['CONNECT', 'DELETE', 'GET', 'HEAD', 'OPTIONS', 'PATCH', 'POST', 'PUT', 'TRACE'],
    ) {
        $this->tracer = $tracerProvider->getTracer(
            'com.tobiasbachert.instrumentation.amphp-http-client',
            InstalledVersions::getPrettyVersion('tbachert/otel-instrumentation-amphp-http-client'),
            'https://opentelemetry.io/schemas/1.30.0',
        );
        $this->captureRequestHeaders = array_combine($captureRequestHeaders,
            array_map(static fn(string $header) => sprintf('http.request.header.%s', strtolower($header)), $captureRequestHeaders));
        $this->captureResponseHeaders = array_combine($captureResponseHeaders,
            array_map(static fn(string $header) => sprintf('http.response.header.%s', strtolower($header)), $captureResponseHeaders));
    }

    public function requestStart(Request $request): void {
        if (!$request->hasAttribute(RequestSharedState::class)) {
            $request->setAttribute(RequestSharedState::class, new RequestSharedState());
        }

        if ($request->hasAttribute(SpanInterface::class)) {
            $request->removeAttribute(SpanInterface::class);
        }
    }

    public function requestFailed(Request $request, Throwable $exception): void {
        if (!$request->hasAttribute(SpanInterface::class)) {
            return;
        }

        $span = $request->getAttribute(SpanInterface::class);
        assert($span instanceof SpanInterface);

        $span->recordException($exception, ['exception.escaped' => true]);
        $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        $span->setAttribute('error.type', $exception::class);
        $span->end();
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
        // no-op
    }

    public function push(Request $request): void {
        // no-op
    }

    public function requestHeaderStart(Request $request, Stream $stream): void {
        $method = $request->getMethod();
        if (!in_array($method, $this->knownHttpMethods, true)) {
            $method = null;
        }

        $spanBuilder = $this->tracer
            ->spanBuilder($method ?? 'HTTP')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('http.request.method', $method ?? '_OTHER')
            ->setAttribute('server.address', $request->getUri()->getHost())
            ->setAttribute('server.port', $request->getUri()->getPort() ?? match ($request->getUri()->getScheme()) {
                'https' => 443,
                'http' => 80,
                default => null,
            })
            ->setAttribute('url.full', $request->getUri()->withUserInfo('')->__toString())
        ;

        $sharedState = $request->getAttribute(RequestSharedState::class);
        assert($sharedState instanceof RequestSharedState);
        if (++$sharedState->resendCount) {
            $spanBuilder->setAttribute('http.request.resend_count', $sharedState->resendCount);
        }

        if ($method === null) {
            $spanBuilder->setAttribute('http.request.method_original', $request->getMethod());
        }
        if ($this->captureUrlSchemeAttribute) {
            $spanBuilder->setAttribute('url.scheme', $request->getUri()->getScheme());
        }
        if ($this->captureUserAgentAttribute) {
            $spanBuilder->setAttribute('user_agent.original', $request->getHeader('user-agent'));
        }
        foreach ($this->captureRequestHeaders as $header => $attribute) {
            if ($value = $request->getHeaderArray($header)) {
                $spanBuilder->setAttribute($attribute, $value);
            }
        }

        $localAddress = $stream->getLocalAddress();
        if ($localAddress instanceof InternetAddress) {
            $spanBuilder->setAttribute('network.local.address', $localAddress->getAddress());
            $spanBuilder->setAttribute('network.local.port', $localAddress->getPort());
        }
        if ($localAddress instanceof UnixAddress) {
            $spanBuilder->setAttribute('network.local.address', $localAddress->toString());
        }

        $remoteAddress = $stream->getRemoteAddress();
        if ($remoteAddress instanceof InternetAddress) {
            $spanBuilder->setAttribute('network.peer.address', $remoteAddress->getAddress());
            $spanBuilder->setAttribute('network.peer.port', $remoteAddress->getPort());
            $spanBuilder->setAttribute('network.type', strtolower($remoteAddress->getVersion()->name));
        }
        if ($remoteAddress instanceof UnixAddress) {
            $spanBuilder->setAttribute('network.peer.address', $remoteAddress->toString());
            $spanBuilder->setAttribute('network.transport', 'unix');
        }

        $span = $spanBuilder->startSpan();
        $context = $span->storeInContext(Context::getCurrent());
        foreach ($this->propagator->fields() as $field) {
            $request->removeHeader($field);
        }
        $this->propagator->inject($request, new HttpMessagePropagationSetter(), $context);

        $request->setAttribute(SpanInterface::class, $span);
        $request->setAttribute(ContextInterface::class, $context);
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
        $span = $request->getAttribute(SpanInterface::class);
        assert($span instanceof Span);

        $span->setAttribute('network.protocol.version', $response->getProtocolVersion());
        $span->setAttribute('http.response.status_code', $response->getStatus());
        if ($response->getStatus() >= 400) {
            $span->setStatus(StatusCode::STATUS_ERROR);
            $span->setAttribute('error.type', (string) $response->getStatus());
        }

        foreach ($this->captureResponseHeaders as $header => $attribute) {
            if ($value = $response->getHeaderArray($header)) {
                $span->setAttribute($attribute, $value);
            }
        }

        $span->end();
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
}
