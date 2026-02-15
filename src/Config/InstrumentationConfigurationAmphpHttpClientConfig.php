<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\Config;

use Nevay\OTelInstrumentation\AmphpHttpClient\AmphpHttpClientConfig;
use Nevay\OTelInstrumentation\AmphpHttpClient\UrlTemplateResolver;
use Nevay\OTelInstrumentation\AmphpHttpClient\UrlTemplateResolver\CompositeUrlTemplateResolver;
use OpenTelemetry\API\Configuration\Config\ComponentPlugin;
use OpenTelemetry\API\Configuration\Config\ComponentProvider;
use OpenTelemetry\API\Configuration\Config\ComponentProviderRegistry;
use OpenTelemetry\API\Configuration\Context;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\InstrumentationConfiguration;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;

/**
 * @implements ComponentProvider<InstrumentationConfiguration>
 */
final class InstrumentationConfigurationAmphpHttpClientConfig implements ComponentProvider {

    /**
     * @param array{
     *     enabled: bool,
     *     url_template_resolvers: list<ComponentPlugin<UrlTemplateResolver>>,
     * } $properties
     */
    public function createPlugin(array $properties, Context $context): InstrumentationConfiguration {
        $urlTemplateResolvers = [];
        foreach ($properties['url_template_resolvers'] as $urlTemplateResolver) {
            $urlTemplateResolvers[] = $urlTemplateResolver->create($context);
        }

        return new AmphpHttpClientConfig(
            enabled: $properties['enabled'],
            urlTemplateResolver: new CompositeUrlTemplateResolver(...$urlTemplateResolvers),
        );
    }

    public function getConfig(ComponentProviderRegistry $registry, NodeBuilder $builder): ArrayNodeDefinition {
        $node = $builder->arrayNode('amphp_http_client');
        $node
            ->canBeDisabled()
            ->children()
                ->append($registry->componentList('url_template_resolvers', UrlTemplateResolver::class))
            ->end()
        ;

        return $node;
    }
}
