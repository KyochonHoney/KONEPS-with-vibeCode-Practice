<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 기본 역할 생성
        $roles = [
            [
                'name' => 'user',
                'display_name' => '일반사용자',
                'description' => '기본 사용 권한'
            ],
            [
                'name' => 'admin',
                'display_name' => '관리자',
                'description' => '운영 관리 권한'
            ],
            [
                'name' => 'super_admin',
                'display_name' => '최고관리자',
                'description' => '시스템 전체 관리 권한'
            ]
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(
                ['name' => $roleData['name']],
                [
                    'display_name' => $roleData['display_name'],
                    'description' => $roleData['description']
                ]
            );
        }
    }
}