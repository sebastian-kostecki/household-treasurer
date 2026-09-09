<?php

namespace App\Console\Commands;

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('user:create {name? : The user display name} {email? : The user email address} {--password= : The user password} {--unverified : Leave the email address unverified}')]
#[Description('Create a new user account')]
class CreateUserCommand extends Command
{
    public function handle(CreateNewUser $createNewUser): int
    {
        $name = $this->argument('name') ?? $this->ask('Name');
        $email = $this->argument('email') ?? $this->ask('Email address');

        $password = $this->option('password');

        if ($password === null) {
            $password = $this->secret('Password');
            $passwordConfirmation = $this->secret('Confirm password');

            if ($password !== $passwordConfirmation) {
                $this->components->error('Password confirmation does not match.');

                return self::FAILURE;
            }
        }

        try {
            $user = $createNewUser->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $password,
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->components->error($message);
                }
            }

            return self::FAILURE;
        }

        if (! $this->option('unverified')) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $this->components->info("Created user #{$user->id} ({$user->email}).");

        return self::SUCCESS;
    }
}
