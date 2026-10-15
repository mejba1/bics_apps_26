<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    private ?array $permissionCache = null;

    public function roleModel()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /** Super Admin role (or an old "admin" account that has no role yet). */
    public function isSuperAdmin(): bool
    {
        $role = $this->role_id ? $this->roleModel : null;
        return $role ? $role->is_super : $this->getAttribute('role') === 'admin';
    }

    /** Permission keys this user has (inactive accounts have none). */
    public function permissions(): array
    {
        if ($this->permissionCache !== null) return $this->permissionCache;
        if (($this->status ?? 'active') !== 'active') return $this->permissionCache = [];
        try {
            $role = $this->role_id ? $this->roleModel : null;
            if ($role) return $this->permissionCache = $role->permissionKeys();
        } catch (\Throwable $e) {
            // roles table not created yet: fall through to the old two-level system
        }
        return $this->permissionCache = $this->getAttribute('role') === 'admin'
            ? array_keys(\App\Support\Permissions::all())
            : \App\Support\Permissions::staffDefaults();
    }

    public function hasPermission(string $key): bool
    {
        return in_array($key, $this->permissions(), true);
    }

    /** Name of the user's role for display. */
    public function getRoleNameAttribute(): string
    {
        $role = $this->role_id ? $this->roleModel : null;
        return $role ? $role->name : ($this->getAttribute('role') === 'admin' ? 'Admin' : 'Staff');
    }

    public function forgetPermissions(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('roleModel');
    }
}
