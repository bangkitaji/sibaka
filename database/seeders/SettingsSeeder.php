<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaultSettings = [
            // General
            ['key' => 'app_name', 'value' => 'SIBAKA Portal', 'type' => 'string', 'group' => 'general'],
            ['key' => 'app_description', 'value' => 'Sinau Bareng Kamisetembang — Knowledge sharing platform for IT alumni of STM Pembangunan Semarang', 'type' => 'string', 'group' => 'general'],
            ['key' => 'maintenance_mode', 'value' => '0', 'type' => 'boolean', 'group' => 'general'],
            
            // Auth & Registration
            ['key' => 'registration_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'auth'],
            ['key' => 'invite_code_required', 'value' => '0', 'type' => 'boolean', 'group' => 'auth'],
            ['key' => 'max_failed_login_attempts', 'value' => '5', 'type' => 'integer', 'group' => 'auth'],
            
            // Content
            ['key' => 'auto_approve_content', 'value' => '1', 'type' => 'boolean', 'group' => 'content'],
            ['key' => 'allow_anonymous_posts', 'value' => '1', 'type' => 'boolean', 'group' => 'content'],
        ];

        foreach ($defaultSettings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
