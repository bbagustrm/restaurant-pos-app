<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('restaurant.restaurant_name', 'POS Kafe');
        $this->migrator->add('restaurant.restaurant_address', 'Jl. Contoh No. 123, Yogyakarta');
        $this->migrator->add('restaurant.restaurant_phone', '+62 812-3456-7890');
        $this->migrator->add('restaurant.tax_percentage', 11.0);
        $this->migrator->add('restaurant.currency_symbol', 'Rp');
        $this->migrator->add('restaurant.receipt_footer', "Terima kasih atas kunjungan Anda!\nSampai jumpa lagi.");
        $this->migrator->add('restaurant.auto_print_receipt', false);
    }
};
