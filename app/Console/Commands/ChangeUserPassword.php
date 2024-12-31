<?php

namespace App\Console\Commands;

use App\Models\InventoryProduct;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\InventoryProductItem;
use App\Models\User;
use Illuminate\Support\Facades\Hash;


class ChangeUserPassword extends Command
{
    protected $signature = 'users:change-password';

    protected $description = 'Change user password';

    public function handle()
    {

        $choice = $this->choice('📋 Choose an user to change the password:', array_map(function ($user) {
            return $user['name'] . ' [' . $user['id'] . ']';
        }, User::all()->toArray()));

        $userId = str_replace(']', '', explode('[', $choice)[1]);

        $user = User::where('id', $userId)->first();

        // If user not found, display error message:
        if (!$user) {
            $this->error('❌ User not found');
            return;
        }

        // Interrogate to enter new password:
        $password = $this->secret('💬 Enter new password for ' . $user->name);

        if (strlen($password) < 8) {
            $this->error('❌ Password must be at least 8 characters long');

            $password = $this->secret('💬 Enter new password for ' . $user->name);

            if (strlen($password) < 8) {
                $this->error('❌ Password must be at least 8 characters long');
                return;
            }
        }


        // Update user password:
        User::where('id', $userId)->update([
            'password' => Hash::make($password)
        ]);

        $this->info('✅ Password changed successfully');
    }

}
