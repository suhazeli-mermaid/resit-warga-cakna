<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('penderma')->after('email'); // penderma, ajk, admin
        });

        // Set wargacaknatrg@gmail.com secara automatik sebagai Super Admin jika akaun wujud
        DB::table('users')->where('email', 'wargacaknatrg@gmail.com')->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};