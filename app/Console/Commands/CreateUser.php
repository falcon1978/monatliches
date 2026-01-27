<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create {--name=} {--email=} {--password=} {--admin=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a user account (optionally admin)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = (string) $this->option('name');
        $email = (string) $this->option('email');
        $password = (string) $this->option('password');
        $isAdmin = (int) $this->option('admin') === 1;

        if ($name === '' || $email === '' || $password === '') {
            $this->error('Bitte --name=, --email= und --password= angeben.');
            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error('Ein User mit dieser E-Mail existiert bereits.');
            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => $isAdmin,
        ]);

        $this->info("User {$user->email} wurde erstellt.");

        return self::SUCCESS;
    }
}
