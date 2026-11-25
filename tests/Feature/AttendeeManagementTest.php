<?php

use App\Actions\Attendees\GenerateAttendeeQr;
use App\Jobs\SendMemberOrientationInvitation;
use App\Mail\MemberOrientationInvitation;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Schema::dropIfExists('attendees');
    Schema::create('attendees', function (Blueprint $table) {
        $table->text('uuid')->primary();
        $table->text('name')->nullable();
        $table->text('aiub_id')->nullable();
        $table->text('email')->nullable();
        $table->text('outlook_email')->nullable();
        $table->text('phone_no')->nullable();
        $table->text('gender')->nullable();
        $table->text('size')->nullable();
        $table->text('dept')->nullable();
        $table->text('amount_paid')->nullable();
        $table->double('membership_cost')->nullable();
        $table->boolean('received')->default(false);
        $table->timestamp('received_at')->nullable();
        $table->string('invitation_email_status')->nullable();
        $table->timestamp('invitation_email_sent_at')->nullable();
        $table->text('invitation_email_error')->nullable();
    });
});

test('staff cannot access attendee management', function () {
    $staff = User::factory()->create(['role' => 'staff']);

    $this->actingAs($staff)->get(route('attendees.index'))->assertForbidden();
});

test('admin can search and filter attendees', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    DB::table('attendees')->insert([
        ['uuid' => (string) Str::uuid(), 'name' => 'Ada Lovelace', 'received' => true],
        ['uuid' => (string) Str::uuid(), 'name' => 'Grace Hopper', 'received' => false],
    ]);

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->set('search', 'Ada')
        ->assertSeeText('Ada Lovelace')
        ->assertDontSeeText('Grace Hopper')
        ->set('search', '')
        ->set('status', 'unreceived')
        ->assertSeeText('Grace Hopper')
        ->assertDontSeeText('Ada Lovelace');
});

test('admin can show one hundred attendees per page', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    DB::table('attendees')->insert(collect(range(1, 101))->map(fn (int $number): array => [
        'uuid' => (string) Str::uuid(),
        'name' => sprintf('Attendee %03d', $number),
    ])->all());

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->set('perPage', 100)
        ->assertSeeText('Attendee 100')
        ->assertDontSeeText('Attendee 101');
});

test('admin can reset a received attendee', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert([
        'uuid' => $uuid,
        'received' => true,
        'received_at' => '2026-11-24 12:00:00',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->call('resetReceipt', $uuid)
        ->assertHasNoErrors();

    $this->assertDatabaseHas('attendees', [
        'uuid' => $uuid,
        'received' => false,
        'received_at' => null,
    ]);
});

test('admin can create an attendee with validated fields', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $uuid = (string) Str::uuid();

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->set('form.uuid', $uuid)
        ->set('form.name', 'Ada Lovelace')
        ->set('form.email', 'ada@example.com')
        ->set('form.membership_cost', '500.50')
        ->set('form.received', true)
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('attendees', [
        'uuid' => $uuid,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'membership_cost' => 500.50,
        'received' => false,
        'received_at' => null,
    ]);
});

test('duplicate UUIDs and invalid attendee fields are rejected', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert(['uuid' => $uuid]);

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->set('form.uuid', $uuid)
        ->set('form.email', 'not-an-email')
        ->set('form.membership_cost', '-1')
        ->call('save')
        ->assertHasErrors(['form.uuid', 'form.email', 'form.membership_cost']);

    expect(DB::table('attendees')->count())->toBe(1);
});

test('attendee names are escaped in management results', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    DB::table('attendees')->insert([
        'uuid' => (string) Str::uuid(),
        'name' => '<script>alert("xss")</script>',
    ]);

    $html = Livewire::actingAs($admin)->test('pages::attendees')->html();

    expect($html)
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>alert("xss")</script>');
});

