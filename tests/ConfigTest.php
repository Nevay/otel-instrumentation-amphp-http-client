<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient;

use League\Uri\UriTemplate;
use Nevay\OTelInstrumentation\AmphpHttpClient\UrlTemplateResolver\CompositeUrlTemplateResolver;
use Nevay\OTelInstrumentation\AmphpHttpClient\UrlTemplateResolver\RequestAttributeResolver;
use Nevay\OTelSDK\Configuration\Config;
use Nevay\OTelSDK\Configuration\Env\ArrayEnvSource;
use Nevay\OTelSDK\Configuration\Env\EnvSourceReader;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase {

    public function testKitchenSink(): void {
        $result = Config::loadFile(__DIR__ . '/snippets/kitchen-sink.yaml');

        $config = $result->configProperties->get(AmphpHttpClientConfig::class);

        $this->assertInstanceOf(AmphpHttpClientConfig::class, $config);
        $this->assertTrue($config->enabled);
        $this->assertEquals(
            new CompositeUrlTemplateResolver(
                new RequestAttributeResolver('url.template'),
                new RequestAttributeResolver(UriTemplate::class),
            ),
            $config->urlTemplateResolver,
        );
    }

    public function testDisabled(): void {
        $result = Config::loadFile(__DIR__ . '/snippets/disabled.yaml');

        $config = $result->configProperties->get(AmphpHttpClientConfig::class);

        $this->assertInstanceOf(AmphpHttpClientConfig::class, $config);
        $this->assertFalse($config->enabled);
    }

    public function testEnv(): void {
        $result = Config::loadFromEnv(new EnvSourceReader([new ArrayEnvSource([])]));

        $config = $result->configProperties->get(AmphpHttpClientConfig::class);

        $this->assertInstanceOf(AmphpHttpClientConfig::class, $config);
        $this->assertTrue($config->enabled);
    }

    public function testEnvDisabled(): void {
        $result = Config::loadFromEnv(new EnvSourceReader([new ArrayEnvSource(['OTEL_PHP_DISABLED_INSTRUMENTATIONS' => 'amphp-http-client'])]));

        $config = $result->configProperties->get(AmphpHttpClientConfig::class);

        $this->assertInstanceOf(AmphpHttpClientConfig::class, $config);
        $this->assertFalse($config->enabled);
    }

    public function testEnvDisabledAll(): void {
        $result = Config::loadFromEnv(new EnvSourceReader([new ArrayEnvSource(['OTEL_PHP_DISABLED_INSTRUMENTATIONS' => 'all'])]));

        $config = $result->configProperties->get(AmphpHttpClientConfig::class);

        $this->assertInstanceOf(AmphpHttpClientConfig::class, $config);
        $this->assertFalse($config->enabled);
    }
}
