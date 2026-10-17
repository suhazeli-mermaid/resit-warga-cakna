<?php

namespace Database\Seeders;

use App\Models\Campaign;
use Illuminate\Database\Seeder;

class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        Campaign::create([
            'title' => 'Dana Kecemasan Gaza',
            'slug' => 'dana-gaza',
            'description' => 'Bantuan perubatan, makanan, dan bekalan asas kecemasan bagi penduduk Palestin di Gaza.',
            'target_amount' => 50000.00,
            'collected_amount' => 12500.00,
            'is_active' => true,
        ]);

        Campaign::create([
            'title' => 'Dana Gerobok Rezeki Terengganu',
            'slug' => 'dana-gerobok-rezeki',
            'description' => 'Agihan bakul makanan bulanan untuk keluarga asnaf dan warga terpinggir di sekitar Terengganu.',
            'target_amount' => 20000.00,
            'collected_amount' => 8400.00,
            'is_active' => true,
        ]);

        Campaign::create([
            'title' => 'Dana Zakat & Kebajikan',
            'slug' => 'dana-zakat',
            'description' => 'Agihan dana zakat bagi asnaf yang berhak menerima mengikut syarak.',
            'target_amount' => 100000.00,
            'collected_amount' => 45000.00,
            'is_active' => true,
        ]);
    }
}