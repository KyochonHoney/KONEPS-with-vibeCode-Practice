<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 기본 역할 생성
        $roles = [
            [
                'name' => 'super_admin',
                'display_name' => '최고관리자',
                'description' => '시스템 전체 관리 권한',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'admin',
                'display_name' => '관리자',
                'description' => '운영 관리 권한',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'user',
                'display_name' => '일반사용자',
                'description' => '기본 사용 권한',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        \DB::table('roles')->insertOrIgnore($roles);

        // 기본 권한 생성
        $permissions = [
            [
                'name' => 'manage_users',
                'display_name' => '사용자 관리',
                'description' => '사용자 계정 생성/수정/삭제',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'manage_settings',
                'display_name' => '설정 관리',
                'description' => '시스템 설정 관리',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'view_analytics',
                'display_name' => '통계 조회',
                'description' => '분석 통계 조회',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'manage_templates',
                'display_name' => '템플릿 관리',
                'description' => '제안서 템플릿 관리',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'analyze_tenders',
                'display_name' => '공고 분석',
                'description' => '용역공고 AI 분석',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'generate_proposals',
                'display_name' => '제안서 생성',
                'description' => '제안서 자동 생성',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'manage_tenders',
                'display_name' => '공고 관리',
                'description' => '용역공고 데이터 관리',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        \DB::table('permissions')->insertOrIgnore($permissions);
    }
}
