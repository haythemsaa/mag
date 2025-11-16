<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DriverScoreResource;
use App\Models\Driver;
use App\Models\DriverScore;
use App\Services\DriverScoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Analytics
 *
 * APIs for analytics, scoring and predictions
 */
class AnalyticsController extends Controller
{
    public function __construct(protected DriverScoringService $scoringService)
    {
    }

    /**
     * Get driver scores
     *
     * Get scores for drivers in the organization.
     *
     * @queryParam driver_id int Filter by driver ID. Example: 1
     * @queryParam year int Filter by year. Example: 2025
     * @queryParam month int Filter by month (1-12). Example: 11
     * @queryParam per_page int Number of items per page (default: 15). Example: 20
     */
    public function driverScores(Request $request): JsonResponse
    {
        $query = DriverScore::where('organization_id', $request->user()->organization_id)
            ->with(['driver'])
            ->latest('year')
            ->latest('month');

        if ($request->has('driver_id')) {
            $query->where('driver_id', $request->driver_id);
        }

        if ($request->has('year')) {
            $query->where('year', $request->year);
        }

        if ($request->has('month')) {
            $query->where('month', $request->month);
        }

        $perPage = $request->input('per_page', 15);
        $scores = $query->paginate($perPage);

        return response()->json([
            'data' => DriverScoreResource::collection($scores->items()),
            'meta' => [
                'current_page' => $scores->currentPage(),
                'total' => $scores->total(),
                'per_page' => $scores->perPage(),
                'last_page' => $scores->lastPage(),
            ],
        ]);
    }

    /**
     * Get driver score history
     *
     * Get score history for a specific driver.
     *
     * @urlParam driver int required The driver ID. Example: 1
     * @queryParam months int Number of months of history (default: 12). Example: 6
     */
    public function driverHistory(Request $request, Driver $driver): JsonResponse
    {
        if ($driver->organization_id !== $request->user()->organization_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $months = $request->input('months', 12);
        $history = $this->scoringService->getDriverHistory($driver, $months);

        return response()->json([
            'data' => DriverScoreResource::collection($history),
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'license_number' => $driver->license_number,
            ],
        ]);
    }

    /**
     * Calculate driver score
     *
     * Calculate or recalculate score for a driver for a specific period.
     *
     * @urlParam driver int required The driver ID. Example: 1
     * @bodyParam year int required Year. Example: 2025
     * @bodyParam month int required Month (1-12). Example: 11
     */
    public function calculateScore(Request $request, Driver $driver): JsonResponse
    {
        if ($driver->organization_id !== $request->user()->organization_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $score = $this->scoringService->calculateScore(
            $driver,
            $validated['year'],
            $validated['month']
        );

        return response()->json([
            'message' => 'Score calculated successfully',
            'data' => new DriverScoreResource($score),
        ]);
    }

    /**
     * Get leaderboard
     *
     * Get top performing drivers for a period.
     *
     * @queryParam year int Year (default: current). Example: 2025
     * @queryParam month int Month (default: current). Example: 11
     * @queryParam limit int Number of top drivers (default: 10). Example: 20
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);
        $limit = $request->input('limit', 10);

        $leaderboard = $this->scoringService->getLeaderboard(
            $request->user()->organization_id,
            $year,
            $month,
            $limit
        );

        return response()->json([
            'data' => DriverScoreResource::collection($leaderboard),
            'period' => [
                'year' => $year,
                'month' => $month,
                'label' => date('F Y', mktime(0, 0, 0, $month, 1, $year)),
            ],
        ]);
    }

    /**
     * Get scoring statistics
     *
     * Get overall scoring statistics for the organization.
     *
     * @queryParam year int Year (default: current). Example: 2025
     * @queryParam month int Month (default: current). Example: 11
     */
    public function scoringStatistics(Request $request): JsonResponse
    {
        $year = $request->input('year', now()->year);
        $month = $request->input('month', now()->month);

        $scores = DriverScore::where('organization_id', $request->user()->organization_id)
            ->where('year', $year)
            ->where('month', $month)
            ->get();

        if ($scores->isEmpty()) {
            return response()->json([
                'message' => 'No scores found for this period',
                'period' => ['year' => $year, 'month' => $month],
            ]);
        }

        $statistics = [
            'period' => [
                'year' => $year,
                'month' => $month,
                'label' => date('F Y', mktime(0, 0, 0, $month, 1, $year)),
            ],
            'total_drivers' => $scores->count(),
            'average_score' => round($scores->avg('total_score'), 2),
            'highest_score' => round($scores->max('total_score'), 2),
            'lowest_score' => round($scores->min('total_score'), 2),
            'score_distribution' => [
                'excellent' => $scores->filter(fn ($s) => $s->total_score >= 90)->count(),
                'good' => $scores->filter(fn ($s) => $s->total_score >= 70 && $s->total_score < 90)->count(),
                'average' => $scores->filter(fn ($s) => $s->total_score >= 60 && $s->total_score < 70)->count(),
                'poor' => $scores->filter(fn ($s) => $s->total_score < 60)->count(),
            ],
            'trends' => [
                'improving' => $scores->where('trend', 'improving')->count(),
                'declining' => $scores->where('trend', 'declining')->count(),
                'stable' => $scores->where('trend', 'stable')->count(),
            ],
            'component_averages' => [
                'safety' => round($scores->avg('safety_score'), 2),
                'efficiency' => round($scores->avg('efficiency_score'), 2),
                'compliance' => round($scores->avg('compliance_score'), 2),
                'behavior' => round($scores->avg('behavior_score'), 2),
            ],
            'top_performers' => $scores->sortByDesc('total_score')
                ->take(5)
                ->map(fn ($score) => [
                    'driver_id' => $score->driver_id,
                    'driver_name' => $score->driver?->name,
                    'score' => $score->total_score,
                    'rank' => $score->organization_rank,
                ])
                ->values(),
            'bottom_performers' => $scores->sortBy('total_score')
                ->take(5)
                ->map(fn ($score) => [
                    'driver_id' => $score->driver_id,
                    'driver_name' => $score->driver?->name,
                    'score' => $score->total_score,
                    'rank' => $score->organization_rank,
                ])
                ->values(),
        ];

        return response()->json($statistics);
    }
}
