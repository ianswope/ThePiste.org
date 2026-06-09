<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Split "regional" into official circuit events (ROC/RJCC/RYC/SYC/RCC/RPC)
     * vs club-level "local" events, using circuit designators in the name.
     */
    public function up(): void
    {
        foreach (DB::table('tournaments')->get() as $t) {
            if (str_starts_with((string) $t->level, 'fie')) {
                continue;
            }

            preg_match_all('/\b(ROC|RJCC|RYC|SYC|RCC|RPC)\b/i', $t->name, $m);
            $circuits = array_values(array_unique(array_map('strtoupper', $m[1])));
            $existing = json_decode($t->circuits ?? '[]', true) ?: [];

            $level = $t->is_nac ? 'national' : (($circuits !== [] || $existing !== []) ? 'regional' : 'local');

            DB::table('tournaments')->where('id', $t->id)->update([
                'level' => $level,
                'circuits' => $existing !== [] ? $t->circuits : ($circuits !== [] ? json_encode($circuits) : null),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('tournaments')->where('level', 'local')->update(['level' => 'regional']);
    }
};
