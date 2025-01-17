<?php

namespace App\Rules;

use App\Models\Tag;
use App\Traits\ValidatesIds;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TagRule implements ValidationRule
{
    use ValidatesIds;

    public function __construct(
        public string $type,
        public ?string $ownerModel = null,
        public ?string $ownerId = null,
    ) {

    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            $fail('The :attribute must be a valid array of integers.');

            return;
        }
        // Allow empty arrays, for deletion
        if (empty(array_filter($value))) {
            return;
        }

        if (! $ids = $this->validateIds($value)) {
            $fail('The :attribute must be a valid string, integer or array<string|int>');

            return;
        }

        $tagCount = Tag::query()
            ->where('type', $this->type)
            ->whereIn('id', $ids)
            ->when($this->ownerModel, function ($query) {
                $query->where('owner_model_id', $this->ownerId)
                    ->where(function ($query) {
                        $query->whereNull('owner_model')
                            ->orWhere('owner_model', $this->ownerModel);
                    });
            })->count();

        if ($tagCount !== count($ids)) {
            $fail('The :attribute must be valid tag IDs');

            return;
        }
    }
}
