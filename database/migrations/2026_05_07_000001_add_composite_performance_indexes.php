<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additional composite indexes to support frequent query patterns:
 * - contract_applications (business_contract_id, status) — used by decide() with lockForUpdate
 * - contract_proof_submissions (marketer_id, status) — used by wallet withdraw proof check
 * - notifications (user_id, read_at) — used by unread count on every page load
 * - audit_logs (model_type, model_id) — used by audit trail lookups
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_applications', function (Blueprint $table) {
            if (!$this->hasIndex('contract_applications', 'contract_applications_contract_status_index')) {
                $table->index(['business_contract_id', 'status'], 'contract_applications_contract_status_index');
            }
            if (!$this->hasIndex('contract_applications', 'contract_applications_marketer_status_index')) {
                $table->index(['marketer_id', 'status'], 'contract_applications_marketer_status_index');
            }
        });

        Schema::table('contract_proof_submissions', function (Blueprint $table) {
            if (!$this->hasIndex('contract_proof_submissions', 'contract_proofs_marketer_status_index')) {
                $table->index(['marketer_id', 'status'], 'contract_proofs_marketer_status_index');
            }
        });

        Schema::table('notifications', function (Blueprint $table) {
            if (!$this->hasIndex('notifications', 'notifications_user_read_index')) {
                $table->index(['user_id', 'read_at'], 'notifications_user_read_index');
            }
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            if (!$this->hasIndex('audit_logs', 'audit_logs_model_index')) {
                $table->index(['model_type', 'model_id'], 'audit_logs_model_index');
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $indexes = Schema::getIndexes($table);
            foreach ($indexes as $index) {
                if ($index['name'] === $indexName) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // Fallback: assume it doesn't exist
        }
        return false;
    }

    public function down(): void
    {
        // No-op: don't drop indexes that may have existed before this migration
    }
};
