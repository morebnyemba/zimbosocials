<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Explain WHY counts drop, in the entry that already answers refills.
 *
 * The existing copy says we top numbers back up but never says why they fell,
 * which leaves the customer assuming they were sold something fake. The honest
 * explanation — platforms run their own sweeps, it doesn't harm their account,
 * and refills land within 72 hours — turns the most common post-delivery
 * complaint into a reason to trust us.
 *
 * Updated rather than added: a second entry on the same subject would split the
 * knowledge-base search between them and the assistant would quote whichever
 * happened to rank higher.
 */
return new class extends Migration
{
    private const TITLE = 'Refill guarantee';

    /** Only present in our own generated copy — an admin's edit won't have it. */
    private const MARKER = 'refill-guaranteed';

    public function up(): void
    {
        if (! Schema::hasTable('whatsapp_knowledge_base')) {
            return;
        }

        $row = DB::table('whatsapp_knowledge_base')->where('title', self::TITLE)->first();

        // Hand-edited by an admin — their wording wins.
        if ($row && (! is_string($row->answer) || ! str_contains($row->answer, self::MARKER))) {
            return;
        }

        $entry = [
            'question' => 'Why are the followers, views or likes I bought falling? My count went down, '
                .'dropping, disappearing, reducing, lost followers. Do you offer refill, is there a refill guarantee?',
            'answer' => "Good question — and it's normal, so don't worry. 🙏\n\n"
                ."Instagram, TikTok and Facebook all run their own regular sweeps, and some of the accounts that followed you get flagged by those detection systems and removed. It happens right across each platform — it isn't something wrong with your order.\n\n"
                ."*Two things worth knowing:*\n"
                ."• It does *not* affect your profile, your reach or your account standing in any way.\n"
                ."• You're covered by our *refill policy* — we top the numbers back up *free*.\n\n"
                ."Send me your order number or the link and we'll start the refill. Refills are processed *within 72 hours*, at no extra charge. 👍",
            // Space-separated, matching the rest of the table: the search
            // tokenises on whitespace, so a comma-joined list is one long token
            // that matches nothing.
            'keywords' => 'refill guarantee drop dropped dropping falling fell reducing decreasing disappearing '
                .'lost losing unfollowed top up replace refills warranty maintain went down count down fake removed flagged',
            'updated_at' => now(),
        ];

        // Update where the seeded entry exists; create it on a fresh install,
        // where the base entries come from a seeder that may never have run.
        if ($row) {
            DB::table('whatsapp_knowledge_base')->where('id', $row->id)->update($entry);

            return;
        }

        DB::table('whatsapp_knowledge_base')->insert($entry + [
            'title' => self::TITLE,
            'category' => 'orders',
            'locale' => 'en',
            // Boolean, not a label: the retrieval query filters on
            // where('status', true), so anything else hides the entry.
            'status' => true,
            'hits' => 0,
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The previous copy is superseded, not worth restoring; leaving the
        // improved answer in place is safer than reverting to one that made
        // customers think they'd been sold fakes.
    }
};
