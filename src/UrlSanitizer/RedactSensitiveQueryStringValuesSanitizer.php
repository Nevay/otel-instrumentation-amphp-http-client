<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer;

use Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer;
use Psr\Http\Message\UriInterface;
use function strlen;
use function strpos;
use function substr_compare;
use function substr_replace;

final class RedactSensitiveQueryStringValuesSanitizer implements UrlSanitizer {

    /**
     * @param list<string> $queryParameters sensitive query parameters to redact
     */
    public function __construct(
        private readonly array $queryParameters,
    ) {}

    public function sanitize(UriInterface $url): UriInterface {
        $query = $url->getQuery();
        for ($i = 0; $i < strlen($query); $i = $d + 1) {
            if (($d = strpos($query, '&', $i)) === false) {
                $d = strlen($query);
            }
            foreach ($this->queryParameters as $parameter) {
                $l = strlen($parameter);
                if (($query[$i + $l] ?? '') === '=' && !substr_compare($query, $parameter, $i, $l)) {
                    $query = substr_replace($query, 'REDACTED', $i + $l + 1, $d - $i - $l - 1);
                }
            }
        }

        if ($query === $url->getQuery()) {
            return $url;
        }

        return $url->withQuery($query);
    }
}
