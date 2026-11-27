<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->string('reference', 32)->nullable()->unique()->after('id');
            $table->string('preferred_contact', 20)->nullable()->after('phone');
            $table->boolean('consent')->default(false)->after('message');
            $table->index(['status', 'created_at']);
            $table->index(['assigned_to', 'follow_up_at']);
        });
        Schema::table('testimonials', function (Blueprint $table) {
            $table->string('moderation_status', 20)->default('approved')->after('is_active')->index();
            $table->foreignId('moderated_by')->nullable()->after('moderation_status')->constrained('admins')->nullOnDelete();
            $table->dateTime('moderated_at')->nullable()->after('moderated_by');
        });
        Schema::table('courses', function (Blueprint $table) {
            $table->string('delivery_mode', 60)->nullable()->after('level');
            $table->string('schedule', 160)->nullable()->after('delivery_mode');
            $table->string('fee_note', 200)->nullable()->after('schedule');
            $table->text('trainer_info')->nullable()->after('fee_note');
            $table->longText('prerequisites')->nullable()->after('trainer_info');
            $table->longText('projects')->nullable()->after('prerequisites');
            $table->index(['is_active', 'category_id', 'order']);
            $table->index(['level', 'duration']);
        });
    }

    public function down(): void
    {
        Schema::table('courses', fn (Blueprint $table) => $table->dropIndex(['level', 'duration']));
        Schema::table('courses', fn (Blueprint $table) => $table->dropIndex(['is_active', 'category_id', 'order']));
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn(['delivery_mode', 'schedule', 'fee_note', 'trainer_info', 'prerequisites', 'projects']));
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropForeign(['moderated_by']);
            $table->dropColumn(['moderation_status', 'moderated_by', 'moderated_at']);
        });
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropIndex(['assigned_to', 'follow_up_at']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropUnique(['reference']);
            $table->dropColumn(['reference', 'preferred_contact', 'consent']);
        });
    }
};
