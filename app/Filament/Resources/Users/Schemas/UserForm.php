<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profil')
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->maxLength(255)
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('Wajib saat membuat user baru. Kosongkan untuk tidak mengubah saat edit.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Role')
                    ->description('Pilih satu atau lebih role untuk user ini.')
                    ->components([
                        CheckboxList::make('roles')
                            ->label('')
                            ->options(fn () => Role::pluck('name', 'name'))
                            ->descriptions([
                                'super_admin' => 'Akses penuh ke seluruh sistem termasuk admin panel.',
                                'cashier' => 'Mengoperasikan POS untuk membuat order.',
                                'kitchen' => 'Memantau Kitchen Display dan update status item.',
                            ])
                            ->columns(1)
                            ->bulkToggleable()
                            // Don't try to write to a `roles` column; we sync via Spatie.
                            ->dehydrated(false)
                            // Hydrate from the user's currently assigned role names.
                            ->afterStateHydrated(function (CheckboxList $component, $state, $record): void {
                                if ($record) {
                                    $component->state($record->roles->pluck('name')->all());
                                }
                            })
                            // Sync roles after the User record is saved.
                            ->saveRelationshipsUsing(function ($record, $state): void {
                                /** @var User $record */
                                $record->syncRoles(array_values((array) $state));
                            }),
                    ]),
            ]);
    }
}
