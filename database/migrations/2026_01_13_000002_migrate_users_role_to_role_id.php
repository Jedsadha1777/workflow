<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add role_id column
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->after('role');
        });

        // Step 2: Migrate existing role string to role_id
        $users = DB::table('users')->whereNotNull('role')->get();
        
        foreach ($users as $user) {
            $role = DB::table('roles')->where('code', $user->role)->first();
            
            if ($role) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['role_id' => $role->id]);
            }
        }

        // Step 3: Add foreign key constraint
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onDelete('set null');
        });

        // Step 4: Drop old role column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        // Step 1: Add back role column
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable()->after('role_id');
        });

        // Step 2: Migrate role_id back to role string
        $users = DB::table('users')->whereNotNull('role_id')->get();
        
        foreach ($users as $user) {
            $role = DB::table('roles')->where('id', $user->role_id)->first();
            
            if ($role) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['role' => $role->code]);
            }
        }

        // Step 3: Drop foreign key and role_id column
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }
};
