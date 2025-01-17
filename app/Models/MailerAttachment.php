<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class MailerAttachment extends Model
{
    use HasFactory;

    protected $table = 'crm_attachments';

    protected $primaryKey = 'attachment_id';

    protected $guarded = [];

    public $timestamps = false;

    public function mailer(): BelongsTo
    {
        return $this->belongsTo(Mailer::class, 'mailer_id');
    }

    public function attachmentUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attachment_path ? Storage::disk('private')->temporaryUrl($this->attachment_path, now()->addMinutes(5)) : null,
        );
    }
}
