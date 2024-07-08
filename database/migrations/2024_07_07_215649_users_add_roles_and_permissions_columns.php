<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('roles')->default('[]');
            $table->json('permissions')->default('[]');
        });

        DB::table('users')->where('username', 'admin')->update([
            'roles' => json_encode(['admin']),
            'permissions' => json_encode(['all']),
        ]);

        DB::table('users')->where('username', '<>', 'admin')->update([
            'roles' => '[]',
            'permissions' => json_encode(['view-reports', 'view-inventory', 'view-attendances', 'view-wallet']),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
