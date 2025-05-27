<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\Config;

use Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer;
use Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer\CompositeSanitizer;
use Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer\RedactSensitiveQueryStringValuesSanitizer;
use Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer\RedactUsernamePasswordSanitizer;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\InstrumentationConfiguration;

final class HttpConfig implements InstrumentationConfiguration {

    public function __construct(
        public readonly bool $captureUrlScheme = false,
        public readonly bool $captureUserAgentOriginal = false,
        public readonly bool $captureNetworkTransport = false,
        public readonly bool $captureRequestBodySize = false,
        public readonly bool $captureResponseBodySize = false,
        public readonly array $knownHttpMethods = ['CONNECT', 'DELETE', 'GET', 'HEAD', 'OPTIONS', 'PATCH', 'POST', 'PUT', 'TRACE'],
        public readonly UrlSanitizer $sanitizer = new CompositeSanitizer([
            new RedactUsernamePasswordSanitizer(),
            new RedactSensitiveQueryStringValuesSanitizer(['AWSAccessKeyId', 'Signature', 'sig', 'X-Goog-Signature']),
        ]),
    ) {}
}
