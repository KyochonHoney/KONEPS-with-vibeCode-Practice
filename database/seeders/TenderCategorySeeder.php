<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TenderCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => '정보시스템',
                'code' => 'IT',
                'description' => 'IT 관련 용역 (시스템 구축, 소프트웨어 개발 등)',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '건설공사',
                'code' => 'CONST',
                'description' => '건설 관련 용역 (설계, 감리, 시공 등)',
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '용역',
                'code' => 'SERVICE',
                'description' => '일반 용역 (컨설팅, 연구, 조사 등)',
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '물품',
                'code' => 'GOODS',
                'description' => '물품 구매 (장비, 소프트웨어, 소모품 등)',
                'is_active' => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => '기타',
                'code' => 'ETC',
                'description' => '기타 분류되지 않는 공고',
                'is_active' => true,
                'sort_order' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        \DB::table('tender_categories')->insertOrIgnore($categories);
    }
}
