<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient;

use Amp\Http\Client\Request;

interface UrlTemplateResolver {

    /**
     * Returns the url template for the request.
     *
     * @return string|null the url template for the request
     * @see https://opentelemetry.io/docs/specs/semconv/registry/attributes/url/#url-template
     */
    public function resolveUrlTemplate(Request $request): ?string;
}
