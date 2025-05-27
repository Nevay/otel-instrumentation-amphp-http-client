<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\Config;

use Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer;
use Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer\CompositeSanitizer;
use Nevay\OTelSDK\Configuration\ComponentPlugin;
use Nevay\OTelSDK\Configuration\ComponentProvider;
use Nevay\OTelSDK\Configuration\ComponentProviderRegistry;
use Nevay\OTelSDK\Configuration\Context;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\InstrumentationConfiguration;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use function array_map;

final class InstrumentationConfigurationHttp implements ComponentProvider {

    /**
     * @param array{
     *     capture_url_scheme: bool,
     *     capture_user_agent_original: bool,
     *     capture_network_transport: bool,
     *     capture_request_body_size: bool,
     *     capture_response_body_size: bool,
     *     known_http_methods: list<string>,
     *     url_sanitizers: list<ComponentPlugin<UrlSanitizer>>,
     * } $properties
     */
    public function createPlugin(array $properties, Context $context): InstrumentationConfiguration {
        return new HttpConfig(
            captureUrlScheme: $properties['capture_url_scheme'],
            captureUserAgentOriginal: $properties['capture_user_agent_original'],
            captureNetworkTransport: $properties['capture_network_transport'],
            captureRequestBodySize: $properties['capture_request_body_size'],
            captureResponseBodySize: $properties['capture_response_body_size'],
            knownHttpMethods: $properties['known_http_methods'],
            sanitizer: new CompositeSanitizer(array_map(
                static fn(ComponentPlugin $sanitizer): UrlSanitizer => $sanitizer->create($context),
                $properties['url_sanitizers'],
            )),
        );
    }

    public function getConfig(ComponentProviderRegistry $registry, NodeBuilder $builder): ArrayNodeDefinition {
        $node = $builder->arrayNode('http');
        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->booleanNode('capture_url_scheme')->defaultFalse()->end()
                ->booleanNode('capture_user_agent_original')->defaultFalse()->end()
                ->booleanNode('capture_network_transport')->defaultFalse()->end()
                ->booleanNode('capture_request_body_size')->defaultFalse()->end()
                ->booleanNode('capture_response_body_size')->defaultFalse()->end()
                ->arrayNode('known_http_methods')
                    ->defaultValue(['CONNECT', 'DELETE', 'GET', 'HEAD', 'OPTIONS', 'PATCH', 'POST', 'PUT', 'TRACE'])
                    ->scalarPrototype()->end()
                ->end()
                ->append($registry->componentList('url_sanitizers', UrlSanitizer::class)->defaultValue([['default' => null]]))
            ->end()
        ;

        return $node;
    }
}
