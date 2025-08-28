<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    // [BEGIN nara:permission_model]
    protected $fillable = [
        'name',
        'guard_name',
        'display_name', 
        'description'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 이 권한을 가진 역할들
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }

    /**
     * 권한명으로 권한 찾기
     */
    public static function findByName(string $name): ?self
    {
        return static::where('name', $name)->first();
    }
    // [END nara:permission_model]
}