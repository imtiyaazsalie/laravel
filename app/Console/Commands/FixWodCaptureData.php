<?php

namespace App\Console\Commands;

use App\Models\WodCapture;
use App\Models\WodCaptureComments;
use App\Models\WodCaptureLikes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixWodCaptureData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-wod-capture-data {--fix-data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /**
         * This command fixes wod_capture and wod_capture_exercise data created due to a bug which created each exercise record with a new wod capture.
         *
         * To check if this needs to run. The query below finds records which have duplicates in raw SQL. Removing the group by will return the duplicates as well.
         *
         * You can also run without the --fix-data flag to do a dry run and not change any records.
         */

        // SELECT wod_capture.* FROM wod_capture
        // WHERE DATE(wod_capture.dt_added) > '2024-06-09'
        //     AND 1 < (SELECT COUNT(*) FROM wod_to_exercise WHERE wod_id = wod_capture.wod_id)
        //     AND 1 = (SELECT COUNT(*) FROM wod_capture_exercises WHERE wod_capture_exercises.wod_capture_id = wod_capture.wod_capture_id)
        // AND 0 < (SELECT COUNT(*) FROM wod_capture AS wc
        //     WHERE wc.wod_id = wod_capture.wod_id
        //         AND wc.user_id = wod_capture.user_id
        //         AND wc.wod_capture_id != wod_capture.wod_capture_id)
        // GROUP BY wod_id,user_id;

        $dummyRun = ! $this->option('fix-data');

        $wodCaptures = WodCapture::query()
            //between the dates the bug was present
            ->whereDate('dt_added', '>', '2024-06-09')
            ->whereDate('dt_added', '<=', '2024-06-20')
            // where the WOD has more than one exercise
            ->whereRaw('1 < (SELECT COUNT(*) FROM wod_to_exercise WHERE wod_id = wod_capture.wod_id)')
            //where the capture has only one wod capture exercise
            ->whereRaw('1 = (SELECT COUNT(*) FROM wod_capture_exercises WHERE wod_capture_exercises.wod_capture_id = wod_capture.wod_capture_id)')
            //where there is another capture for the same user and wod
            ->whereRaw('0 < (SELECT COUNT(*) FROM wod_capture AS wc WHERE wc.wod_id = wod_capture.wod_id AND wc.user_id = wod_capture.user_id AND wc.wod_capture_id != wod_capture.wod_capture_id)')
            //get the first one, exclude the duplicates.
            ->groupByRaw('wod_id,user_id')
            ->with('likes', 'comments', 'exercises')
            ->get();

        foreach ($wodCaptures as $capture) {
            DB::transaction(function () use ($capture, $dummyRun) {

                // find duplicate capture
                $duplicate = WodCapture::query()
                    ->where('wod_id', $capture->wod_id)
                    ->where('user_id', $capture->user_id)
                    ->where('wod_capture_id', '!=', $capture->getKey())
                    ->with('exercises', 'likes', 'comments')
                    ->first();

                if (! $duplicate) {
                    $this->error("No duplicate found for wod capture id: {$capture->getKey()}");

                    return;
                }

                $this->line("Found duplicate Wod Capture: {$duplicate->getKey()} for Wod Capture: {$capture->getKey()}");

                //check for existing capture exercise data
                $currentExercises = $capture->exercises;

                // correct the capture exercise data
                foreach ($duplicate->exercises as $exercise) {

                    //skip and delete duplicate wod capture exercise
                    if ($currentExercises->contains('exercise_id', $exercise->exercise_id)) {
                        $this->error("Manual intervention required: exercise ID {$exercise->exercise_id} already logged for WOD capture ID {$capture->getKey()}");

                        continue;
                    }

                    //move missing capture exercise data from duplicate to current
                    $this->info("Updating Wod Capture Exercise {$exercise->getKey()} to WOD Capture ID {$capture->getKey()}");

                    if (! $dummyRun) {
                        $exercise->update([
                            'wod_capture_id' => $capture->getKey(),
                        ]);
                    }
                }

                //move wod capture comments
                if ($duplicate->likes->isNotEmpty()) {
                    $this->info("Detected Wod Capture Likes on duplicate ID {$duplicate->getKey()}, moving to WOD Capture ID {$capture->getKey()}");

                    $likes = $duplicate->likes->filter(fn ($like) => ! $capture->likes->contains('user_id', $like->user_id));

                    if ($likes->isEmpty()) {
                        $this->info("No new likes found on duplicate ID {$duplicate->getKey()}");
                    } else {
                        $this->info("Moving likes from duplicate ID {$duplicate->getKey()} to WOD Capture ID {$capture->getKey()}");

                        if (! $dummyRun) {
                            WodCaptureLikes::query()
                                ->whereKey($likes->modelKeys())
                                ->update([
                                    'wod_capture_id' => $capture->getKey(),
                                ]);
                        }
                    }

                    //delete extra likes
                    if (! $dummyRun) {
                        WodCaptureLikes::query()
                            ->whereKey($duplicate->likes->whereNotIn('like_id', $likes->modelKeys())->modelKeys())
                            ->delete();
                    }
                }

                //move wod capture comments
                if ($duplicate->comments->isNotEmpty()) {
                    $this->info("Detected Wod Capture Comments on duplicate ID {$duplicate->getKey()}, moving to WOD Capture ID {$capture->getKey()}");

                    if (! $dummyRun) {
                        WodCaptureComments::query()
                            ->whereKey($duplicate->comments->modelKeys())
                            ->update([
                                'wod_capture_id' => $capture->getKey(),
                            ]);
                    }
                }

                //delete duplicate wod capture
                $this->info("Deleting duplicate Wod Capture: {$duplicate->getKey()}.");

                if (! $dummyRun) {
                    $duplicate->forceDelete();
                }

            });
        }

    }
}
