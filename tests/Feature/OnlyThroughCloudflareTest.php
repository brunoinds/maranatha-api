<?php

namespace Tests\Feature;

use Tests\TestCase;

class OnlyThroughCloudflareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.only_cloudflare' => true]);
    }

    public function test_request_through_cloudflare_passes(): void
    {
        $this->get('/api/cd/check', ['X-Forwarded-For' => '172.70.1.10'])->assertOk();
        $this->get('/api/cd/check', ['X-Forwarded-For' => '2606:4700:10::1'])->assertOk();
    }

    public function test_direct_access_to_the_server_ip_is_refused(): void
    {
        $this->get('/api/cd/check', ['X-Forwarded-For' => '187.84.14.205'])->assertForbidden();
    }

    public function test_spoofed_cloudflare_ip_before_the_real_one_is_refused(): void
    {
        $this->get('/api/cd/check', ['X-Forwarded-For' => '172.70.1.10, 187.84.14.205'])->assertForbidden();
    }

    public function test_internal_requests_without_proxy_pass(): void
    {
        $this->get('/api/cd/check')->assertOk();
    }

    public function test_disabled_outside_production(): void
    {
        config(['app.only_cloudflare' => false]);
        $this->get('/api/cd/check', ['X-Forwarded-For' => '187.84.14.205'])->assertOk();
    }
}
