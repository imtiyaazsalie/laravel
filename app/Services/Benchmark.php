<?php

namespace App\Services;

use App\Models\Exercise;
use App\Models\Leaderboard;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class Benchmark
{
    public function captureScoreOnLeaderboard(User $user, Exercise $exercise, $score): void
    {

        $hasScoreImproved = false;

        // check if user has a previous score for this benchmark
        $leaderBoardResult = Leaderboard::query()
            ->select(DB::raw('0 + score as score'))
            ->join('users', 'users.user_id', '=', 'leaderboard.user_id')
            ->join('user_to_box', 'users.user_id', '=', 'user_to_box.user_id')
            ->where('leaderboard.exercise_id', '=', $exercise->getKey())
            ->where('leaderboard.user_id', '=', $user->getKey())
            ->whereBetween(DB::raw('DATE(now())'), ['user_to_box.effective_date', 'user_to_box.end_date'])
            ->where('leaderboard.is_active', '=', true)
            ->first();

        if ($leaderBoardResult) {
            // Get the score and compare with current
            if ((float) $leaderBoardResult->score < (float) $score) {
                // Delete current item
                $leaderBoardResult->delete();
                $hasScoreImproved = true;
            }
        } else {
            $hasScoreImproved = true;
        }

        if ($hasScoreImproved) {
            // Capture on leaderboard if no previous result or is better than their previous result
            $newLeaderboardResult = Leaderboard::query()->create([
                'user_id' => $user->getKey(),
                'exercise_id' => $exercise->getKey(),
                'score' => $score,
                'rank' => 0,
            ]);
        }
    }
}
