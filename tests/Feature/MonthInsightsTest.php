<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Month;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MonthInsightsTest extends TestCase
{
    use RefreshDatabase;

    public function test_insights_call_returns_ai_payload_on_success(): void
    {
        $this->configureAi();

        $user = User::factory()->create();
        $month = $this->createMonthWithData($user);

        Http::fake([
            'http://127.0.0.1:8080/v1/rapport/analyze' => Http::response([
                'summary' => 'AI Zusammenfassung fuer den Monat.',
                'prioritized_findings' => [
                    [
                        'code' => 'expense_pressure_high',
                        'severity' => 'high',
                        'title' => 'Kostenlast hoch',
                        'description' => 'Die offenen Kosten sind im Verhaeltnis zu den Einnahmen hoch.',
                    ],
                ],
                'suggested_fixes' => [
                    [
                        'title' => 'Top-Kostenblock zuerst pruefen',
                        'description' => 'Beginne mit dem groessten offenen Kostenblock.',
                        'amount_reference' => 120.5,
                    ],
                ],
                'questions' => [
                    'Welche Ausgabe kannst du diese Woche als Erstes reduzieren?',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('months.insights', $month));

        $response->assertOk()
            ->assertJsonPath('source', 'ai')
            ->assertJsonPath('summary', 'AI Zusammenfassung fuer den Monat.')
            ->assertJsonCount(1, 'prioritized_findings')
            ->assertJsonCount(1, 'suggested_fixes');

        Http::assertSent(function ($request) use ($user) {
            $payload = $request->data();

            return $request->url() === 'http://127.0.0.1:8080/v1/rapport/analyze'
                && $request->hasHeader('X-AI-Timestamp')
                && $request->hasHeader('X-AI-Signature')
                && ($payload['tenant_id'] ?? null) === 'budget-user-'.$user->id
                && isset($payload['rapport_summary'])
                && isset($payload['findings']);
        });
    }

    public function test_insights_call_returns_german_fallback_when_ai_times_out(): void
    {
        $this->configureAi();

        $user = User::factory()->create();
        $month = $this->createMonthWithData($user);

        Http::fake(function () {
            throw new ConnectionException('Connection timed out');
        });

        $response = $this->actingAs($user)
            ->getJson(route('months.insights', $month));

        $response->assertOk()
            ->assertJsonPath('source', 'fallback')
            ->assertJsonStructure([
                'summary',
                'prioritized_findings',
                'suggested_fixes',
                'questions',
                'generated_at',
            ]);

        $summary = (string) $response->json('summary');
        $this->assertNotSame('', trim($summary));
        $this->assertStringContainsString('Monat', $summary);
    }

    public function test_user_cannot_analyze_foreign_month(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $month = Month::create([
            'user_id' => $owner->id,
            'name' => 'Jul 2026',
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-31',
            'daily_living_cost' => 40,
        ]);

        $this->actingAs($other)
            ->getJson(route('months.insights', $month))
            ->assertForbidden();
    }

    private function configureAi(): void
    {
        config([
            'services.ai.enabled' => true,
            'services.ai.base_url' => 'http://127.0.0.1:8080',
            'services.ai.hmac_secret' => 'testing-secret',
            'services.ai.timeout_seconds' => 5,
            'services.ai.tenant_prefix' => 'budget-user',
        ]);
    }

    private function createMonthWithData(User $user): Month
    {
        $month = Month::create([
            'user_id' => $user->id,
            'name' => 'Jun 2026',
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
            'daily_living_cost' => 45,
            'is_current' => true,
        ]);

        $forecastAccount = $user->accounts()->create([
            'name' => 'Forecast',
            'type' => 'forecast',
        ]);
        $bankAccount = $user->accounts()->where('type', 'ist')->first()
            ?? $user->accounts()->create([
                'name' => 'Bank',
                'type' => 'ist',
            ]);

        Entry::create([
            'user_id' => $user->id,
            'month_id' => $month->id,
            'entry_date' => '2026-06-10',
            'type' => 'income',
            'income_source' => 'expected',
            'direction' => 'in',
            'amount' => 1800,
            'account_id' => $forecastAccount->id,
            'status' => 'open',
            'description' => 'Projektzahlung',
        ]);

        Entry::create([
            'user_id' => $user->id,
            'month_id' => $month->id,
            'entry_date' => '2026-06-03',
            'due_date' => now()->subDays(2)->toDateString(),
            'type' => 'expense',
            'direction' => 'out',
            'amount' => 900,
            'account_id' => $bankAccount->id,
            'status' => 'open',
            'description' => 'Miete',
        ]);

        Entry::create([
            'user_id' => $user->id,
            'month_id' => $month->id,
            'entry_date' => '2026-06-05',
            'type' => 'fixcost',
            'direction' => 'out',
            'amount' => 450,
            'account_id' => $bankAccount->id,
            'status' => 'open',
            'description' => 'Versicherung',
        ]);

        return $month;
    }
}
