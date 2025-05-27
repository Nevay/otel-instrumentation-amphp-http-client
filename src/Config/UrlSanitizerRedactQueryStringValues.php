<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\Config;

use Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer;
use Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer\RedactSensitiveQueryStringValuesSanitizer;
use Nevay\OTelSDK\Configuration\ComponentProvider;
use Nevay\OTelSDK\Configuration\ComponentProviderRegistry;
use Nevay\OTelSDK\Configuration\Context;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

final class UrlSanitizerRedactQueryStringValues implements ComponentProvider {

    /**
     * @param array{
     *     query_keys: list<string>,
     * } $properties
     */
    public function createPlugin(array $properties, Context $context): UrlSanitizer {
        return new RedactSensitiveQueryStringValuesSanitizer($properties['query_keys']);
    }

    public function getConfig(ComponentProviderRegistry $registry, NodeBuilder $builder): ArrayNodeDefinition {
        $node = $builder->arrayNode('redact_query_string_values');
        $node
            ->children()
                ->arrayNode('query_keys')
                    ->scalarPrototype()->end()
                ->end()
            ->end()
        ;

        return $node;
    }
}
