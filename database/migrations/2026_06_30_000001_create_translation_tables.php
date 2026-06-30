<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Approved, active overrides merged on top of the file-based messages.php.
        Schema::create('translation_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 8);
            $table->string('key', 191);
            $table->text('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['locale', 'key']);
        });

        // Customer-submitted proposals awaiting admin review.
        Schema::create('translation_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 8);
            $table->string('key', 191);
            $table->text('value');                 // proposed translation
            $table->text('original_value')->nullable(); // value shown to the suggester at submit time
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();

            $table->index(['status', 'locale']);
            $table->index(['locale', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translation_suggestions');
        Schema::dropIfExists('translation_overrides');
    }
};
