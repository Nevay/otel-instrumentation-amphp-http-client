<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\Config;

use Nevay\OTelInstrumentation\AmphpHttpClient\UrlTemplateResolver;
use Nevay\OTelInstrumentation\AmphpHttpClient\UrlTemplateResolver\RequestAttributeResolver;
use OpenTelemetry\API\Configuration\Config\ComponentProvider;
use OpenTelemetry\API\Configuration\Config\ComponentProviderRegistry;
use OpenTelemetry\API\Configuration\Context;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

/**
 * @implements ComponentProvider<UrlTemplateResolver>
 */
final class UrlTemplateResolverRequestAttribute implements ComponentProvider {

    /**
     * @param array{
     *     attribute: string,
     * } $properties
     */
    public function createPlugin(array $properties, Context $context): UrlTemplateResolver {
        return new RequestAttributeResolver($properties['attribute']);
    }

    public function getConfig(ComponentProviderRegistry $registry, NodeBuilder $builder): ArrayNodeDefinition {
        $node = $builder->arrayNode('request_attribute');
        $node
            ->children()
                ->scalarNode('attribute')->isRequired()->cannotBeEmpty()->end()
            ->end()
        ;

        return $node;
    }
}
