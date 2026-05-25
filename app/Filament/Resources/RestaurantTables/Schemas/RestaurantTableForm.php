<?php

namespace App\Filament\Resources\RestaurantTables\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RestaurantTableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(50)
                    ->placeholder('e.g. Meja 1, Bar 2'),

                TextInput::make('capacity')
                    ->label('Kapasitas')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->default(4),

                Select::make('status')
                    ->label('Status')
                    ->required()
                    ->default('available')
                    ->options([
                        'available' => 'Tersedia',
                        'occupied' => 'Terisi',
                        'reserved' => 'Direservasi',
                    ]),
            ]);
    }
}
