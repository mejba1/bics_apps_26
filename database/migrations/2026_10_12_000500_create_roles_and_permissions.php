<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Roles with a chosen set of permissions (Admin > Roles & Permissions); every user gets a role.
     * Existing accounts keep what they could do: "admin" -> Super Admin, "user" -> Registration Desk.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_super')->default(false); // Super Admin: always every permission, cannot be edited
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('permission', 64);
            $table->primary(['role_id', 'permission']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('role')->constrained('roles')->nullOnDelete();
        });

        $now = now();
        $role = function (string $name, string $description, bool $super, array $permissions) use ($now) {
            $id = DB::table('roles')->insertGetId(['name' => $name, 'description' => $description, 'is_super' => $super, 'created_at' => $now, 'updated_at' => $now]);
            foreach ($permissions as $p) DB::table('role_permissions')->insert(['role_id' => $id, 'permission' => $p]);
            return $id;
        };

        $super = $role('Super Admin', 'Full access to everything, including users and roles.', true, []);
        $desk = $role('Registration Desk', 'Dashboard, check-in, gifts and the raffle draw.', false, Permissions::staffDefaults());
        $role('Content Editor', 'Website pages, menu and gallery.', false, ['website.pages', 'website.menu', 'website.gallery']);

        DB::table('users')->where('role', 'admin')->update(['role_id' => $super]);
        DB::table('users')->where('role', '!=', 'admin')->update(['role_id' => $desk]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
    }
};
