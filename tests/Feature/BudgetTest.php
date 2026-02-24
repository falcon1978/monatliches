<?php

namespace Tests\Feature;

use App\Models\Entry;
use App\Models\Month;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_own_month(): void
    {
        $user = User::factory()->create();
        $month = Month::create([
            'user_id' => $user->id,
            'name' => 'Feb 2026',
            'date_from' => '2026-02-01',
            'date_to' => '2026-02-28',
            'daily_living_cost' => 50,
        ]);

        $this->actingAs($user)
            ->get(route('months.show', $month))
            ->assertOk()
            ->assertSee('Feb 2026');
    }

    public function test_authenticated_user_cannot_view_other_users_month(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $month = Month::create([
            'user_id' => $owner->id,
            'name' => 'Mar 2026',
            'date_from' => '2026-03-01',
            'date_to' => '2026-03-31',
            'daily_living_cost' => 40,
        ]);

        $this->actingAs($other)
            ->get(route('months.show', $month))
            ->assertForbidden();
    }

    public function test_income_payment_creates_transfer_entries_and_updates_open_amount(): void
    {
        $user = User::factory()->create();
        $month = Month::create([
            'user_id' => $user->id,
            'name' => 'Apr 2026',
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
            'daily_living_cost' => 35,
        ]);

        $forecastAccount = $user->accounts()->where('type', 'forecast')->firstOrFail();
        $bankAccount = $user->accounts()->where('type', 'ist')->firstOrFail();

        $income = Entry::create([
            'user_id' => $user->id,
            'month_id' => $month->id,
            'entry_date' => now()->toDateString(),
            'type' => 'income',
            'direction' => 'in',
            'amount' => 100,
            'account_id' => $forecastAccount->id,
            'status' => 'open',
            'description' => 'Test Einnahme',
        ]);

        $this->actingAs($user)
            ->post(route('months.income-payments.store', $month), [
                'entry_id' => $income->id,
                'amount' => 40,
                'target_account_id' => $bankAccount->id,
            ])
            ->assertRedirect();

        $transfers = Entry::where('type', 'transfer')->get();
        $this->assertCount(2, $transfers);

        $out = $transfers->firstWhere('direction', 'out');
        $in = $transfers->firstWhere('direction', 'in');

        $this->assertNotNull($out);
        $this->assertNotNull($in);
        $this->assertSame($forecastAccount->id, $out->account_id);
        $this->assertSame($bankAccount->id, $in->account_id);
        $this->assertSame($income->id, $out->related_entry_id);
        $this->assertSame($income->id, $in->related_entry_id);

        $income->refresh()->load('relatedTransfersOut');

        $this->assertSame(60.0, $income->open_amount);
        $this->assertSame('partial', $income->status);
    }

    public function test_negative_expected_income_stays_open_after_update(): void
    {
        $user = User::factory()->create();
        $month = Month::create([
            'user_id' => $user->id,
            'name' => 'Mai 2026',
            'date_from' => '2026-05-01',
            'date_to' => '2026-05-31',
            'daily_living_cost' => 35,
        ]);

        $clearingAccount = $user->accounts()->create([
            'name' => 'Verrechnung Test',
            'type' => 'clearing',
        ]);

        $income = Entry::create([
            'user_id' => $user->id,
            'month_id' => $month->id,
            'entry_date' => now()->toDateString(),
            'type' => 'income',
            'income_source' => 'expected',
            'direction' => 'in',
            'amount' => 120,
            'account_id' => $clearingAccount->id,
            'status' => 'open',
            'description' => 'Verrechnungsposten',
        ]);

        $this->actingAs($user)
            ->put(route('entries.update', $income), [
                'entry_date' => now()->toDateString(),
                'description' => 'Verrechnungsposten',
                'amount' => '-50.00',
                'status' => 'open',
                'account_id' => $clearingAccount->id,
            ])
            ->assertRedirect(route('months.show', $month));

        $income->refresh()->load('relatedTransfersOut');

        $this->assertSame(-50.0, (float) $income->amount);
        $this->assertSame(-50.0, $income->open_amount);
        $this->assertSame('open', $income->status);
    }

    public function test_negative_expected_income_payment_is_booked_with_target_account(): void
    {
        $user = User::factory()->create();
        $month = Month::create([
            'user_id' => $user->id,
            'name' => 'Jun 2026',
            'date_from' => '2026-06-01',
            'date_to' => '2026-06-30',
            'daily_living_cost' => 35,
        ]);

        $clearingAccount = $user->accounts()->create([
            'name' => 'Verrechnung Test',
            'type' => 'clearing',
        ]);
        $bankAccount = $user->accounts()->where('type', 'ist')->firstOrFail();

        $income = Entry::create([
            'user_id' => $user->id,
            'month_id' => $month->id,
            'entry_date' => now()->toDateString(),
            'type' => 'income',
            'income_source' => 'expected',
            'direction' => 'in',
            'amount' => -100,
            'account_id' => $clearingAccount->id,
            'status' => 'open',
            'description' => 'Negativer Verrechnungsposten',
        ]);

        $this->actingAs($user)
            ->post(route('months.income-payments.store', $month), [
                'entry_id' => $income->id,
                'amount' => 40,
                'target_account_id' => $bankAccount->id,
            ])
            ->assertRedirect();

        $income->refresh()->load('relatedTransfersOut');

        $this->assertSame(-60.0, $income->open_amount);
        $this->assertSame('partial', $income->status);

        $this->actingAs($user)
            ->post(route('months.income-payments.store', $month), [
                'entry_id' => $income->id,
                'amount' => 60,
                'target_account_id' => $bankAccount->id,
            ])
            ->assertRedirect();

        $income->refresh()->load('relatedTransfersOut');

        $this->assertSame(0.0, $income->open_amount);
        $this->assertSame('paid', $income->status);
    }
}
