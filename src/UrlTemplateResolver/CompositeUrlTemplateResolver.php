<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient\UrlTemplateResolver;

use Amp\Http\Client\Request;
use Nevay\OTelInstrumentation\AmphpHttpClient\UrlTemplateResolver;

final class CompositeUrlTemplateResolver implements UrlTemplateResolver {

    private readonly array $urlTemplateResolvers;

    public function __construct(UrlTemplateResolver ...$urlTemplateResolvers) {
        $this->urlTemplateResolvers = $urlTemplateResolvers;
    }

    public function resolveUrlTemplate(Request $request): ?string {
        foreach ($this->urlTemplateResolvers as $urlTemplateResolver) {
            if (($route = $urlTemplateResolver->resolveUrlTemplate($request)) !== null) {
                return $route;
            }
        }

        return null;
    }
}
