<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\ConfigEnv;

use Nevay\OTelInstrumentation\AmphpHttpClient\AmphpHttpClientConfig;
use OpenTelemetry\API\Configuration\ConfigEnv\EnvComponentLoader;
use OpenTelemetry\API\Configuration\ConfigEnv\EnvComponentLoaderRegistry;
use OpenTelemetry\API\Configuration\ConfigEnv\EnvResolver;
use OpenTelemetry\API\Configuration\Context;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\InstrumentationConfiguration;
use function in_array;

/**
 * @implements EnvComponentLoader<InstrumentationConfiguration>
 */
final class InstrumentationConfigurationAmphpHttpClientConfig implements EnvComponentLoader {

    public function load(EnvResolver $env, EnvComponentLoaderRegistry $registry, Context $context): InstrumentationConfiguration {
        $disabledInstrumentations = $env->list('OTEL_PHP_DISABLED_INSTRUMENTATIONS');

        return new AmphpHttpClientConfig(
            enabled: !$disabledInstrumentations || $disabledInstrumentations !== ['all'] && !in_array('amphp-http-client', $disabledInstrumentations, true),
        );
    }

    public function name(): string {
        return AmphpHttpClientConfig::class;
    }
}
