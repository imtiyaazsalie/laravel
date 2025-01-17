<?php

namespace App\Models;

use App\Exceptions\MailerRecipientTypeException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class MailerRecipient extends Model
{
    use HasFactory, Notifiable;

    protected $table = 'crm_recipients';

    protected $primaryKey = 'recipient_id';

    protected $guarded = [];

    public $timestamps = true;

    public const CREATED_AT = 'created_on';

    public const UPDATED_AT = 'updated_on';

    public function scopeUser(Builder $query, $userId): Builder
    {
        return $query->where('ref_entity_name', (new User())->getTable())
            ->where('ref_entity_id', $userId)
            ->where('type', 'member');
    }

    public function mailTo(): array
    {
        if ($this->ref_entity_name === (new User())->getTable()) {
            return ['address' => $this->user->email, 'name' => $this->user->full_name];
        }

        return ['address' => $this->email, 'name' => $this->name];
    }

    public function getMailToName(): ?string
    {
        return $this->mailTo()['name'];
    }

    public function getMailToAddress(): ?string
    {
        return $this->mailTo()['address'];
    }

    public function user()
    {
        return $this->hasOneThrough(
            User::class,
            MailerRecipient::class,
            'ref_entity_id',
            'user_id',
            'ref_entity_id',
            'ref_entity_id',
        )->where('ref_entity_name', (new User())->getTable())->withTrashed();
    }

    public function getMobileNumber(): ?string
    {
        if ($this->ref_entity_name === (new User())->getTable()) {
            return $this->user->mobile;
        }

        return $this->mobile;
    }

    public function toNameArray(): array|bool
    {
        switch ($this->type) {
            case 'coach':
            case 'member':
            case 'lead-member':

                if (! $this->user) {
                    return false;
                }

                return [
                    'name' => $this->user->name,
                    'surname' => $this->user->surname,
                ];
            case 'non-member':
                return [
                    'name' => $this->name,
                    'surname' => '',
                ];
            default:
                throw new MailerRecipientTypeException("Recipient type $this->type unknown");
        }
    }
}
