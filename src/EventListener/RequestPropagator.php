<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\EventListener;

use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Connection\Connection;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\EventListener;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Nevay\OTelInstrumentation\AmphpHttpClient\Internal\HttpMessagePropagationSetter;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\PropagationSetterInterface;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;
use Throwable;

/**
 * Propagates HTTP client spans to requests.
 *
 * @see https://opentelemetry.io/docs/specs/semconv/http/http-spans/
 */
final class RequestPropagator implements EventListener {

    private readonly TextMapPropagatorInterface $propagator;
    private readonly PropagationSetterInterface $propagationSetter;

    public function __construct(TextMapPropagatorInterface $propagator) {
        $this->propagator = $propagator;
        $this->propagationSetter = new HttpMessagePropagationSetter();
    }

    public function requestStart(Request $request): void {
        // no-op
    }

    public function requestFailed(Request $request, Throwable $exception): void {
        // no-op
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
        $context = self::requestContext($request) ?: null;

        foreach ($this->propagator->fields() as $field) {
            $request->removeHeader($field);
        }
        $this->propagator->inject($request, $this->propagationSetter, $context);
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
        // no-op
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
