<?php

namespace App\Http\Controllers;

use App\Models\Month;
use App\Services\BudgetInsightsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class MonthInsightsController extends Controller
{
    public function __invoke(
        Request $request,
        Month $month,
        BudgetInsightsService $insightsService
    ): JsonResponse {
        $this->authorize('view', $month);

        $user = $request->user();
        if ($month->user_id !== $user->id) {
            abort(403);
        }

        try {
            $insights = $request->boolean('refresh')
                ? $insightsService->refreshMonth($month, $user)
                : $insightsService->analyzeMonth($month, $user);

            return response()->json($insights);
        } catch (Throwable $exception) {
            Log::error('Month insights request failed.', [
                'month_id' => $month->id,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'summary' => 'Die Analyse konnte gerade nicht geladen werden. Bitte versuche es gleich erneut.',
                'prioritized_findings' => [],
                'suggested_fixes' => [],
                'questions' => [],
                'source' => 'error',
                'generated_at' => now()->toIso8601String(),
            ]);
        }
    }
}
