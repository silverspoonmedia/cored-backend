<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('client:health', function (): void {
    $this->comment('Client project console routes are loaded.');
})->purpose('Verify client console routes are loaded');
