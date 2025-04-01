<?php

namespace Database\Seeders;

use App\Models\Bucket;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $user = User::create([
            'name' => 'admin',
            'email' => 'admin@admin',
            'password' => bcrypt('password'),
            'access_key' => 'admin',
            'secret_key' => 'admin',
        ]);

        Bucket::create([
            'user_id' => $user->id,
            'name' => 'bucket',
            'storage_path' => 'storage/bucket',
            'access_key' => 'accesskey',
            'secret_key' => 'secretkey',
            'visibility' => 'private',
            'versioning' => false,
            'object_lock' => false,
        ]);
    }
}
