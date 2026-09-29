<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates the single owner account for the admin panel.
 *
 * SECURITY.md §9/§14 — there is deliberately **no hard-coded default
 * password**. An earlier version shipped `password123`, which is a trivially
 * guessable credential for the one account that can publish, delete, and
 * change every setting on the site.
 *
 * Resolution order:
 *  1. `ADMIN_EMAIL` / `ADMIN_NAME` / `ADMIN_PASSWORD` from the environment.
 *  2. Otherwise a strong random password is generated and printed to the
 *     console **once**, at creation time. It is never stored in source and
 *     never guessable.
 *
 * Seeding is idempotent: if the account already exists it is left untouched,
 * so re-running `db:seed` cannot silently reset a password someone has
 * already changed.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'bappi@ehb-journalist.test');
        $name = env('ADMIN_NAME', 'Emrul Hasan Bappi');

        $existing = User::where('email', $email)->first();

        if ($existing) {
            $this->command?->warn("Admin account {$email} already exists — left unchanged.");

            return;
        }

        $configured = env('ADMIN_PASSWORD');
        $password = $configured ?: Str::password(24);

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role' => 'owner',
        ]);

        $this->command?->newLine();
        $this->command?->info('Admin account created.');
        $this->command?->table(
            ['Email', 'Password'],
            [[$email, $configured ? $password : $password.'   (generated — copy it now)']]
        );

        if (! $configured) {
            $this->command?->warn(
                'This password is shown once and is not recoverable. Set ADMIN_PASSWORD in .env before seeding if you want to choose it yourself.'
            );
        }

        $this->command?->newLine();
    }
}
