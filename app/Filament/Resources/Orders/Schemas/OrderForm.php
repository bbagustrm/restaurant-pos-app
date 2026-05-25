<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Read-only "form" used by the ViewAction modal of OrderResource.
 *
 * It is a Schema of infolist entries — never editable inputs.
 */
class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Info Order')
                    ->columns(3)
                    ->components([
                        TextEntry::make('order_number')->label('No. Order')->copyable(),
                        TextEntry::make('status')->label('Status')->badge(),
                        TextEntry::make('created_at')->label('Dibuat')->dateTime('d M Y H:i'),
                        TextEntry::make('table.name')->label('Meja')->placeholder('Takeaway'),
                        TextEntry::make('cashier.name')->label('Kasir'),
                        TextEntry::make('payment_method')->label('Pembayaran')->badge()->placeholder('—'),
                    ]),

                Section::make('Item')
                    ->components([
                        RepeatableEntry::make('orderItems')
                            ->label('')
                            ->columns(4)
                            ->components([
                                TextEntry::make('product_name')->label('Produk')->columnSpan(2),
                                TextEntry::make('quantity')->label('Qty'),
                                TextEntry::make('product_price')
                                    ->label('Harga')
                                    ->formatStateUsing(fn ($state) => rupiah((int) $state)),
                            ]),
                    ]),

                Section::make('Ringkasan')
                    ->columns(2)
                    ->components([
                        TextEntry::make('subtotal')
                            ->formatStateUsing(fn ($state) => rupiah((int) $state)),
                        TextEntry::make('discount_amount')
                            ->label('Diskon')
                            ->formatStateUsing(fn ($state) => rupiah((int) $state)),
                        TextEntry::make('tax_amount')
                            ->label(fn ($record) => 'Pajak ('.$record->tax_percentage.'%)')
                            ->formatStateUsing(fn ($state) => rupiah((int) $state)),
                        TextEntry::make('total_amount')
                            ->label('Total')
                            ->formatStateUsing(fn ($state) => rupiah((int) $state))
                            ->weight('bold'),
                    ]),
            ]);
    }
}
