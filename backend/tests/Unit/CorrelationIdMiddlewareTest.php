<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Payments\Infrastructure\Observability\CorrelationIdMiddleware;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class CorrelationIdMiddlewareTest extends TestCase
{
    private CorrelationIdMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new CorrelationIdMiddleware;
    }

    public function test_passes_existing_correlation_id_to_response(): void
    {
        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Correlation-Id', 'my-fixed-id-123');

        $response = $this->middleware->handle($request, fn () => new Response);

        $this->assertSame('my-fixed-id-123', $response->headers->get('X-Correlation-Id'));
    }

    public function test_generates_uuid_when_header_absent(): void
    {
        $request = Request::create('/test', 'GET');

        $response = $this->middleware->handle($request, fn () => new Response);

        $correlationId = $response->headers->get('X-Correlation-Id');
        $this->assertNotEmpty($correlationId);

        // Проверяем что это UUID формат
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $correlationId,
        );
    }

    public function test_different_requests_get_different_generated_ids(): void
    {
        $request1 = Request::create('/test', 'GET');
        $request2 = Request::create('/test', 'GET');

        $response1 = $this->middleware->handle($request1, fn () => new Response);
        $response2 = $this->middleware->handle($request2, fn () => new Response);

        $this->assertNotSame(
            $response1->headers->get('X-Correlation-Id'),
            $response2->headers->get('X-Correlation-Id'),
        );
    }

    public function test_middleware_passes_request_to_next(): void
    {
        $request = Request::create('/test', 'GET');
        $called = false;

        $this->middleware->handle($request, function () use (&$called) {
            $called = true;

            return new Response('ok');
        });

        $this->assertTrue($called);
    }

    public function test_response_body_is_preserved(): void
    {
        $request = Request::create('/test', 'GET');

        $response = $this->middleware->handle(
            $request,
            fn () => new Response('hello world', 200),
        );

        $this->assertSame('hello world', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_integration_via_api_route(): void
    {
        $response = $this->get('/api/v1/payments');

        $response->assertHeader('X-Correlation-Id');
    }

    public function test_integration_passes_custom_id_through(): void
    {
        $response = $this->withHeaders([
            'X-Correlation-Id' => 'custom-trace-id-abc',
        ])->get('/api/v1/payments');

        $response->assertHeader('X-Correlation-Id', 'custom-trace-id-abc');
    }
}
