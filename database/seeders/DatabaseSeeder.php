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

        Bucket::create([
            'name' => 'bucket',
            'storage_path' => 'storage/bucket',
            'access_key' => 'accesskey1',
            'secret_key' => 'secretkey1',
            'visibility' => 'private',
            'versioning' => false,
            'object_lock' => false,
        ]);
    }
}
