<?php declare(strict_types=1);
namespace Nevay\OTelInstrumentation\AmphpHttpClient;

use Amp\Http\Client\BufferedContent;
use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Interceptor\InterceptorTest;
use Amp\Http\Client\Interceptor\ResolveBaseUri;
use Amp\Http\Client\Request;
use Amp\Http\HttpStatus;
use Amp\Http\Server\DefaultErrorHandler;
use Amp\Http\Server\Driver\SocketClientFactory;
use Amp\Http\Server\RequestHandler\ClosureRequestHandler;
use Amp\Http\Server\Response;
use Amp\Http\Server\SocketHttpServer;
use Amp\Socket\InternetAddress;
use Amp\Socket\ResourceServerSocketFactory;
use Amp\Socket\StaticSocketConnector;
use Nevay\OTelSDK\Trace\IdGenerator;
use Nevay\OTelSDK\Trace\SpanExporter\InMemorySpanExporter;
use Nevay\OTelSDK\Trace\SpanProcessor\BatchSpanProcessor;
use Nevay\OTelSDK\Trace\TracerProviderBuilder;
use OpenTelemetry\API\Trace\NoopTracerProvider;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Context\Propagation\NoopTextMapPropagator;
use Psr\Log\NullLogger;
use function Amp\Socket\socketConnector;
use function hex2bin;

final class TracingEventListenerTest extends InterceptorTest {

    public function testInjectsTraceParent(): void {
        $tracerProvider = (new TracerProviderBuilder())
            ->setIdGenerator(new class implements IdGenerator {

                public function generateSpanIdBinary(): string {
                    return hex2bin('43b34e9afb52a2db');
                }

                public function generateTraceIdBinary(): string {
                    return hex2bin('ac0a7f8c2faac49775a616b7c0cc21d8');
                }

                public function traceFlags(): int {
                    return 0;
                }
            })
            ->build();
        $listener = new TracingEventListener($tracerProvider, new TraceContextPropagator());

        $this->givenEventListener($listener);
        $this->whenRequestIsExecuted();
        $this->thenRequestHasHeader('traceparent', '00-ac0a7f8c2faac49775a616b7c0cc21d8-43b34e9afb52a2db-01');

        $tracerProvider->shutdown();
    }

    public function testSetsRequestAttributesSpanAndContext(): void {
        $listener = new TracingEventListener(new NoopTracerProvider(), new NoopTextMapPropagator());

        $this->givenEventListener($listener);
        $this->whenRequestIsExecuted();

        $this->assertTrue($this->getRequest()->hasAttribute(SpanInterface::class));
        $this->assertTrue($this->getRequest()->hasAttribute(ContextInterface::class));
    }

    public function testSpanAttributes(): void {
        $tracerProvider = (new TracerProviderBuilder())
            ->addSpanProcessor(new BatchSpanProcessor($exporter = new InMemorySpanExporter()))
            ->build();

        $listener = new TracingEventListener($tracerProvider, new NoopTextMapPropagator());

        $this->givenEventListener($listener);
        $this->whenRequestIsExecuted(new Request('http://example.com/foo/bar?test=1'));

        $tracerProvider->shutdown();
        $spans = $exporter->collect();
        $this->assertCount(1, $spans);

        $span = $spans[0];

        $this->assertSame('GET', $span->getName());
        $this->assertSame('GET', $span->getAttributes()->get('http.request.method'));
        $this->assertSame('example.com', $span->getAttributes()->get('server.address'));
        $this->assertSame('http://example.com/foo/bar?test=1', $span->getAttributes()->get('url.full'));
        $this->assertSame(200, $span->getAttributes()->get('http.response.status_code'));
    }

    public function testCaptureRequestHeaders(): void {
        $tracerProvider = (new TracerProviderBuilder())
            ->addSpanProcessor(new BatchSpanProcessor($exporter = new InMemorySpanExporter()))
            ->build();
        $listener = new TracingEventListener($tracerProvider, new NoopTextMapPropagator(), captureRequestHeaders: ['Content-Length']);

        $this->givenEventListener($listener);
        $this->whenRequestIsExecuted(new Request('http://example.com', 'POST', BufferedContent::fromString('test')));

        $tracerProvider->shutdown();
        $spans = $exporter->collect();
        $this->assertCount(1, $spans);

        $this->assertSame(['4'], $spans[0]->getAttributes()->get('http.request.header.content-length'));
    }

