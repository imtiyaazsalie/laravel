<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduledEmailAttachment extends Model
{
    use HasFactory;

    protected $table = 'scheduled_email_attachments';

    protected $primaryKey = 'scheduled_email_attachment_id';

    public $timestamps = false;

    protected $guarded = [];
}
