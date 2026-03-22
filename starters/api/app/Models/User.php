<?php

declare(strict_types=1);

namespace App\Models;

use Lattice\Database\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['email_verified_at' => 'datetime'];

    public function setPasswordAttribute(string $value): void
    {
        $this->attributes['password'] = password_hash($value, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}
