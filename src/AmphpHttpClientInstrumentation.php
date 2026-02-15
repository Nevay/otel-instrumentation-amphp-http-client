<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient;

use Amp\Http\Client\HttpClientBuilder;
use OpenTelemetry\API\Configuration\ConfigProperties;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\Context;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\HookManagerInterface;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\Instrumentation;
use OpenTelemetry\API\Instrumentation\Configuration\General\HttpConfig as GeneralHttpConfig;
use OpenTelemetry\Contrib\Instrumentation\HttpConfig\HttpConfig as PhpHttpConfig;

final class AmphpHttpClientInstrumentation implements Instrumentation {

    public function register(HookManagerInterface $hookManager, ConfigProperties $configuration, Context $context): void {
        $config = $configuration->get(AmphpHttpClientConfig::class) ?? new AmphpHttpClientConfig();
        if (!$config->enabled) {
            return;
        }

        $generalHttpConfig = $configuration->get(GeneralHttpConfig::class)?->config;
        $phpHttpConfig = $configuration->get(PhpHttpConfig::class) ?? new PhpHttpConfig();

        $tracing = new TracingEventListener(
            tracerProvider: $context->tracerProvider,
            propagator: $context->propagator,
            captureRequestHeaders: $generalHttpConfig['client']['request_captured_headers'] ?? [],
            captureResponseHeaders: $generalHttpConfig['client']['response_captured_headers'] ?? [],
            captureUrlScheme: $phpHttpConfig->client->captureUrlScheme,
            captureUserAgentOriginal: $phpHttpConfig->client->captureUserAgentOriginal,
            captureRequestBodySize: $phpHttpConfig->client->captureRequestBodySize,
            captureResponseBodySize: $phpHttpConfig->client->captureResponseBodySize,
            knownHttpMethods: $phpHttpConfig->knownHttpMethods,
            sanitizer: $phpHttpConfig->sanitizer,
            urlTemplateResolver: $config->urlTemplateResolver,
        );
        $metrics = new MetricsEventListener(
            meterProvider: $context->meterProvider,
            knownHttpMethods: $phpHttpConfig->knownHttpMethods,
            urlTemplateResolver: $config->urlTemplateResolver,
        );
        $logs = new LogsEventListener(
            loggerProvider: $context->loggerProvider,
        );

        $hookManager->hook(
            HttpClientBuilder::class,
            '__construct',
            postHook: (static function(HttpClientBuilder $clientBuilder) use ($tracing, $metrics, $logs): void {
                $clientBuilder->eventListeners[] = $tracing;
                $clientBuilder->eventListeners[] = $metrics;
                $clientBuilder->eventListeners[] = $logs;
            })->bindTo(null, HttpClientBuilder::class),
        );
    }
}
