<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class CreateTestUser extends Command
{
    protected $signature = 'user:create-test';
    protected $description = 'Create a test user and generate token';

    public function handle()
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password')
            ]
        );

        $token = $user->createToken('test-token')->plainTextToken;
        
        $this->info('Test user created/found:');
        $this->info('Email: admin@test.com');
        $this->info('Password: password');
        $this->info('Token: ' . $token);
        
        return 0;
    }
}
