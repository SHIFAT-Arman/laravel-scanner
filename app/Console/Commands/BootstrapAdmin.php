<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('app:bootstrap-admin {--force : Allow bootstrapping in production}')]
#[Description('Create or update the initial administrator from environment credentials')]
class BootstrapAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Refusing to bootstrap an administrator in production without --force.');

            return self::FAILURE;
        }

        $name = config('auth.bootstrap_admin.name');
        $email = config('auth.bootstrap_admin.email');
        $password = config('auth.bootstrap_admin.password');

        if (! is_string($name) || blank($name)
            || ! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ! is_string($password) || blank($password)) {
            $this->error('Set ADMIN_NAME, ADMIN_EMAIL, and ADMIN_PASSWORD before running this command.');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $created = ! $user->exists;
        $user->name = $name;
        $user->role = 'admin';
        $user->password = Hash::make($password);
        $user->email_verified_at ??= now();
        $user->save();

        $this->info($created ? 'Administrator created.' : 'Administrator updated.');
        $this->line('Change the bootstrap password after the first login and remove ADMIN_PASSWORD.');

        return self::SUCCESS;
    }
}
