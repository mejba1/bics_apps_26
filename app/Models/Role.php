<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** A role with a set of permissions (see App\Support\Permissions). */
class Role extends Model
{
    protected $fillable = ['name', 'description', 'is_super'];

    protected $casts = ['is_super' => 'boolean'];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    /** Permission keys of this role (Super Admin: all of them). */
    public function permissionKeys(): array
    {
        if ($this->is_super) return array_keys(Permissions::all());
        return DB::table('role_permissions')->where('role_id', $this->id)->pluck('permission')
            ->filter(fn ($p) => Permissions::exists($p))->values()->all();
    }

    public function syncPermissions(array $keys): void
    {
        $keys = array_values(array_unique(array_filter($keys, fn ($k) => Permissions::exists($k))));
        DB::transaction(function () use ($keys) {
            DB::table('role_permissions')->where('role_id', $this->id)->delete();
            foreach ($keys as $k) DB::table('role_permissions')->insert(['role_id' => $this->id, 'permission' => $k]);
        });
    }
}
