<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskAssignee extends Model
{
    use HasFactory;

    protected $table = 'task_assignees';

    protected $primaryKey = 'task_assignment_id';

    protected $guarded = [];

    public $timestamps = false;
}
