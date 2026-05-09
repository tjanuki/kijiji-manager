<?php

use App\Support\Toast;
use Inertia\Inertia;

it('flashes a success toast through Inertia::flash', function () {
    Inertia::shouldReceive('flash')
        ->once()
        ->with('toast', ['type' => 'success', 'message' => 'Saved.']);

    Toast::success('Saved.');
});

it('flashes an error toast through Inertia::flash', function () {
    Inertia::shouldReceive('flash')
        ->once()
        ->with('toast', ['type' => 'error', 'message' => 'Failed.']);

    Toast::error('Failed.');
});
