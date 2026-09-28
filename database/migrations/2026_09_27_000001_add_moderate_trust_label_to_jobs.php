<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "unrated" used to mean both "no score yet" and "scored in the middle band".
 * Split the middle band into its own "moderate" label and relabel scored rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE jobs MODIFY trust_label ENUM('verified', 'moderate', 'suspicious', 'low_trust', 'unrated') NOT NULL DEFAULT 'unrated'"
        );

        DB::table('jobs')
            ->where('trust_label', 'unrated')
            ->whereNotNull('trust_score')
            ->update([
                'trust_label' => DB::raw(
                    'CASE WHEN trust_score >= 75 THEN \'verified\''
                    .' WHEN trust_score >= 50 THEN \'moderate\''
                    .' WHEN trust_score >= 30 THEN \'suspicious\''
                    .' ELSE \'low_trust\' END'
                ),
            ]);
    }

    public function down(): void
    {
        DB::table('jobs')->where('trust_label', 'moderate')->update(['trust_label' => 'unrated']);

        DB::statement(
            "ALTER TABLE jobs MODIFY trust_label ENUM('verified', 'suspicious', 'low_trust', 'unrated') NOT NULL DEFAULT 'unrated'"
        );
    }
};
