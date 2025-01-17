<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leaderboard\DeleteLeaderboardRequest;
use App\Http\Requests\Leaderboard\ListLeaderboardRequest;
use App\Http\Resources\LeaderboardResource;
use App\Models\Leaderboard;
use App\Services\LeaderboardService;

class LeaderboardController extends Controller
{
    public function __construct(private readonly LeaderboardService $leaderboardService)
    {
    }

    public function list(ListLeaderboardRequest $request)
    {
        return LeaderboardResource::collection($this->leaderboardService->listByRequest());
    }

    public function delete(DeleteLeaderboardRequest $request, Leaderboard $leaderboard)
    {
        $this->leaderboardService->delete($leaderboard);

        return response()->noContent();
    }
}
