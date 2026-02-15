<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\UrlTemplateResolver;

use Amp\Http\Client\Request;
use Nevay\OTelInstrumentation\AmphpHttpClient\UrlTemplateResolver;

final class RequestAttributeResolver implements UrlTemplateResolver {

    public function __construct(
        private readonly string $attribute,
    ) {}

    public function resolveUrlTemplate(Request $request): ?string {
        if (!$request->hasAttribute($this->attribute)) {
            return null;
        }

        return (string) $request->getAttribute($this->attribute);
    }
}
