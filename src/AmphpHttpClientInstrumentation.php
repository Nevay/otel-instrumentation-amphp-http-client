<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient;

use Amp\Http\Client\HttpClientBuilder;
use Nevay\OTelInstrumentation\AmphpHttpClient\EventListener\Logs;
use Nevay\OTelInstrumentation\AmphpHttpClient\EventListener\Metrics;
use Nevay\OTelInstrumentation\AmphpHttpClient\EventListener\RequestPropagator;
use Nevay\OTelInstrumentation\AmphpHttpClient\EventListener\Tracing;
use OpenTelemetry\API\Configuration\ConfigProperties;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\Context;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\HookManagerInterface;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\Instrumentation;
use OpenTelemetry\API\Instrumentation\Configuration\General\HttpConfig as GeneralHttpConfig;
use OpenTelemetry\Contrib\Instrumentation\HttpConfig\HttpConfig as PhpHttpConfig;
use function array_push;

final class AmphpHttpClientInstrumentation implements Instrumentation {

    public function register(HookManagerInterface $hookManager, ConfigProperties $configuration, Context $context): void {
        $config = $configuration->get(AmphpHttpClientConfig::class) ?? new AmphpHttpClientConfig();
        if (!$config->enabled) {
            return;
        }

        $generalHttpConfig = $configuration->get(GeneralHttpConfig::class)?->config;
        $phpHttpConfig = $configuration->get(PhpHttpConfig::class) ?? new PhpHttpConfig();

        $eventListeners = [
            new Tracing(
                tracerProvider: $context->tracerProvider,
                captureRequestHeaders: $generalHttpConfig['client']['request_captured_headers'] ?? [],
                captureResponseHeaders: $generalHttpConfig['client']['response_captured_headers'] ?? [],
                captureUrlScheme: $phpHttpConfig->client->captureUrlScheme,
                captureUserAgentOriginal: $phpHttpConfig->client->captureUserAgentOriginal,
                captureRequestBodySize: $phpHttpConfig->client->captureRequestBodySize,
                captureResponseBodySize: $phpHttpConfig->client->captureResponseBodySize,
                knownHttpMethods: $phpHttpConfig->knownHttpMethods,
                sanitizer: $phpHttpConfig->sanitizer,
                urlTemplateResolver: $config->urlTemplateResolver,
            ),
            new Metrics(
                meterProvider: $context->meterProvider,
                knownHttpMethods: $phpHttpConfig->knownHttpMethods,
                urlTemplateResolver: $config->urlTemplateResolver,
            ),
            new Logs(
                loggerProvider: $context->loggerProvider,
            ),
            new RequestPropagator(
                propagator: $context->propagator,
            ),
        ];

        $hookManager->hook(
            HttpClientBuilder::class,
            '__construct',
            postHook: (static function(HttpClientBuilder $clientBuilder) use ($eventListeners): void {
                array_push($clientBuilder->eventListeners, ...$eventListeners);
            })->bindTo(null, HttpClientBuilder::class),
        );
    }
}
