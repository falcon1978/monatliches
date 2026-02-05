<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\AccountBalance;
use App\Models\Entry;
use App\Models\Month;
use App\Models\RecurringTemplate;
use App\Models\User;
use App\Policies\AccountBalancePolicy;
use App\Policies\AccountPolicy;
use App\Policies\EntryPolicy;
use App\Policies\MonthPolicy;
use App\Policies\RecurringTemplatePolicy;
use App\Policies\UserPolicy;
use App\Services\UpdateFeedService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(AccountBalance::class, AccountBalancePolicy::class);
        Gate::policy(Entry::class, EntryPolicy::class);
        Gate::policy(Month::class, MonthPolicy::class);
        Gate::policy(RecurringTemplate::class, RecurringTemplatePolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            if (! $user) {
                return;
            }

            $months = Month::forUser($user)
                ->visible()
                ->orderBy('date_from')
                ->get(['id', 'name', 'date_from', 'date_to']);

            if ($months->isEmpty()) {
                $view->with('monthBand', [
                    'months' => $months,
                    'currentMonthId' => null,
                ]);
                return;
            }

            $routeMonth = request()->route('month');
            $routeEntry = request()->route('entry');

            $currentMonth = $routeMonth instanceof Month ? $routeMonth : null;
            if (! $currentMonth && $routeEntry instanceof Entry) {
                $currentMonth = $routeEntry->month;
            }

            $view->with('monthBand', [
                'months' => $months,
                'currentMonthId' => $currentMonth?->id,
            ]);
        });

        View::composer('layouts.navigation', function ($view) {
            if (app()->runningInConsole()) {
                return;
            }

            $user = auth()->user();
            if (! $user || ! $user->is_admin) {
                return;
            }

            $info = app(UpdateFeedService::class)->getCachedInfo();
            if (! $info || empty($info['update_available'])) {
                return;
            }

            $view->with('navUpdateInfo', $info);
        });
    }
}
