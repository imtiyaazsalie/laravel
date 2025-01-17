<?php

namespace App\Traits;

trait ValidatesIds
{
    /**
     * Cast ID/s to array or null if invalid.
     */
    public function validateIds(string|int|array $value): ?array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        $ids = array_filter((array) $value, function ($id) {
            if (is_int($id) || is_string($id)) {
                return true;
            }

            return false;
        });

        if (count($ids) === count((array) $value)) {
            return array_unique($ids);
        }

        return null;
    }
}
