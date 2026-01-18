<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient;

use OpenTelemetry\API\Instrumentation\AutoInstrumentation\InstrumentationConfiguration;

final class AmphpHttpClientConfig implements InstrumentationConfiguration {

    public function __construct(
        public readonly bool $enabled = true,
    ) {}
}
