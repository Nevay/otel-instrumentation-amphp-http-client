<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer;

use Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer;
use Psr\Http\Message\UriInterface;

final class CompositeSanitizer implements UrlSanitizer {

    /**
     * @param iterable<UrlSanitizer> $sanitizers
     */
    public function __construct(
        private readonly iterable $sanitizers,
    ) {}

    public function sanitize(UriInterface $url): UriInterface {
        foreach ($this->sanitizers as $sanitizer) {
            $url = $sanitizer->sanitize($url);
        }

        return $url;
    }
}
