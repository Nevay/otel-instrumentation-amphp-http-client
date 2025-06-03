<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer;

use Nevay\OTelInstrumentation\AmphpHttpClient\UrlSanitizer;
use Psr\Http\Message\UriInterface;
use function strlen;
use function strpos;
use function substr;
use function substr_compare;

final class RedactSensitiveQueryStringValuesSanitizer implements UrlSanitizer {

    /**
     * @param list<string> $queryParameters sensitive query parameters to redact
     */
    public function __construct(
        private readonly array $queryParameters,
    ) {}

    public function sanitize(UriInterface $url): UriInterface {
        $query = $url->getQuery();
        $offset = 0;
        $sanitized = '';
        for ($i = 0, $n = strlen($query); $i < $n; $i = $d + 1) {
            if (($d = strpos($query, '&', $i)) === false) {
                $d = strlen($query);
            }

            foreach ($this->queryParameters as $parameter) {
                $l = strlen($parameter);
                if (($query[$i + $l] ?? '') === '=' && !substr_compare($query, $parameter, $i, $l)) {
                    $sanitized .= substr($query, $offset, $i + $l + 1 - $offset);
                    $sanitized .= 'REDACTED';
                    $offset = $d;
                    break;
                }
            }
        }

        if ($offset === 0) {
            return $url;
        }

        $sanitized .= substr($query, $offset);

        return $url->withQuery($sanitized);
    }
}
