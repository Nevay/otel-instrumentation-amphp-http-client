<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient;

use Nevay\SPI\ServiceLoader;
use OpenTelemetry\API\Configuration\Config\ComponentProvider;
use OpenTelemetry\API\Configuration\ConfigEnv\EnvComponentLoader;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\Instrumentation;
use function class_exists;

if (!class_exists(ServiceLoader::class)) {
    return;
}

ServiceLoader::register(Instrumentation::class, AmphpHttpClientInstrumentation::class);

ServiceLoader::register(ComponentProvider::class, Config\InstrumentationConfigurationAmphpHttpClientConfig::class);
ServiceLoader::register(ComponentProvider::class, Config\UrlTemplateResolverRequestAttribute::class);

ServiceLoader::register(EnvComponentLoader::class, ConfigEnv\InstrumentationConfigurationAmphpHttpClientConfig::class);
