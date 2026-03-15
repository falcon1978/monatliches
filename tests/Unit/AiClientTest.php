<?php

namespace Tests\Unit;

use App\Services\Ai\AiClient;
use Tests\TestCase;

class AiClientTest extends TestCase
{
    public function test_sign_payload_builds_expected_signature(): void
    {
        config([
            'services.ai.hmac_secret' => 'unit-test-secret',
        ]);

        $client = new AiClient();
        $payload = [
            'tenant_id' => 'budget-user-7',
            'rapport_id' => 'month-15-202602251200',
            'rapport_summary' => [
                'output_language' => 'de',
            ],
            'findings' => [],
        ];

        $signed = $client->signPayload($payload);

        $expectedBodyJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $expectedSignature = hash_hmac(
            'sha256',
            $signed['timestamp'].'.'.$signed['bodyJson'],
            'unit-test-secret'
        );

        $this->assertSame($expectedBodyJson, $signed['bodyJson']);
        $this->assertSame($expectedSignature, $signed['signature']);
        $this->assertNotSame('', $signed['timestamp']);
    }
}
