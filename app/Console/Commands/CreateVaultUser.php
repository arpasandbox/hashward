<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateVaultUser extends Command
{
    protected $signature = 'vault:create-user {--name=} {--email=} {--password=}';

    protected $description = 'Create the host vault account (no public registration).';

    public function handle(): int
    {
        if (User::hostExists()) {
            $this->error('A host already exists. Add invitees via the invite flow in the app.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Login password (min 12 chars)');

        $validator = Validator::make(
            compact('name', 'email', 'password'),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', 'string', 'min:12'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'kdf_iterations' => 600000,
            'is_host' => true,
        ]);

        $this->info('Host account created. Complete 2FA and master password setup on first login.');

        return self::SUCCESS;
    }
}
