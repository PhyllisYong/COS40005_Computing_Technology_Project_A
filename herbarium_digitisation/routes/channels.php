<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * Private channel authorization for LeafMachine2 digitisation job updates.
 * A user may only subscribe to their own job progress updates.
 */
Broadcast::channel('digitisation.user.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});
