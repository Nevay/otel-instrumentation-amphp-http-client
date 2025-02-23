# OpenTelemetry [amphp/http-client] instrumentation

## Installation

```shell
composer require tbachert/otel-instrumentation-amphp-http-client
```

## Usage

```php
use Amp\Http\Client\HttpClientBuilder;
use Nevay\OTelInstrumentation\AmphpHttpClient\MetricsEventListener;
use Nevay\OTelInstrumentation\AmphpHttpClient\TracingEventListener;

$httpClient = (new HttpClientBuilder)
    ->listen(new TracingEventListener($tracerProvider, $propagator))
    ->listen(new MetricsEventListener($meterProvider))
    ->build();

$response = $httpClient->request(...);
```

### Accessing the client span of a request

```php
use OpenTelemetry\API\Trace\SpanInterface;

$span = $response->getRequest()->getAttribute(SpanInterface::class);
```

[amphp/http-client]: https://github.com/amphp/http-client
