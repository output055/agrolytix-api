<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MakeSuperAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agrolytix:make-super-admin {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Promotes a user to Super Admin by their email address';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $user = \App\Models\User::withoutGlobalScopes()->where('email', $email)->first();

        if (!$user) {
            $this->error("User with email {$email} not found.");
            return Command::FAILURE;
        }

        $user->update(['role' => 'SuperAdmin']);
        
        $this->info("User {$user->name} ({$email}) has been successfully promoted to Super Admin!");
        return Command::SUCCESS;
    }
}
