<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassRecurringBookingsDaysOfWeek extends Model
{
    use HasFactory;

    protected $primaryKey = 'class_recurring_booking_id';

    protected $table = 'class_recurring_bookings_days_of_week';

    protected $guarded = [];

    public $timestamps = false;

    public function day()
    {
        return $this->belongsTo(ClassDay::class, 'class_day_id');
    }
}
