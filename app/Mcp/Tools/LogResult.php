<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ResolvesFencer;
use App\Services\ResultRecorder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class LogResult extends Tool
{
    use ResolvesFencer;

    protected string $description = 'Log a tournament result for a fencer (finish, field size, rating earned, points). An earned rating better than the current one upgrades the profile automatically.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'event_name' => $schema->string()->description('e.g. "Junior Women\'s Foil"')->required(),
            'fenced_on' => $schema->string()->description('Date fenced, YYYY-MM-DD.')->required(),
            'place' => $schema->integer()->description('Finishing place, 1 = won.')->required(),
            'weapon' => $schema->string()->enum(['foil', 'epee', 'sabre'])->description('Defaults to the fencer\'s primary weapon.'),
            'category' => $schema->string()->description('Y10/Y12/Y14/CDT/JNR/D1A/DV2/OPEN/VET (optional).'),
            'field_size' => $schema->integer()->description('Total entries (optional).'),
            'rating_earned' => $schema->string()->description('Rating earned at this event, e.g. "C26" (optional).'),
            'points' => $schema->number()->description('Regional/national points earned (optional).'),
            'tournament' => $schema->string()->description('Catalog tournament id or name fragment to link (optional).'),
            'notes' => $schema->string()->description('Free-form notes (optional).'),
            'fencer' => $schema->string()->description('Fencer name or id (optional).'),
        ];
    }

    public function handle(Request $request, ResultRecorder $recorder): Response
    {
        $data = $request->validate([
            'event_name' => 'required|string|max:160',
            'fenced_on' => 'required|date',
            'place' => 'required|integer|between:1,999',
            'weapon' => 'nullable|in:foil,epee,sabre',
            'category' => 'nullable|string|max:10',
            'field_size' => 'nullable|integer|between:1,999',
            'rating_earned' => 'nullable|string|max:4',
            'points' => 'nullable|numeric|between:0,10000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $fencer = $this->fencer($request);
        $data['weapon'] = $data['weapon'] ?? $fencer->weapon;

        if ($needle = trim((string) $request->get('tournament', ''))) {
            $match = ctype_digit($needle)
                ? $this->activeSeason()->tournaments()->whereKey((int) $needle)->first()
                : $this->activeSeason()->tournaments()->where('name', 'like', "%{$needle}%")->first();
            if ($match) {
                $data['tournament_id'] = $match->id;
            }
        }

        $outcome = $recorder->record($fencer, $data);

        $msg = "Logged: {$fencer->name} finished {$data['place']}".($data['field_size'] ?? null ? "/{$data['field_size']}" : '')
            ." in {$data['event_name']} on {$data['fenced_on']}.";
        if ($outcome['rating_upgraded']) {
            $msg .= ' 🎉 '.$outcome['rating_upgraded'];
        }

        return Response::text($msg);
    }
}
