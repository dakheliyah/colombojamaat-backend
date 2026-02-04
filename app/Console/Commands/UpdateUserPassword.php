<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UpdateUserPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:update-password {its_no} {password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update a user password by ITS number';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $itsNo = $this->argument('its_no');
        $password = $this->argument('password');

        $hashedPassword = Hash::make($password);

        $result = DB::table('users')
            ->where('its_no', $itsNo)
            ->update(['password' => $hashedPassword]);

        if ($result > 0) {
            $this->info("Password updated successfully for user with ITS number: {$itsNo}");
            $this->info("Password hash: " . substr($hashedPassword, 0, 30) . "...");
        } else {
            $this->error("No user found with ITS number: {$itsNo}");
        }

        return 0;
    }
}
