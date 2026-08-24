<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thinking tokens were being paid for and not counted.
 *
 * Gemini reports usageMetadata.thoughtsTokenCount separately from
 * candidatesTokenCount, and bills both at the output rate. Recording only the
 * latter meant the dashboard showed the visible reply while the invoice
 * included the reasoning behind it — on the chat path, the larger of the two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage', function (Blueprint $table): void {
            $table->unsignedBigInteger('thinking_tokens')->default(0)->after('output_tokens');
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage', function (Blueprint $table): void {
            $table->dropColumn('thinking_tokens');
        });
    }
};
