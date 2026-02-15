<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\EventListener;

use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Connection\Connection;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\EventListener;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Composer\InstalledVersions;
use Nevay\OTelInstrumentation\AmphpHttpClient\Internal\RequestSharedState;
use OpenTelemetry\API\Logs\LoggerInterface;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ContextInterface;
use Throwable;

/**
 * Generates HTTP client request logs.
 *
 * @see https://opentelemetry.io/docs/specs/semconv/http/
 */
final class Logs implements EventListener {

    private readonly LoggerInterface $logger;

    public function __construct(
        LoggerProviderInterface $loggerProvider,
    ) {
        $this->logger = $loggerProvider->getLogger(
            'com.tobiasbachert.instrumentation.amphp-http-client',
            InstalledVersions::getPrettyVersion('tbachert/otel-instrumentation-amphp-http-client'),
            'https://opentelemetry.io/schemas/1.39.0',
        );
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
        $context = self::requestContext($request);

        $this->logger
            ->logRecordBuilder()
            ->setEventName('http.client.request.error')
            ->setException($exception)
            ->setContext($context)
            ->emit();
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
        // no-op
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
