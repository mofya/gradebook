<?php

namespace App\Filament\Resources\GradeQueryResource\RelationManagers;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Hidden::make('user_id')
                    ->default(auth()->id()),
                Forms\Components\Textarea::make('body')
                    ->required()
                    ->rows(3)
                    ->label('Message'),
                Forms\Components\Toggle::make('is_internal_note')
                    ->label('Internal Note (staff only)')
                    ->default(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Author')
                    ->sortable(),
                Tables\Columns\TextColumn::make('body')
                    ->limit(80)
                    ->wrap(),
                Tables\Columns\IconColumn::make('is_internal_note')
                    ->boolean()
                    ->label('Internal'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'asc')
            ->headerActions([
                Actions\CreateAction::make()->label('Add Reply'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->modalHeading('Delete Message')
                    ->modalDescription('Are you sure? This will permanently remove this message from the query.'),
            ]);
    }
}
