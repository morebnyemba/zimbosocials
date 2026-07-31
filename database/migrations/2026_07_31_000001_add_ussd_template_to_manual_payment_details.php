<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A dialable shortcode per payment method, built with the real amount.
 *
 * The shortcode was being typed into the free-text instructions, which meant it
 * carried whatever amount happened to be there when an admin wrote it — one
 * account was telling every customer to send exactly 10 USD regardless of what
 * they owed. As a template it is filled in per deposit.
 *
 * Left null by default and admin-editable rather than derived from the method
 * name: dialling codes differ per provider and a wrong one sends money to the
 * wrong place, so this is not a thing to guess at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_payment_details', function (Blueprint $table): void {
            $table->string('ussd_template')->nullable()->after('instructions');
        });

        // EcoCash is the one we can fill in safely: the number already lives on
        // the row, and the pattern is well known.
        if (Schema::hasTable('manual_payment_details')) {
            \Illuminate\Support\Facades\DB::table('manual_payment_details')
                ->whereNull('ussd_template')
                ->where(function ($q) {
                    $q->where('method_key', 'like', '%ecocash%')
                        ->orWhere('label', 'like', '%ecocash%');
                })
                ->update(['ussd_template' => '*151*1*1*{number}*{amount}#']);
        }
    }

    public function down(): void
    {
        Schema::table('manual_payment_details', function (Blueprint $table): void {
            $table->dropColumn('ussd_template');
        });
    }
};
