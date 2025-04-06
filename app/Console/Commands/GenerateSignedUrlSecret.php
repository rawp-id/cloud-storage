<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class GenerateSignedUrlSecret extends Command
{
    protected $signature = 'signed:generate';
    protected $description = 'Generate and set SIGNED_URL_SECRET in .env';

    public function handle()
    {
        $secret = Str::random(64);

        $envPath = base_path('.env');
        $envContent = File::get($envPath);

        if (strpos($envContent, 'SIGNED_URL_SECRET=') !== false) {
            // Replace existing value
            $envContent = preg_replace('/SIGNED_URL_SECRET=.*/', 'SIGNED_URL_SECRET=' . $secret, $envContent);
        } else {
            // Add new line
            $envContent .= "\nSIGNED_URL_SECRET=" . $secret;
        }

        File::put($envPath, $envContent);

        $this->info('SIGNED_URL_SECRET generated and added to .env!');
    }
}
