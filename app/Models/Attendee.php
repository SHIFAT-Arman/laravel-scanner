<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $uuid
 * @property string|null $name
 * @property string|null $aiub_id
 * @property string|null $email
 * @property string|null $phone_no
 * @property string|null $gender
 * @property string|null $size
 * @property string|null $dept
 * @property string|null $amount_paid
 * @property float|null $membership_cost
 * @property bool $received
 * @property Carbon|null $received_at
 * @property string|null $invitation_email_status
 * @property Carbon|null $invitation_email_sent_at
 * @property string|null $invitation_email_error
 */
#[Fillable(['uuid', 'name', 'aiub_id', 'email', 'phone_no', 'gender', 'size', 'dept', 'amount_paid', 'membership_cost'])]
class Attendee extends Model
{
    protected $table = 'attendees';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received' => 'boolean',
            'received_at' => 'datetime',
            'invitation_email_sent_at' => 'datetime',
        ];
    }
}
