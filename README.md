# OpenTelemetry [amphp/http-client] instrumentation

## Installation

```shell
composer require tbachert/otel-instrumentation-amphp-http-client
```

## Usage

### Automatic instrumentation

This instrumentation is enabled by default.

#### Disable via file-based configuration

```yaml
instrumentations/development:
  php:
    amphp_http_client: false
```

#### Disable via env-based configuration

```shell
OTEL_PHP_DISABLED_INSTRUMENTATIONS=amphp-http-client
```

### Manual instrumentation

```php
use Amp\Http\Client\HttpClientBuilder;
use Nevay\OTelInstrumentation\AmphpHttpClient\EventListener\Logs;
use Nevay\OTelInstrumentation\AmphpHttpClient\EventListener\Metrics;
use Nevay\OTelInstrumentation\AmphpHttpClient\EventListener\RequestPropagator;
use Nevay\OTelInstrumentation\AmphpHttpClient\EventListener\Tracing;

$httpClient = (new HttpClientBuilder)
    ->listen(new Tracing($tracerProvider))
    ->listen(new Metrics($meterProvider))
    ->listen(new Logs($loggerProvider))
    ->listen(new RequestPropagator($propagator))
    ->build();

$response = $httpClient->request(...);
```

### Accessing the client span of a request

```php
use OpenTelemetry\API\Trace\SpanInterface;

$span = $response->getRequest()->getAttribute(SpanInterface::class);
```

[amphp/http-client]: https://github.com/amphp/http-client
