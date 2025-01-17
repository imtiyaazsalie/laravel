<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LinkedUser extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function linkedUser()
    {
        return $this->belongsTo(User::class, 'linked_user_id');
    }

    public function scopeShared(Builder $query, $shared)
    {
        if (! $user = auth()->user()) {
            return $query;
        }

        if ($shared) {
            $query->where('linked_user_id', '=', $user->getAuthIdentifier());
        } else {
            $query->where('user_id', '=', $user->getAuthIdentifier());
        }

    }
}
