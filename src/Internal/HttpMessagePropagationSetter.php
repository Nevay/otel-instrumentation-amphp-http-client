<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\Internal;

use Amp\Http\Client\Request;
use OpenTelemetry\Context\Propagation\PropagationSetterInterface;
use function assert;

/**
 * @internal
 */
final class HttpMessagePropagationSetter implements PropagationSetterInterface {

    public function set(&$carrier, string $key, string $value): void {
        assert($carrier instanceof Request);

        $carrier->setHeader($key, $value);
    }
}
