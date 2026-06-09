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

    protected string $description = 'Add a structured season goal for a fencer. Types: rating (earn a letter, needs target_rating), qualify (championship path, needs target), standing (regional points, optional category), develop (mileage, needs target_events). A goal of the same type and weapon replaces the existing one. Goals drive which tournaments the planner recommends.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['rating', 'qualify', 'standing', 'develop'])->required()
                ->description('Goal type.'),
            'weapon' => $schema->string()->enum(['foil', 'epee', 'sabre'])
                ->description('Weapon (defaults to the fencer\'s primary; ignored for develop).'),
            'target_rating' => $schema->string()->enum(['E', 'D', 'C', 'B', 'A'])
                ->description('For rating goals: the letter to earn.'),
            'target' => $schema->string()->enum(array_keys(config('fencing.qualify_targets')))
                ->description('For qualify goals: '.collect(config('fencing.qualify_targets'))->map(fn ($t, $k) => "{$k} = {$t['label']}")->implode('; ')),
            'category' => $schema->string()
                ->description('For standing goals: category (e.g. JNR, CDT); omit for any.'),
            'target_events' => $schema->integer()
                ->description('For develop goals: number of events to fence this season.'),
            'fencer' => $schema->string()->description('Fencer name or id (optional when the account has one fencer).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate([
            'type' => 'required|in:rating,qualify,standing,develop',
            'weapon' => 'nullable|in:foil,epee,sabre',
            'target_rating' => 'required_if:type,rating|nullable|in:E,D,C,B,A',
            'target' => 'required_if:type,qualify|nullable|in:'.implode(',', array_keys(config('fencing.qualify_targets'))),
            'category' => 'nullable|string|max:6',
            'target_events' => 'required_if:type,develop|nullable|integer|between:1,60',
        ]);

        $fencer = $this->fencer($request);

        $params = match ($data['type']) {
            'rating' => ['target_rating' => $data['target_rating']],
            'qualify' => ['target' => $data['target']],
            'standing' => ['category' => $data['category'] ?? null],
            'develop' => ['target_events' => (int) $data['target_events']],
        };

        $weapon = $data['type'] === 'develop' ? null : ($data['weapon'] ?? $fencer->weapon);

        $fencer->goals()->active()
            ->where('type', $data['type'])
            ->where('weapon', $weapon)
            ->delete();

        $goal = $fencer->goals()->create([
            'type' => $data['type'],
            'weapon' => $weapon,
            'params' => $params,
            'status' => 'active',
        ]);

        $all = $fencer->activeGoals()->map->label()->implode('; ');

        return Response::text("Added for {$fencer->name}: {$goal->label()}. Active goals: {$all}.");
    }
}
