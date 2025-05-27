<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\Config;

use Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer;
use Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer\RedactUsernamePasswordSanitizer;
use Nevay\OTelSDK\Configuration\ComponentProvider;
use Nevay\OTelSDK\Configuration\ComponentProviderRegistry;
use Nevay\OTelSDK\Configuration\Context;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final class UrlSanitizerRedactUserInfo implements ComponentProvider {

    public function createPlugin(array $properties, Context $context): UrlSanitizer {
        return new RedactUsernamePasswordSanitizer();
    }

    public function getConfig(ComponentProviderRegistry $registry, NodeBuilder $builder): ArrayNodeDefinition {
        return $builder->arrayNode('redact_userinfo');
    }
}
