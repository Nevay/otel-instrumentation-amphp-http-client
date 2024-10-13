<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\Internal;

/**
 * @internal
 */
final class RequestSharedState {

    public function __construct(
        public int $resendCount = -1,
    ) {}
}
