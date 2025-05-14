<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer;

use Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer;
use Psr\Http\Message\UriInterface;
use function str_contains;

final class RedactUsernamePasswordSanitizer implements UrlSanitizer {

    public function sanitize(UriInterface $url): UriInterface {
        $userInfo = $url->getUserInfo();
        if ($userInfo === '') {
            return $url;
        }

        return str_contains($userInfo, ':')
            ? $url->withUserInfo('REDACTED', 'REDACTED')
            : $url->withUserInfo('REDACTED');
    }
}
