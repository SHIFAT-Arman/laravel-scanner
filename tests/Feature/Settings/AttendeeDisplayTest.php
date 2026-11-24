<?php

use App\Models\AttendeeDisplaySetting;
use App\Models\User;
use Livewire\Livewire;

test('only admins can open attendee display settings', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)->get(route('attendee-display.edit'))->assertForbidden();
});

test('admin can choose supported attendee display fields', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test('pages::settings.attendee-display')
        ->set('fields', ['name', 'email'])
        ->call('save')
        ->assertHasNoErrors();

    expect(AttendeeDisplaySetting::enabledFields())->toBe(['name', 'email']);
});

test('unsupported attendee fields are rejected', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test('pages::settings.attendee-display')
        ->set('fields', ['name', 'received_at'])
        ->call('save')
        ->assertHasErrors(['fields.1']);

    expect(AttendeeDisplaySetting::enabledFields())->toBe(AttendeeDisplaySetting::DEFAULT_FIELDS);
});
