<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * New permission "registrations.create" (register people from the admin panel).
     * The Registration Desk role gets it, as on-site registration is part of its job.
     * Super Admin has every permission automatically.
     */
    public function up(): void
    {
        $desk = DB::table('roles')->where('name', 'Registration Desk')->value('id');
        if ($desk && !DB::table('role_permissions')->where('role_id', $desk)->where('permission', 'registrations.create')->exists()) {
            DB::table('role_permissions')->insert(['role_id' => $desk, 'permission' => 'registrations.create']);
        }
    }

    public function down(): void
    {
        DB::table('role_permissions')->where('permission', 'registrations.create')->delete();
    }
};