test('admin can edit attendee details without changing receipt state', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert([
        'uuid' => $uuid,
        'name' => 'Old name',
        'received' => true,
        'received_at' => '2026-11-24 12:00:00',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->call('edit', $uuid)
        ->set('form.name', 'New name')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('attendees', [
        'uuid' => $uuid,
        'name' => 'New name',
        'received' => true,
        'received_at' => '2026-11-24 12:00:00',
    ]);
});

test('admin can delete an attendee', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert(['uuid' => $uuid]);

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->call('delete', $uuid)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('attendees', ['uuid' => $uuid]);
});

test('admin can export attendee fields and receipt state', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert([
        'uuid' => $uuid,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'received' => true,
        'received_at' => '2026-11-24 12:00:00',
    ]);
    $stream = fopen('php://memory', 'r+');
    fputcsv($stream, ['uuid', 'name', 'aiub_id', 'email', 'phone_no', 'gender', 'size', 'dept', 'amount_paid', 'membership_cost', 'received', 'received_at'], escape: '');
    fputcsv($stream, [$uuid, 'Ada Lovelace', null, 'ada@example.com', null, null, null, null, null, null, true, '2026-11-24 12:00:00'], escape: '');
    rewind($stream);
    $expected = stream_get_contents($stream);
    fclose($stream);

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->call('export')
        ->assertFileDownloaded('attendees.csv', $expected, 'text/csv');
});

test('admin can import valid attendees', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $firstUuid = (string) Str::uuid();
    $secondUuid = (string) Str::uuid();
    $csv = "uuid,name,email,membership_cost\n{$firstUuid},Ada Lovelace,ada@example.com,500.50\n{$secondUuid},Grace Hopper,,\n";

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->set('csv', UploadedFile::fake()->createWithContent('attendees.csv', $csv))
        ->call('import')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('attendees', [
        'uuid' => $firstUuid,
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'membership_cost' => 500.50,
        'received' => false,
    ]);
    $this->assertDatabaseHas('attendees', ['uuid' => $secondUuid, 'name' => 'Grace Hopper']);
});

test('invalid CSV rows make no database changes', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $firstUuid = (string) Str::uuid();
    $secondUuid = (string) Str::uuid();
    $csv = "uuid,name,email\n{$firstUuid},Ada Lovelace,ada@example.com\n{$secondUuid},Grace Hopper,not-an-email\n";

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->set('csv', UploadedFile::fake()->createWithContent('attendees.csv', $csv))
        ->call('import')
        ->assertHasErrors('csv')
        ->assertSeeText('Row 3');

    expect(DB::table('attendees')->count())->toBe(0);
});

test('duplicate CSV UUIDs reject the entire import', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $uuid = (string) Str::uuid();
    $csv = "uuid,name\n{$uuid},Ada Lovelace\n{$uuid},Grace Hopper\n";

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->set('csv', UploadedFile::fake()->createWithContent('attendees.csv', $csv))
        ->call('import')
        ->assertHasErrors('csv')
        ->assertSeeText('duplicates UUID from row 2');

    expect(DB::table('attendees')->count())->toBe(0);
});

test('admin can download one attendee QR', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert(['uuid' => $uuid, 'name' => 'Ada Lovelace']);

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->call('downloadQr', $uuid)
        ->assertFileDownloaded("{$uuid}.png", contentType: 'image/png');
});

test('admin can preview an attendee QR and information', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert([
        'uuid' => $uuid,
        'name' => 'Ada Lovelace',
        'aiub_id' => '25-12345-1',
        'phone_no' => '01700000000',
        'size' => 'M',
    ]);

    $component = Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->call('previewQr', $uuid)
        ->assertSet('showQr', true)
        ->assertSeeText(['Ada Lovelace', '25-12345-1', '01700000000', 'M']);

    expect($component->get('qrImage'))->toStartWith('data:image/png;base64,');
});

