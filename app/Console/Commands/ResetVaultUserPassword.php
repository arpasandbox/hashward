<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class ResetVaultUserPassword extends Command
{
    protected $signature = 'vault:reset-password {email} {--password=}';

    protected $description = 'Reset login password and clear lockout for a vault user.';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->option('password') ?: $this->secret('New login password (min 12 chars)');

        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            [
                'email' => ['required', 'email'],
                'password' => ['required', 'string', 'min:12'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        $user->forceFill([
            'password' => $password,
            'failed_logins' => 0,
            'locked_until' => null,
        ])->save();

        $this->info('Password updated and lockout cleared for '.$email);

        return self::SUCCESS;
    }
}
