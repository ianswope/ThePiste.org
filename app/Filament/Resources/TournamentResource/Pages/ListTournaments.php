<?php

namespace App\Filament\Resources\TournamentResource\Pages;

use App\Filament\Resources\TournamentResource;
use App\Models\Season;
use App\Services\TournamentImporter;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListTournaments extends ListRecords
{
    protected static string $resource = TournamentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('template')
                ->label('CSV template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    $rows = [
                        TournamentImporter::COLUMNS,
                        ['Example Open ROC/RJCC', '2026-10-17', '2026-10-18', 'Madison', 'WI', 'R2',
                            '', 'ROC|RJCC', 'JNR|CDT|D1A', '', 'Optional strategy note shown on the card.', 'https://example.com', '', ''],
                        ['October NAC', '2026-10-09', '2026-10-12', 'Orlando', 'FL', 'NATIONAL',
                            'yes', '', 'D1A|JNR|CDT', '', '', '', '28.538', '-81.379'],
                    ];

                    return response()->streamDownload(function () use ($rows) {
                        $out = fopen('php://output', 'w');
                        foreach ($rows as $row) {
                            fputcsv($out, $row);
                        }
                        fclose($out);
                    }, 'thepiste-tournaments-template.csv', ['Content-Type' => 'text/csv']);
                }),

            Actions\Action::make('importCsv')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Select::make('season_id')
                        ->label('Season')
                        ->options(Season::orderByDesc('starts_on')->pluck('name', 'id'))
                        ->default(fn () => Season::where('is_active', true)->value('id'))
                        ->required(),
                    FileUpload::make('file')
                        ->label('CSV file')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->disk('local')
                        ->directory('csv-imports')
                        ->required()
                        ->helperText('Use the CSV template for the expected columns. Rows are matched by name + start date, so re-importing updates events in place.'),
                ])
                ->action(function (array $data, TournamentImporter $importer) {
                    $csv = Storage::disk('local')->get($data['file']);
                    $summary = $importer->importCsv($csv, Season::findOrFail($data['season_id']));
                    Storage::disk('local')->delete($data['file']);

                    $body = "{$summary['created']} created, {$summary['updated']} updated, {$summary['geocoded']} geocoded.";
                    if ($summary['errors'] !== []) {
                        $shown = array_slice($summary['errors'], 0, 8);
                        $more = count($summary['errors']) - count($shown);
                        $body .= "\n".implode("\n", $shown).($more > 0 ? "\n…and {$more} more." : '');
                    }

                    Notification::make()
                        ->title($summary['errors'] === [] ? 'Import complete' : 'Import finished with issues')
                        ->body($body)
                        ->{$summary['errors'] === [] ? 'success' : 'warning'}()
                        ->persistent()
                        ->send();
                }),

            Actions\CreateAction::make(),
        ];
    }
}
