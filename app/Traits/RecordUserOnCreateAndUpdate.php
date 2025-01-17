<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use ReflectionClass;

trait RecordUserOnCreateAndUpdate
{
    public static function initializeRecordUserOnCreateAndUpdate(): void
    {
        if (app()->runningInConsole()) {
            return;
        }

        $constants = (new ReflectionClass(static::class))->getConstants();
        $created_by_id = Arr::get($constants, 'CREATED_BY_ID', 'created_by_id');
        $updated_by_id = Arr::get($constants, 'UPDATED_BY_ID', 'updated_by_id');

        $user_id = auth()?->user()?->getAuthIdentifier();

        if ($user_id) {
            if ($created_by_id) {
                static::creating(function (Model $model) use ($user_id, $created_by_id) {
                    $model->setAttribute($created_by_id, $user_id);
                });
            }

            if ($updated_by_id) {
                static::updating(function (Model $model) use ($user_id, $updated_by_id) {
                    $model->setAttribute($updated_by_id, $user_id);
                });
            }
        }
    }
}
