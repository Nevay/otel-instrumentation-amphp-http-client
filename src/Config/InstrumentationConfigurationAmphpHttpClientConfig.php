<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\Config;

use Nevay\OTelInstrumentation\AmphpHttpClient\AmphpHttpClientConfig;
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
     * } $properties
     */
    public function createPlugin(array $properties, Context $context): InstrumentationConfiguration {
        return new AmphpHttpClientConfig(
            enabled: $properties['enabled'],
        );
    }

    public function getConfig(ComponentProviderRegistry $registry, NodeBuilder $builder): ArrayNodeDefinition {
        $node = $builder->arrayNode('amphp_http_server');
        $node
            ->canBeDisabled()
        ;

        return $node;
    }
}
