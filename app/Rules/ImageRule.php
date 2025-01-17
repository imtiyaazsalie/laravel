<?php

namespace App\Rules;

use Closure;
use Exception;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;

class ImageRule implements ValidationRule
{
    /**
     * Create a new rule instance.
     */
    public function __construct()
    {
        // Constructor
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $validator = Validator::make(
                ['image' => $value],
                [
                    'image' => [
                        'required',
                        'file',  // Validates that it's a validly uploaded file
                        'image', // Validates that it's a valid image file
                        'mimes:jpeg,png,webp',
                        'max:4000', // Size in kilobytes
                        'dimensions:max_width=1920,max_height=1080',
                        'extensions:jpeg,png,webp,jpg',
                    ],
                ], [
                    'max' => 'The :attribute may not be greater than :max kilobytes.',
                    'dimensions' => 'The :attribute must be at most :max_width pixels wide and :max_height pixels high.',
                ]);

            if ($validator->fails()) {
                $fail($validator->errors()->first('image'));
            }
        } catch (Exception $e) {
            $fail('Failed to validate image: '.$e->getMessage());
        }
    }
}