    public function testUnknownRequestMethod(): void {
        $tracerProvider = (new TracerProviderBuilder())
            ->addSpanProcessor(new BatchSpanProcessor($exporter = new InMemorySpanExporter()))
            ->build();
        $listener = new TracingEventListener($tracerProvider, new NoopTextMapPropagator());

        $this->givenEventListener($listener);
        $this->whenRequestIsExecuted(new Request('http://example.com/foo/bar?test=1', 'CUSTOM'));

        $tracerProvider->shutdown();
        $spans = $exporter->collect();
        $this->assertCount(1, $spans);

        $span = $spans[0];

        $this->assertSame('HTTP', $span->getName());
        $this->assertSame('_OTHER', $span->getAttributes()->get('http.request.method'));
        $this->assertSame('CUSTOM', $span->getAttributes()->get('http.request.method_original'));
    }

    public function testRedactUserPassword(): void {
        $tracerProvider = (new TracerProviderBuilder())
            ->addSpanProcessor(new BatchSpanProcessor($exporter = new InMemorySpanExporter()))
            ->build();
        $listener = new TracingEventListener($tracerProvider, new NoopTextMapPropagator());

        (fn() => $this->builder = $this->builder->allowDeprecatedUriUserInfo())->bindTo($this, InterceptorTest::class)();
        $this->givenEventListener($listener);
        $this->whenRequestIsExecuted(new Request('http://user:pass@example.com'));

        $tracerProvider->shutdown();
        $spans = $exporter->collect();
        $this->assertCount(1, $spans);

        $span = $spans[0];

        $this->assertSame('http://REDACTED:REDACTED@example.com', $span->getAttributes()->get('url.full'));
    }

    public function testRedactSensitiveQueryParameter(): void {
        $tracerProvider = (new TracerProviderBuilder())
            ->addSpanProcessor(new BatchSpanProcessor($exporter = new InMemorySpanExporter()))
            ->build();
        $listener = new TracingEventListener($tracerProvider, new NoopTextMapPropagator());

        $this->givenEventListener($listener);
        $this->whenRequestIsExecuted(new Request('http://www.example.com/path?color=blue&sig=somesignature'));

        $tracerProvider->shutdown();
        $spans = $exporter->collect();
        $this->assertCount(1, $spans);

        $span = $spans[0];

        $this->assertSame('http://www.example.com/path?color=blue&sig=REDACTED', $span->getAttributes()->get('url.full'));
    }

    public function testResolveBaseUri(): void {
        $tracerProvider = (new TracerProviderBuilder())
            ->addSpanProcessor(new BatchSpanProcessor($exporter = new InMemorySpanExporter()))
            ->build();
        $listener = new TracingEventListener($tracerProvider, new NoopTextMapPropagator());

        $this->givenEventListener($listener);
        $this->givenApplicationInterceptor(new ResolveBaseUri('http://example.com'));
        @$this->whenRequestIsExecuted(new Request('/foo/bar?test=1'));

        $tracerProvider->shutdown();
        $spans = $exporter->collect();
        $this->assertCount(1, $spans);

        $span = $spans[0];

        $this->assertSame('http://example.com/foo/bar?test=1', $span->getAttributes()->get('url.full'));
    }

    public function testRedirectResendCount(): void {
        $server = new SocketHttpServer(new NullLogger(), new ResourceServerSocketFactory(), new SocketClientFactory(new NullLogger()));
        $server->expose(new InternetAddress('127.0.0.1', 0));
        $server->start(new ClosureRequestHandler(static function(): Response {
            static $i = -1;
            return match (++$i) {
                0 => new Response(HttpStatus::MOVED_PERMANENTLY, ['Location' => 'http://example.com/redirect1']),
                1 => new Response(HttpStatus::MOVED_PERMANENTLY, ['Location' => 'http://example.com/redirect2']),
                default => new Response(HttpStatus::OK),
            };
        }), new DefaultErrorHandler());

        $tracerProvider = (new TracerProviderBuilder())
            ->addSpanProcessor(new BatchSpanProcessor($exporter = new InMemorySpanExporter()))
            ->build();
        $listener = new TracingEventListener($tracerProvider, new NoopTextMapPropagator());

        $client = (new HttpClientBuilder())
            ->usingPool(new UnlimitedConnectionPool(new DefaultConnectionFactory(new StaticSocketConnector($server->getServers()[0]->getAddress()->toString(), socketConnector()))))
            ->listen($listener)
            ->build();

        $client->request(new Request('http://example.com/initial'));

        $server->stop();
        $tracerProvider->shutdown();
        $spans = $exporter->collect();
        $this->assertCount(3, $spans);

        $this->assertSame('http://example.com/initial', $spans[0]->getAttributes()->get('url.full'));
        $this->assertSame('http://example.com/redirect1', $spans[1]->getAttributes()->get('url.full'));
        $this->assertSame('http://example.com/redirect2', $spans[2]->getAttributes()->get('url.full'));
        $this->assertSame(null, $spans[0]->getAttributes()->get('http.request.resend_count'));
        $this->assertSame(1, $spans[1]->getAttributes()->get('http.request.resend_count'));
        $this->assertSame(2, $spans[2]->getAttributes()->get('http.request.resend_count'));
    }
}
