<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $AdminRole = Role::where('slug', 'admin')->first();

        User::create([
            'role_id' => $AdminRole->id,
            'first_name' => 'Inkspire',
            'last_name' =>  'Media',
            'email'     =>  'admin@inkspiremedia.com',
            'email_verified_at' =>  now(),
            'password'  =>  Hash::make('password123'),
            'password_created_at'   => now(),
            'invitation_expires_at' => null,
        ]);
    }
}
