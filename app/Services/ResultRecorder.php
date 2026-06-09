<?php

namespace App\Services;

use App\Models\Fencer;
use App\Models\Result;

/**
 * Records a result and applies the rating rule: an earned rating better than
 * the weapon's current one upgrades it (and the fencer's headline rating when
 * it's the primary weapon). Ratings are earned, never revoked. Shared by the
 * web results tracker and the MCP connector.
 */
class ResultRecorder
{
    /** @return array{result: Result, rating_upgraded: ?string} */
    public function record(Fencer $fencer, array $data): array
    {
        $result = $fencer->results()->create($data);

        return ['result' => $result, 'rating_upgraded' => $this->maybeUpgradeRating($fencer, $result)];
    }

    /** @return ?string upgrade message when a rating was applied */
    private function maybeUpgradeRating(Fencer $fencer, Result $result): ?string
    {
        $raw = trim((string) $result->rating_earned);
        $earned = strtoupper(substr($raw, 0, 1));
        if (! in_array($earned, Fencer::RATING_LADDER, true) || $earned === 'U') {
            return null;
        }

        $row = $fencer->weapons()->where('weapon', $result->weapon)->first();
        if (! $row) {
            return null;
        }

        $currentIdx = array_search(strtoupper(substr($row->rating, 0, 1)), Fencer::RATING_LADDER, true) ?: 0;
        $earnedIdx = array_search($earned, Fencer::RATING_LADDER, true);
        if ($earnedIdx <= $currentIdx) {
            return null;
        }

        $row->update(['rating' => $raw]);
        if ($row->is_primary) {
            $fencer->update(['rating' => $raw]);
        }

        return ucfirst($result->weapon)." rating updated to {$raw}.";
    }
}
