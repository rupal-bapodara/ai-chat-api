<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ChatController::class, 'index']);
Route::post('/upload', [ChatController::class, 'upload'])->name('documents.upload');
Route::delete('/documents/{document}', [ChatController::class, 'delete'])->name('documents.delete');
Route::post('/chat', [ChatController::class, 'chat']);