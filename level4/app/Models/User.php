<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * User thuộc hạ tầng xác thực ở app/, không thuộc module nào (QĐ-3.9).
 * KHÔNG khai báo relationship sang model của các module (orders, tickets…)
 * để User không phình relationship từ mọi module; module cần dữ liệu theo
 * người dùng thì tự query bằng user_id.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const string ROLE_USER = 'user';

    public const string ROLE_SCANNER = 'scanner';

    /** @var list<string> */
    protected $fillable = ['name', 'email', 'password'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Nhân viên soát vé (§4) — chỉ vai trò này được vào chức năng check-in.
     */
    public function isScanner(): bool
    {
        return $this->role === self::ROLE_SCANNER;
    }
}
