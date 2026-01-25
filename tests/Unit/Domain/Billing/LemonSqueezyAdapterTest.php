<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Adapters\LemonSqueezyAdapter;
use Illuminate\Http\Request;
use Tests\TestCase;

class LemonSqueezyAdapterTest extends TestCase
{
    public function test_parse_webhook_uses_payload_hash_when_id_missing(): void
    {
        $payload = json_encode([
            'meta' => [
                'event_name' => 'subscription_created',
            ],
            'data' => [
                'id' => 'sub_123',
            ],
        ], JSON_UNESCAPED_SLASHES);

        $request = Request::create('/webhooks/lemonsqueezy', 'POST', [], [], [], [], $payload);

        $adapter = new LemonSqueezyAdapter;
        $parsed = $adapter->parseWebhook($request);

        $this->assertSame(hash('sha256', $payload), $parsed['id']);
        $this->assertSame('subscription_created', $parsed['type']);
    }
}
