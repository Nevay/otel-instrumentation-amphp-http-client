<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient;

use Amp\Http\Client\HttpClientBuilder;
use Nevay\OTelInstrumentation\Http;
use OpenTelemetry\API\Configuration\ConfigProperties;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\Context;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\HookManagerInterface;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\Instrumentation;
use OpenTelemetry\API\Instrumentation\Configuration\General\HttpConfig;

final class AmphpHttpClientInstrumentation implements Instrumentation {

    public function register(HookManagerInterface $hookManager, ConfigProperties $configuration, Context $context): void {
        $config = $configuration->get(HttpConfig::class)?->config;
        $httpConfig = $configuration->get(Http\HttpConfig::class) ?? new Http\HttpConfig();

        $tracing = new TracingEventListener(
            tracerProvider: $context->tracerProvider,
            propagator: $context->propagator,
            captureRequestHeaders: $config['client']['request_captured_headers'] ?? [],
            captureResponseHeaders: $config['client']['response_captured_headers'] ?? [],
            captureUrlScheme: $httpConfig->client->captureUrlScheme,
            captureUserAgentOriginal: $httpConfig->client->captureUserAgentOriginal,
            captureRequestBodySize: $httpConfig->client->captureRequestBodySize,
            captureResponseBodySize: $httpConfig->client->captureResponseBodySize,
            knownHttpMethods: $httpConfig->knownHttpMethods,
            sanitizer: $httpConfig->sanitizer,
        );
        $metrics = new MetricsEventListener(
            meterProvider: $context->meterProvider,
            captureUrlScheme: $httpConfig->client->captureUrlScheme,
            knownHttpMethods: $httpConfig->knownHttpMethods,
        );

        $hookManager->hook(
            HttpClientBuilder::class,
            '__construct',
            postHook: (static function(HttpClientBuilder $clientBuilder) use ($tracing, $metrics): void {
                $clientBuilder->eventListeners[] = $tracing;
                $clientBuilder->eventListeners[] = $metrics;
            })->bindTo(null, HttpClientBuilder::class),
        );
    }
}
