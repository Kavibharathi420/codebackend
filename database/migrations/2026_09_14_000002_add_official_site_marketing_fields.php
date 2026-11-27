<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->date('next_batch_date')->nullable()->after('schedule');
            $table->string('batch_time', 120)->nullable()->after('next_batch_date');
            $table->unsignedInteger('available_seats')->nullable()->after('batch_time');
        });

        Schema::table('gallery_images', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->string('student_name', 120)->nullable()->after('description');
            $table->string('project_course', 160)->nullable()->after('student_name');
            $table->string('technologies', 300)->nullable()->after('category');
            $table->string('external_url', 2000)->nullable()->after('technologies');
        });
    }

    public function down(): void
    {
        Schema::table('gallery_images', fn (Blueprint $table) => $table->dropColumn(['description', 'student_name', 'project_course', 'technologies', 'external_url']));
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn(['next_batch_date', 'batch_time', 'available_seats']));
    }
};
