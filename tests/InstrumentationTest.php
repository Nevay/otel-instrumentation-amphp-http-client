<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient;

use Amp\Http\Client\HttpClientBuilder;
use OpenTelemetry\API\Configuration\ConfigProperties;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\Context;
use OpenTelemetry\API\Instrumentation\AutoInstrumentation\HookManagerInterface;
use PHPUnit\Framework\TestCase;

final class InstrumentationTest extends TestCase {

    public function testInstrumentationHooksSocketServerConstructor(): void {
        $hookManager = $this->createMock(HookManagerInterface::class);
        $configProperties = $this->createMock(ConfigProperties::class);

        $hookManager->expects($this->once())->method('hook')->with(HttpClientBuilder::class, '__construct');

        $instrumentation = new AmphpHttpClientInstrumentation();
        $instrumentation->register($hookManager, $configProperties, new Context());
    }

    public function testInstrumentationCanBeDisabled(): void {
        $hookManager = $this->createMock(HookManagerInterface::class);
        $configProperties = $this->createMock(ConfigProperties::class);

        $hookManager->expects($this->never())->method('hook');
        $configProperties->method('get')->with(AmphpHttpClientConfig::class)->willReturn(new AmphpHttpClientConfig(enabled: false));

        $instrumentation = new AmphpHttpClientInstrumentation();
        $instrumentation->register($hookManager, $configProperties, new Context());
    }
}
