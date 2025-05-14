<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient;

use Psr\Http\Message\UriInterface;

interface UrlSanitizer {

    public function sanitize(UriInterface $url): UriInterface;
}
