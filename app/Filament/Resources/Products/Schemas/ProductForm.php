<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Info Dasar')
                    ->description('Nama, kategori, dan harga produk.')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Nama Produk')
                            ->required()
                            ->maxLength(150)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                                if (($get('slug') ?? '') !== Str::slug($old ?? '')) {
                                    return;
                                }
                                $set('slug', Str::slug($state ?? ''));
                            }),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(150)
                            ->unique(ignoreRecord: true)
                            ->helperText('Otomatis dari nama, bisa diubah manual.'),

                        Select::make('category_id')
                            ->label('Kategori')
                            ->options(fn () => Category::orderBy('sort_order')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('price')
                            ->label('Harga')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix('Rp')
                            ->step(500)
                            ->columnSpan(1),

                        RichEditor::make('description')
                            ->label('Deskripsi')
                            ->columnSpanFull(),
                    ]),

                Section::make('Media')
                    ->description('Foto produk (opsional, max 2MB).')
                    ->components([
                        FileUpload::make('image')
                            ->label('Gambar Produk')
                            ->image()
                            ->disk('public')
                            ->directory('products')
                            ->imageEditor()
                            ->imagePreviewHeight('200')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp']),
                    ]),

                Section::make('Pengaturan')
                    ->description('Status dan urutan tampilan.')
                    ->columns(3)
                    ->components([
                        Toggle::make('is_available')
                            ->label('Tersedia')
                            ->helperText('Bisa dipesan di POS.')
                            ->default(true),

                        Toggle::make('is_featured')
                            ->label('Featured')
                            ->helperText('Tampil sebagai unggulan.')
                            ->default(false),

                        TextInput::make('sort_order')
                            ->label('Urutan')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                    ]),
            ]);
    }
}
