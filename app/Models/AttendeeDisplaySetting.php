<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property array<int, string> $fields */
#[Fillable(['fields'])]
class AttendeeDisplaySetting extends Model
{
    public const array DEFAULT_FIELDS = ['name', 'aiub_id'];

    public const array SUPPORTED_FIELDS = [
        'name' => 'Name',
        'aiub_id' => 'AIUB ID',
        'email' => 'Email',
        'phone_no' => 'Phone number',
        'gender' => 'Gender',
        'size' => 'T-shirt size',
        'dept' => 'Department',
        'amount_paid' => 'Amount paid',
        'membership_cost' => 'Membership cost',
    ];

    public $timestamps = false;

    /**
     * @return array<int, string>
     */
    public static function enabledFields(): array
    {
        $setting = self::query()->find(1);

        return $setting instanceof self ? $setting->fields : self::DEFAULT_FIELDS;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['fields' => 'array'];
    }
}
