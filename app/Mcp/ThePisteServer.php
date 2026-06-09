<?php

namespace App\Mcp;

use App\Mcp\Tools\GetPlan;
use App\Mcp\Tools\GetProgress;
use App\Mcp\Tools\GetSeasonOutlook;
use App\Mcp\Tools\ListFencers;
use App\Mcp\Tools\LogResult;
use App\Mcp\Tools\ManagePlan;
use App\Mcp\Tools\SetGoal;
use Laravel\Mcp\Server;

class ThePisteServer extends Server
{
    protected string $name = 'ThePiste';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
        ThePiste (thepiste.org) is a USA Fencing season planner. The authenticated
        account owns one or more fencer profiles. Typical flows:

        - "Plan the season": list-fencers -> set-goal (if needed) -> get-season-outlook
          (anchors are tier nac/home; best value is priority/drive) -> manage-plan to
          add/remove events -> get-plan to confirm tallies.
        - "How's the season going": get-progress (rating ladder + stats + results).
        - "Log Sunday's result": log-result; earned ratings (e.g. "C26") upgrade the
          profile automatically.

        Tournament tiers are computed per fencer (eligibility, distance from home,
        region, weekend conflicts) — recommendations should respect non_negotiable
        events and avoid adding both sides of a conflicts_with pair.
    MARKDOWN;

    protected array $tools = [
        ListFencers::class,
        SetGoal::class,
        GetSeasonOutlook::class,
        GetPlan::class,
        ManagePlan::class,
        LogResult::class,
        GetProgress::class,
    ];
}
