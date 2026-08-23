<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_library', function (Blueprint $table): void {
            $table->json('custom_properties')->nullable()->after('alt_text');
        });
    }

    public function down(): void
    {
        Schema::table('media_library', function (Blueprint $table): void {
            $table->dropColumn('custom_properties');
        });
    }
};
