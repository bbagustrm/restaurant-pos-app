<?php

use App\Settings\RestaurantSettings;

it('can resolve RestaurantSettings via the container with default values', function () {
    /** @var RestaurantSettings $settings */
    $settings = app(RestaurantSettings::class);

    expect($settings)->toBeInstanceOf(RestaurantSettings::class)
        ->and($settings->restaurant_name)->toBe('POS Kafe')
        ->and($settings->tax_percentage)->toBe(11.0)
        ->and($settings->currency_symbol)->toBe('Rp')
        ->and($settings->auto_print_receipt)->toBeFalse()
        ->and($settings->receipt_footer)->toContain('Terima kasih');
});

it('persists settings changes to the database', function () {
    $settings = app(RestaurantSettings::class);
    $settings->restaurant_name = 'Updated Cafe';
    $settings->tax_percentage = 12.5;
    $settings->save();

    $reloaded = app()->forgetInstance(RestaurantSettings::class)
        ? app(RestaurantSettings::class)
        : app(RestaurantSettings::class);

    expect($reloaded->restaurant_name)->toBe('Updated Cafe')
        ->and($reloaded->tax_percentage)->toBe(12.5);
});
