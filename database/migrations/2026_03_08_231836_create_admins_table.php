<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('organisation'); // admin, organisation
            $table->rememberToken();
            $table->timestamps();
        });

        // Migrate existing administrators out of the users table into admins
        $existingAdmins = DB::table('users')
            ->whereIn('role', ['admin', 'organisation'])
            ->get();

        foreach ($existingAdmins as $admin) {
            DB::table('admins')->insert([
                'id'         => $admin->id, // Attempt to keep the same ID for simplicity
                'tenant_id'  => $admin->tenant_id,
                'name'       => $admin->name,
                'email'      => $admin->email ?? ('admin_transfer_' . $admin->id . '@example.com'),
                'password'   => $admin->password ?? bcrypt('password'), // fallback if password was stripped
                'role'       => $admin->role,
                'created_at' => $admin->created_at,
                'updated_at' => $admin->updated_at,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
