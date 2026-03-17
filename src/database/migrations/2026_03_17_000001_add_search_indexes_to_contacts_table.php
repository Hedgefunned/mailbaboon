<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->index(['last_name', 'first_name']);
            $table->fullText(['first_name', 'last_name', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropFullText(['first_name', 'last_name', 'email']);
            $table->dropIndex(['last_name', 'first_name']);
        });
    }
};
