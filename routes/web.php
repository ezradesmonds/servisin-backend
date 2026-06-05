<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response('Servisin API ready');
});
