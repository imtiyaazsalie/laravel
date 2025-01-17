<?php

namespace Database\Seeders;

use App\Models\ExerciseCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExerciseCategorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ExerciseCategory::insert([
            [
                'exercise_category_id' => 1,
                'exercise_category_desc' => 'Strength',
                'is_active' => 0,
                'is_benchmark' => 0,
            ],
            [
                'exercise_category_id' => 2,
                'exercise_category_desc' => 'Endurance',
                'is_active' => 0,
                'is_benchmark' => 0,
            ],
            [
                'exercise_category_id' => 3,
                'exercise_category_desc' => 'Gymnastics',
                'is_active' => 0,
                'is_benchmark' => 0,
            ],
            [
                'exercise_category_id' => 4,
                'exercise_category_desc' => 'Metabolic Conditioning (Cardio)',
                'is_active' => 0,
                'is_benchmark' => 0,
            ],
            [
                'exercise_category_id' => 5,
                'exercise_category_desc' => 'Conditioning',
                'is_active' => 0,
                'is_benchmark' => 0,
            ],
            [
                'exercise_category_id' => 6,
                'exercise_category_desc' => 'Skill',
                'is_active' => 0,
                'is_benchmark' => 0,
            ],
            [
                'exercise_category_id' => 7,
                'exercise_category_desc' => 'Box Benchmark',
                'is_active' => 0,
                'is_benchmark' => 0,
            ],
            [
                'exercise_category_id' => 8,
                'exercise_category_desc' => 'Weightlifting',
                'is_active' => 0,
                'is_benchmark' => 0,
            ],
            [
                'exercise_category_id' => 9,
                'exercise_category_desc' => 'Core/Midline ',
                'is_active' => 0,
                'is_benchmark' => 0,
            ],
            [
                'exercise_category_id' => 10,
                'exercise_category_desc' => 'Mobility',
                'is_active' => 0,
                'is_benchmark' => 0,
            ],
            [
                'exercise_category_id' => 11,
                'exercise_category_desc' => 'Functional',
                'is_active' => 0,
                'is_benchmark' => 0,
            ],
            [
                'exercise_category_id' => 12,
                'exercise_category_desc' => 'CrsFt B Dessert',
                'is_active' => 0,
                'is_benchmark' => 0,
            ],
            [
                'exercise_category_id' => 13,
                'exercise_category_desc' => 'CFB Dessert',
                'is_active' => 0,
                'is_benchmark' => 0,
            ],
            [
                'exercise_category_id' => 14,
                'exercise_category_desc' => 'Bike-Fit',
                'is_active' => 0,
                'is_benchmark' => 0,
            ],
            [
                'exercise_category_id' => 15,
                'exercise_category_desc' => '',
                'is_active' => 0,
                'is_benchmark' => 0,
            ],
            [
                'exercise_category_id' => 16,
                'exercise_category_desc' => 'Sports',
                'is_active' => 0,
                'is_benchmark' => 0,
            ],
            [
                'exercise_category_id' => 17,
                'exercise_category_desc' => 'Weightlifting',
                'is_active' => 1,
                'is_benchmark' => 1,
            ],
            [
                'exercise_category_id' => 18,
                'exercise_category_desc' => 'Gymnastics',
                'is_active' => 1,
                'is_benchmark' => 1,
            ],
            [
                'exercise_category_id' => 19,
                'exercise_category_desc' => 'Metabolic Conditioning',
                'is_active' => 0,
                'is_benchmark' => 1,
            ],
            [
                'exercise_category_id' => 20,
                'exercise_category_desc' => 'Endurance',
                'is_active' => 0,
                'is_benchmark' => 1,
            ],
            [
                'exercise_category_id' => 21,
                'exercise_category_desc' => 'CrossFit WODs',
                'is_active' => 1,
                'is_benchmark' => 1,
            ],
            [
                'exercise_category_id' => 22,
                'exercise_category_desc' => 'CrossFit Hero WODs',
                'is_active' => 1,
                'is_benchmark' => 1,
            ],
            [
                'exercise_category_id' => 23,
                'exercise_category_desc' => 'Monostructural/Cardio',
                'is_active' => 1,
                'is_benchmark' => 1,
            ],
            [
                'exercise_category_id' => 24,
                'exercise_category_desc' => 'CrossFit Open WODs',
                'is_active' => 1,
                'is_benchmark' => 1,
            ],
            [
                'exercise_category_id' => 25,
                'exercise_category_desc' => 'Survival of the Boxes',
                'is_active' => 0,
                'is_benchmark' => 1,
            ],
            [
                'exercise_category_id' => 26,
                'exercise_category_desc' => 'Support your local Box',
                'is_active' => 0,
                'is_benchmark' => 1,
            ],
            [
                'exercise_category_id' => 27,
                'exercise_category_desc' => 'United in Movement',
                'is_active' => 0,
                'is_benchmark' => 1,
            ],
            [
                'exercise_category_id' => 28,
                'exercise_category_desc' => 'Test 1 not benchmark',
                'is_active' => 1,
                'is_benchmark' => 1,
            ],
            [
                'exercise_category_id' => 29,
                'exercise_category_desc' => 'Test edited',
                'is_active' => 1,
                'is_benchmark' => 1,
            ],
        ]);
    }
}
