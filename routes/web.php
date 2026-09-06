<?php

use App\Http\Controllers\CollectionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PokedexController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\TrainerCardController;
use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

/*
| Der Pokédex ist auch ohne Login lesbar – ohne Spielebesitz rechnet die
| Prioritäts-Engine dann mit dem Gast-Kontext (spec.md 2.7).
*/
Route::get('/pokedex', [PokedexController::class, 'index'])->name('pokedex.index');
Route::get('/pokedex/{pokemon}', [PokedexController::class, 'show'])->name('pokedex.show');

// Öffentliches Profil – nur erreichbar, wenn der Nutzer es freigegeben hat (spec.md 2.11)
Route::get('/trainer/{user}', PublicProfileController::class)->name('trainer.public');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/statistik', StatisticsController::class)->name('statistics');

    // Sammlungsstand (spec.md 2.5)
    Route::post('/sammlung/{form}/umschalten', [CollectionController::class, 'toggle'])
        ->name('collection.toggle');
    Route::get('/sammlung/masseneingabe', [CollectionController::class, 'bulkForm'])
        ->name('collection.bulk');
    Route::post('/sammlung/masseneingabe/vorschau', [CollectionController::class, 'bulkPreview'])
        ->name('collection.bulk.preview');
    Route::post('/sammlung/masseneingabe', [CollectionController::class, 'bulkApply'])
        ->name('collection.bulk.apply');

    // Sammlungsstand sichern und einspielen
    Route::get('/sammlung/uebertragen', [TransferController::class, 'index'])
        ->name('collection.transfer');
    Route::get('/sammlung/export', [TransferController::class, 'export'])
        ->name('collection.export');
    Route::post('/sammlung/import', [TransferController::class, 'import'])
        ->name('collection.import');

    // Einstellungen (spec.md 2.6)
    Route::get('/einstellungen', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/einstellungen', [SettingsController::class, 'update'])->name('settings.update');

    // Trainer-Karte und Bestenliste (spec.md 2.9, 2.10)
    Route::get('/trainerkarte', [TrainerCardController::class, 'show'])->name('trainer.card');
    Route::get('/bestenliste', [TrainerCardController::class, 'leaderboard'])->name('trainer.leaderboard');
    Route::post('/freunde', [TrainerCardController::class, 'addFriend'])->name('friends.add');
    Route::post('/freunde/{friendship}/bestaetigen', [TrainerCardController::class, 'acceptFriend'])
        ->name('friends.accept');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
