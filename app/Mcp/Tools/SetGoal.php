<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesFencer;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class SetGoal extends Tool
{
    use ResolvesFencer;

    protected string $description = 'Set a fencer\'s season goal. The goal drives which tournaments the planner recommends and how progress is measured.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'goal' => $schema->string()->enum(array_keys(config('fencing.goals')))
                ->description('Goal key: '.collect(config('fencing.goals'))->map(fn ($l, $k) => "{$k} = {$l}")->implode('; '))
                ->required(),
            'fencer' => $schema->string()->description('Fencer name or id (optional when the account has one fencer).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate(['goal' => 'required|in:'.implode(',', array_keys(config('fencing.goals')))]);

        $fencer = $this->fencer($request);
        $fencer->update(['goal' => $data['goal']]);

        return Response::text("{$fencer->name}'s goal is now: ".config('fencing.goals')[$data['goal']].'.');
    }
}
