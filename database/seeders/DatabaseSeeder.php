<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Month;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'is_admin' => false,
                'accent_color' => '#2f6f3e',
                'employment_type' => 'employed',
                'email_verified_at' => now(),
            ]
        );

        Account::createDefaultsForUser($user);
        $this->seedInitialMonths($user);
    }

    private function seedInitialMonths(User $user): void
    {
        if (Month::forUser($user)->exists()) {
            return;
        }

        $start = now()->startOfMonth();

        for ($offset = 0; $offset < 12; $offset++) {
            $monthStart = $start->copy()->addMonthsNoOverflow($offset)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $name = ucfirst(str_replace('.', '', $monthStart->locale(app()->getLocale())->translatedFormat('M Y')));

            Month::create([
                'user_id' => $user->id,
                'name' => $name,
                'date_from' => $monthStart->toDateString(),
                'date_to' => $monthEnd->toDateString(),
                'daily_living_cost' => 0,
                'is_current' => $offset === 0,
            ]);
        }
    }
}
