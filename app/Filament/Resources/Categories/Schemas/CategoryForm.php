<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Kategori')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                        // Only auto-fill the slug if the user hasn't customized it.
                        if (($get('slug') ?? '') !== Str::slug($old ?? '')) {
                            return;
                        }

                        $set('slug', Str::slug($state ?? ''));
                    }),

                TextInput::make('slug')
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true)
                    ->helperText('Otomatis dari nama, bisa diubah manual.'),

                TextInput::make('icon')
                    ->label('Icon (Heroicon)')
                    ->placeholder('e.g. fire, cake, beaker')
                    ->maxLength(50)
                    ->helperText('Nama Heroicon tanpa prefix. Contoh: fire, cake, beaker.'),

                ColorPicker::make('color')
                    ->label('Warna Tema')
                    ->regex('/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/'),

                TextInput::make('sort_order')
                    ->label('Urutan')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
                    ->required(),
            ]);
    }
}
