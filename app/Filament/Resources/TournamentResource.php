<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TournamentResource\Pages;
use App\Models\Tournament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TournamentResource extends Resource
{
    protected static ?string $model = Tournament::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Select::make('season_id')
                    ->relationship('season', 'name')
                    ->required(),
                Forms\Components\Select::make('host_club_id')
                    ->relationship('hostClub', 'name')
                    ->searchable()
                    ->helperText('Set only for events hosted by a fencer\'s home club.'),
                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->helperText('Unique key, e.g. grafa-third-coast-cup-2026-11-06.'),
                Forms\Components\Toggle::make('is_nac')
                    ->label('NAC / national event'),
                Forms\Components\Select::make('level')
                    ->options(Tournament::LEVELS)
                    ->default('regional')
                    ->required(),
                Forms\Components\TextInput::make('country')
                    ->maxLength(2)
                    ->default('US')
                    ->helperText('ISO code, e.g. US, FR, PL.'),
                Forms\Components\DatePicker::make('starts_on')
                    ->required(),
                Forms\Components\DatePicker::make('ends_on')
                    ->required(),
                Forms\Components\TextInput::make('city'),
                Forms\Components\TextInput::make('state')
                    ->maxLength(2),
                Forms\Components\TextInput::make('region')
                    ->helperText('R1-R6, or NATIONAL for NACs.'),
                Forms\Components\TextInput::make('lat')
                    ->numeric()
                    ->helperText('Decimal degrees; used for drive/fly distance.'),
                Forms\Components\TextInput::make('lng')
                    ->numeric(),
                Forms\Components\Select::make('contested_events')
                    ->multiple()
                    ->options(Tournament::CATEGORIES)
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TagsInput::make('circuits')
                    ->placeholder('RYC, RJCC, ROC, ...')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('curated_note')
                    ->rows(3)
                    ->helperText('Overrides the auto-generated note for marquee events.')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('source_url')
                    ->url()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on')
            ->columns([
                Tables\Columns\TextColumn::make('starts_on')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->wrap()
                    ->description(fn (Tournament $r) => $r->location()),
                Tables\Columns\TextColumn::make('region')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_nac')
                    ->label('NAC')
                    ->boolean(),
                Tables\Columns\TextColumn::make('hostClub.name')
                    ->label('Home club')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('contested_events')
                    ->label('Categories')
                    ->badge(),
                Tables\Columns\TextColumn::make('season.name')
                    ->label('Season')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_nac')->label('NACs only'),
                Tables\Filters\SelectFilter::make('region')
                    ->options(fn () => Tournament::query()->whereNotNull('region')->distinct()->orderBy('region')->pluck('region', 'region')->all()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTournaments::route('/'),
            'create' => Pages\CreateTournament::route('/create'),
            'edit' => Pages\EditTournament::route('/{record}/edit'),
        ];
    }
}
