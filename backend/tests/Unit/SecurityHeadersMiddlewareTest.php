<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Payments\Presentation\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class SecurityHeadersMiddlewareTest extends TestCase
{
    private SecurityHeaders $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new SecurityHeaders;
    }

    private function handle(Request $request): Response
    {
        return $this->middleware->handle($request, fn () => new Response('ok'));
    }

    // ─── Заголовки присутствуют ───────────────────────────────────────────────

    public function test_sets_x_frame_options(): void
    {
        $response = $this->handle(Request::create('/test'));

        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
    }

    public function test_sets_x_content_type_options(): void
    {
        $response = $this->handle(Request::create('/test'));

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_sets_referrer_policy(): void
    {
        $response = $this->handle(Request::create('/test'));

        $this->assertSame(
            'strict-origin-when-cross-origin',
            $response->headers->get('Referrer-Policy')
        );
    }

    public function test_sets_permissions_policy(): void
    {
        $response = $this->handle(Request::create('/test'));

        $this->assertNotEmpty($response->headers->get('Permissions-Policy'));
    }

    public function test_sets_content_security_policy(): void
    {
        $response = $this->handle(Request::create('/test'));

        $this->assertNotEmpty($response->headers->get('Content-Security-Policy'));
    }

    public function test_disables_legacy_xss_protection(): void
    {
        $response = $this->handle(Request::create('/test'));

        // 0 — отключает устаревший заголовок; CSP его заменяет
        $this->assertSame('0', $response->headers->get('X-XSS-Protection'));
    }

    // ─── Содержимое CSP ───────────────────────────────────────────────────────

    public function test_csp_contains_default_src_self(): void
    {
        $response = $this->handle(Request::create('/test'));
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    public function test_csp_blocks_framing_via_frame_ancestors_none(): void
    {
        $response = $this->handle(Request::create('/test'));
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_csp_restricts_base_uri(): void
    {
        $response = $this->handle(Request::create('/test'));
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("base-uri 'self'", $csp);
    }

    public function test_csp_restricts_form_action(): void
    {
        $response = $this->handle(Request::create('/test'));
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("form-action 'self'", $csp);
    }

    // ─── Middleware прозрачна для ответа ──────────────────────────────────────

    public function test_preserves_response_body(): void
    {
        $response = $this->middleware->handle(
            Request::create('/test'),
            fn () => new Response('hello world', 200)
        );

        $this->assertSame('hello world', $response->getContent());
    }

    public function test_preserves_response_status_code(): void
    {
        $response = $this->middleware->handle(
            Request::create('/test'),
            fn () => new Response('not found', 404)
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_calls_next_handler(): void
    {
        $called = false;

        $this->middleware->handle(Request::create('/test'), function () use (&$called) {
            $called = true;

            return new Response;
        });

        $this->assertTrue($called);
    }

    // ─── Интеграция через HTTP ────────────────────────────────────────────────

    public function test_integration_headers_present_on_api_response(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-XSS-Protection', '0');
        $response->assertHeaderMissing('X-Powered-By');
    }

    public function test_integration_csp_present_on_api_response(): void
    {
        $response = $this->getJson('/api/health');

        $this->assertNotEmpty($response->headers->get('Content-Security-Policy'));
    }
}
