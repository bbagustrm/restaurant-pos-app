<?php

namespace App\Filament\Pages;

use App\Settings\RestaurantSettings;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageRestaurantSettings extends SettingsPage
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static ?string $navigationLabel = 'Restoran';

    protected static ?string $title = 'Pengaturan Restoran';

    protected static ?int $navigationSort = 2;

    protected static string $settings = RestaurantSettings::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Restoran')
                    ->description('Informasi yang ditampilkan di invoice dan struk.')
                    ->columns(2)
                    ->components([
                        TextInput::make('restaurant_name')
                            ->label('Nama Restoran')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Textarea::make('restaurant_address')
                            ->label('Alamat')
                            ->required()
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpan(2),

                        TextInput::make('restaurant_phone')
                            ->label('Telepon')
                            ->tel()
                            ->required()
                            ->maxLength(50),

                        TextInput::make('currency_symbol')
                            ->label('Simbol Mata Uang')
                            ->required()
                            ->maxLength(10)
                            ->placeholder('Rp'),
                    ]),

                Section::make('Pajak & Pembayaran')
                    ->columns(2)
                    ->components([
                        TextInput::make('tax_percentage')
                            ->label('PPN')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.5)
                            ->suffix('%')
                            ->helperText('Default Indonesia: 11%.'),

                        Toggle::make('auto_print_receipt')
                            ->label('Auto Print Struk')
                            ->helperText('Cetak otomatis setelah checkout berhasil.')
                            ->inline(false),
                    ]),

                Section::make('Struk')
                    ->components([
                        Textarea::make('receipt_footer')
                            ->label('Footer Struk')
                            ->required()
                            ->rows(3)
                            ->maxLength(500)
                            ->helperText('Pesan terima kasih / info tambahan di bagian bawah struk.'),
                    ]),
            ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }
}
