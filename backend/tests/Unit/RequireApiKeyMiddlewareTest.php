<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Payments\Presentation\Http\Middleware\RequireApiKey;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class RequireApiKeyMiddlewareTest extends TestCase
{
    private RequireApiKey $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RequireApiKey;
    }

    // ─── Проверка отключена (пустой ключ в конфиге) ───────────────────────────

    public function test_passes_through_when_api_key_not_configured(): void
    {
        config(['api.key' => '']);

        $request = Request::create('/test', 'GET');
        $called = false;

        $this->middleware->handle($request, function () use (&$called) {
            $called = true;

            return new Response('ok');
        });

        $this->assertTrue($called);
    }

    public function test_passes_through_when_api_key_is_null_in_config(): void
    {
        config(['api.key' => null]);

        $request = Request::create('/test', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(200, $response->getStatusCode());
    }

    // ─── Валидный ключ ────────────────────────────────────────────────────────

    public function test_allows_request_with_correct_key(): void
    {
        config(['api.key' => 'secret-key-123']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Api-Key', 'secret-key-123');

        $called = false;
        $response = $this->middleware->handle($request, function () use (&$called) {
            $called = true;

            return new Response('ok');
        });

        $this->assertTrue($called);
        $this->assertSame(200, $response->getStatusCode());
    }

    // ─── Неверный / отсутствующий ключ ───────────────────────────────────────

    public function test_rejects_request_with_wrong_key(): void
    {
        config(['api.key' => 'correct-key']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Api-Key', 'wrong-key');

        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_request_without_header(): void
    {
        config(['api.key' => 'secret']);

        $request = Request::create('/test', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_rejects_empty_header_value(): void
    {
        config(['api.key' => 'secret']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Api-Key', '');

        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_error_response_contains_correct_json_format(): void
    {
        config(['api.key' => 'secret']);

        $request = Request::create('/test', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $body = json_decode((string) $response->getContent(), true);

        $this->assertSame('unauthorized', $body['code']);
        $this->assertArrayHasKey('message', $body);
    }

    public function test_next_handler_not_called_on_rejection(): void
    {
        config(['api.key' => 'secret']);

        $request = Request::create('/test', 'GET');
        $called = false;

        $this->middleware->handle($request, function () use (&$called) {
            $called = true;

            return new Response;
        });

        $this->assertFalse($called);
    }

    // ─── Ротация ключей ───────────────────────────────────────────────────────

    public function test_accepts_first_key_in_rotation(): void
    {
        config(['api.key' => 'key-old,key-new']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Api-Key', 'key-old');

        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_accepts_second_key_in_rotation(): void
    {
        config(['api.key' => 'key-old,key-new']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Api-Key', 'key-new');

        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_rejects_unknown_key_when_rotation_configured(): void
    {
        config(['api.key' => 'key-old,key-new']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Api-Key', 'key-unknown');

        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_ignores_whitespace_around_keys_in_rotation(): void
    {
        config(['api.key' => ' key-one , key-two ']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Api-Key', 'key-two');

        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(200, $response->getStatusCode());
    }

    // ─── Timing-safe: не допускает timing-атак ────────────────────────────────

    public function test_rejects_key_that_is_prefix_of_correct_key(): void
    {
        config(['api.key' => 'secret-long-key']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Api-Key', 'secret');

        $response = $this->middleware->handle($request, fn () => new Response('ok'));

        $this->assertSame(401, $response->getStatusCode());
    }

    // ─── Интеграционные (через HTTP) ─────────────────────────────────────────

    public function test_integration_rejects_missing_key(): void
    {
        config(['api.key' => 'test-key']);

        $this->getJson('/api/v1/payments')->assertUnauthorized();
    }

    public function test_integration_allows_correct_key(): void
    {
        config(['api.key' => 'test-key']);

        $this->withHeaders(['X-Api-Key' => 'test-key'])
            ->getJson('/api/v1/payments')
            ->assertOk();
    }

    public function test_integration_skips_check_when_key_not_configured(): void
    {
        config(['api.key' => '']);

        $this->getJson('/api/v1/payments')->assertOk();
    }
}
