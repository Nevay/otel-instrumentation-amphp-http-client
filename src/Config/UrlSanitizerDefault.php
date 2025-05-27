<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\Config;

use Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer;
use Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer\CompositeSanitizer;
use Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer\RedactSensitiveQueryStringValuesSanitizer;
use Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer\RedactUsernamePasswordSanitizer;
use Nevay\OTelSDK\Configuration\ComponentProvider;
use Nevay\OTelSDK\Configuration\ComponentProviderRegistry;
use Nevay\OTelSDK\Configuration\Context;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final class UrlSanitizerDefault implements ComponentProvider {

    public function createPlugin(array $properties, Context $context): UrlSanitizer {
        return new CompositeSanitizer([
            new RedactUsernamePasswordSanitizer(),
            new RedactSensitiveQueryStringValuesSanitizer(['AWSAccessKeyId', 'Signature', 'sig', 'X-Goog-Signature']),
        ]);
    }

    public function getConfig(ComponentProviderRegistry $registry, NodeBuilder $builder): ArrayNodeDefinition {
        return $builder->arrayNode('default');
    }
}
