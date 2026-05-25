<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

// Public Kitchen channel — no auth callback required for public channels,
// but registering here documents the channel and its allowed events.
Broadcast::channel('kitchen', fn () => true);
