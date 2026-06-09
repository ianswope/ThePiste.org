<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesFencer;
use App\Models\Tournament;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ManagePlan extends Tool
{
    use ResolvesFencer;

    protected string $description = 'Add a tournament to a fencer\'s season plan or remove one. Identify the tournament by id (from get-season-outlook / search-tournaments) or by a unique name fragment.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()->enum(['add', 'remove'])->required(),
            'tournament' => $schema->string()->description('Tournament id or name fragment.')->required(),
            'fencer' => $schema->string()->description('Fencer name or id (optional).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'action' => 'required|in:add,remove',
            'tournament' => 'required|string',
        ]);

        $fencer = $this->fencer($request);
        $season = $this->activeSeason();

        $needle = trim($data['tournament']);
        $query = $season->tournaments();
        $matches = ctype_digit($needle)
            ? $query->whereKey((int) $needle)->get()
            : $query->where('name', 'like', "%{$needle}%")->get();

        if ($matches->isEmpty()) {
            return Response::error("No tournament matching \"{$needle}\" in season {$season->name}.");
        }
        if ($matches->count() > 1) {
            return Response::error("\"{$needle}\" is ambiguous: ".$matches->map(fn (Tournament $t) => "[{$t->id}] {$t->name} ({$t->starts_on->format('M j')})")->implode('; '));
        }

        $tournament = $matches->first();
        $plan = $this->plan($fencer);

        if ($data['action'] === 'add') {
            $plan->items()->firstOrCreate(['tournament_id' => $tournament->id]);

            return Response::text("Added {$tournament->name} ({$tournament->starts_on->format('M j')}, {$tournament->city}, {$tournament->state}) to {$fencer->name}'s plan.");
        }

        $plan->items()->where('tournament_id', $tournament->id)->delete();

        return Response::text("Removed {$tournament->name} from {$fencer->name}'s plan.");
    }
}
