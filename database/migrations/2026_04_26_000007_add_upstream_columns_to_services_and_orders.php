<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            if (! Schema::hasColumn('services', 'upstream_service_id')) {
                $table->string('upstream_service_id')->nullable()->after('type');
                $table->index('upstream_service_id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'push_attempts')) {
                $table->unsignedSmallInteger('push_attempts')->default(0)->after('external_order_id');
            }

            if (! Schema::hasColumn('orders', 'pushed_to_upstream')) {
                $table->boolean('pushed_to_upstream')->default(false)->after('push_attempts');
            }

            if (! Schema::hasColumn('orders', 'pushed_at')) {
                $table->timestamp('pushed_at')->nullable()->after('pushed_to_upstream');
            }

            if (! Schema::hasColumn('orders', 'upstream_last_error')) {
                $table->text('upstream_last_error')->nullable()->after('pushed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'upstream_last_error')) {
                $table->dropColumn('upstream_last_error');
            }

            if (Schema::hasColumn('orders', 'pushed_at')) {
                $table->dropColumn('pushed_at');
            }

            if (Schema::hasColumn('orders', 'pushed_to_upstream')) {
                $table->dropColumn('pushed_to_upstream');
            }

            if (Schema::hasColumn('orders', 'push_attempts')) {
                $table->dropColumn('push_attempts');
            }
        });

        Schema::table('services', function (Blueprint $table) {
            if (Schema::hasColumn('services', 'upstream_service_id')) {
                $table->dropIndex(['upstream_service_id']);
                $table->dropColumn('upstream_service_id');
            }
        });
    }
};