test('admin can queue one development invitation for their own address', function () {
    Queue::fake([SendMemberOrientationInvitation::class]);
    config(['mail.attendee_dev_mode' => true]);
    $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert([
        'uuid' => $uuid,
        'name' => 'Ada Lovelace',
        'email' => 'member@example.com',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->call('previewQr', $uuid)
        ->call('queueInvitation', $uuid)
        ->assertHasNoErrors();

    Queue::assertPushed(SendMemberOrientationInvitation::class, function (SendMemberOrientationInvitation $job) use ($uuid): bool {
        return $job->uuid === $uuid && $job->recipientEmail === 'admin@example.com';
    });
    $this->assertDatabaseHas('attendees', ['uuid' => $uuid, 'invitation_email_status' => 'queued']);
});

test('admin can queue invitations using Outlook email addresses', function () {
    Queue::fake([SendMemberOrientationInvitation::class]);
    config(['mail.attendee_dev_mode' => false]);
    $admin = User::factory()->create(['role' => 'admin']);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert([
        'uuid' => $uuid,
        'email' => 'member@example.com',
        'outlook_email' => '23-50945-1@student.aiub.edu',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->set('recipientColumn', 'outlook_email')
        ->call('queueInvitation', $uuid)
        ->assertHasNoErrors();

    Queue::assertPushed(SendMemberOrientationInvitation::class, fn (SendMemberOrientationInvitation $job): bool => $job->recipientEmail === '23-50945-1@student.aiub.edu');
});

test('admin cannot queue invitations using an unsupported email column', function () {
    Queue::fake([SendMemberOrientationInvitation::class]);
    $admin = User::factory()->create(['role' => 'admin']);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert(['uuid' => $uuid]);

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->set('recipientColumn', 'uuid')
        ->call('queueInvitation', $uuid)
        ->assertHasErrors(['recipientColumn']);

    Queue::assertNothingPushed();
});

test('admin can reset a finished invitation to not queued', function (string $status) {
    $admin = User::factory()->create(['role' => 'admin']);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert([
        'uuid' => $uuid,
        'invitation_email_status' => $status,
        'invitation_email_sent_at' => now(),
        'invitation_email_error' => 'Old error',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->assertSeeText('Reset')
        ->call('resetInvitation', $uuid)
        ->assertDontSeeText('Reset');

    $this->assertDatabaseHas('attendees', [
        'uuid' => $uuid,
        'invitation_email_status' => null,
        'invitation_email_sent_at' => null,
        'invitation_email_error' => null,
    ]);
})->with(['queued', 'sent', 'failed']);

test('queued development invitation remains addressed to the admin and sends only once', function () {
    Mail::fake();
    config(['mail.attendee_dev_mode' => false]);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert([
        'uuid' => $uuid,
        'email' => 'member@example.com',
        'invitation_email_status' => 'queued',
    ]);
    $job = new SendMemberOrientationInvitation($uuid, 'admin@example.com');

    $job->handle(app(GenerateAttendeeQr::class));
    $job->handle(app(GenerateAttendeeQr::class));

    Mail::assertSent(MemberOrientationInvitation::class, function (MemberOrientationInvitation $mail) use ($uuid): bool {
        $mail->assertSeeInHtml('Members Orientation 2026');
        $mail->assertSeeInHtml('26th November, 2026');
        $mail->assertSeeInHtml('background: #fef08a');
        $mail->assertSeeInHtml('American International University-Bangladesh');
        $mail->assertDontSeeInHtml('Laravel');
        $mail->assertDontSeeInHtml('http');
        $mail->assertHasSubject('Invitation to the event titled "Members Orientation - 2026" organized by IEEE AIUB Student Branch.');
        $mail->assertHasAttachedData($mail->qrPng, "{$uuid}.png", ['mime' => 'image/png']);

        return $mail->hasTo('admin@example.com') && ! $mail->hasTo('member@example.com');
    });
    Mail::assertSentCount(1);
    $this->assertDatabaseHas('attendees', [
        'uuid' => $uuid,
        'invitation_email_status' => 'sent',
        'invitation_email_error' => null,
    ]);
});

test('failed invitation is visible and can be retried safely', function () {
    config(['mail.attendee_dev_mode' => false]);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert(['uuid' => $uuid, 'invitation_email_status' => 'queued']);
    $job = new SendMemberOrientationInvitation($uuid, '');

    expect(fn () => $job->handle(app(GenerateAttendeeQr::class)))
        ->toThrow(RuntimeException::class, 'valid email address');
    $this->assertDatabaseHas('attendees', [
        'uuid' => $uuid,
        'invitation_email_status' => 'failed',
        'invitation_email_error' => 'The attendee does not have a valid email address.',
    ]);

    Mail::fake();
    DB::table('attendees')->where('uuid', $uuid)->update(['email' => 'member@example.com']);
    (new SendMemberOrientationInvitation($uuid, 'member@example.com'))->handle(app(GenerateAttendeeQr::class));

    Mail::assertSent(MemberOrientationInvitation::class, 'member@example.com');
    $this->assertDatabaseHas('attendees', ['uuid' => $uuid, 'invitation_email_status' => 'sent']);
});

test('admin can queue a controlled invitation batch without duplicates', function () {
    Queue::fake([SendMemberOrientationInvitation::class]);
    $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
    $uuids = [(string) Str::uuid(), (string) Str::uuid()];
    DB::table('attendees')->insert([
        ['uuid' => $uuids[0], 'email' => 'ada@example.com'],
        ['uuid' => $uuids[1], 'email' => 'grace@example.com'],
    ]);

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->set('selected', $uuids)
        ->call('queueSelectedInvitations')
        ->assertHasNoErrors()
        ->call('queueSelectedInvitations')
        ->assertHasNoErrors();

    Queue::assertPushed(SendMemberOrientationInvitation::class, 2);
    expect(DB::table('attendees')->where('invitation_email_status', 'queued')->count())->toBe(2);
});

test('invitation batches are limited to one hundred attendees', function () {
    Queue::fake([SendMemberOrientationInvitation::class]);
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->set('selected', collect(range(1, 101))->map(fn (): string => (string) Str::uuid())->all())
        ->call('queueSelectedInvitations')
        ->assertHasErrors(['selected' => 'max']);

    Queue::assertNothingPushed();
});

test('staff cannot queue attendee invitations', function () {
    Queue::fake([SendMemberOrientationInvitation::class]);
    $staff = User::factory()->create(['role' => 'staff']);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert(['uuid' => $uuid]);

    Livewire::actingAs($staff)
        ->test('pages::attendees')
        ->call('queueInvitation', $uuid)
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('admin can download a controlled QR batch', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $uuids = [(string) Str::uuid(), (string) Str::uuid()];
    DB::table('attendees')->insert([
        ['uuid' => $uuids[0], 'name' => 'Ada Lovelace'],
        ['uuid' => $uuids[1], 'name' => 'Grace Hopper'],
    ]);

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->set('selected', $uuids)
        ->call('downloadSelectedQrs')
        ->assertFileDownloaded('attendee-qrs.zip', contentType: 'application/zip');
});

test('QR batches are limited to one hundred attendees', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test('pages::attendees')
        ->set('selected', collect(range(1, 101))->map(fn (): string => (string) Str::uuid())->all())
        ->call('downloadSelectedQrs')
        ->assertHasErrors(['selected' => 'max'])
        ->assertNoFileDownloaded();
});

test('staff cannot download attendee QRs', function () {
    $staff = User::factory()->create(['role' => 'staff']);
    $uuid = (string) Str::uuid();
    DB::table('attendees')->insert(['uuid' => $uuid]);

    Livewire::actingAs($staff)
        ->test('pages::attendees')
        ->call('downloadQr', $uuid)
        ->assertForbidden()
        ->assertNoFileDownloaded();
});
