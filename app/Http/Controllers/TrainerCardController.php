<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\Friendship;
use App\Models\User;
use App\Services\ProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Trainer-Karte im GameBoy-Stil und Bestenliste (spec.md 2.9, 2.10).
 */
class TrainerCardController extends Controller
{
    public function __construct(private readonly ProgressService $progress) {}

    public function show(Request $request): View
    {
        $user = $request->user();

        return view('trainer.card', [
            'user' => $user,
            'basis' => $this->progress->base($user),
            'regional' => $this->progress->regional($user),
            'shiny' => $this->progress->shiny($user),
            'generationen' => $this->progress->byGeneration($user),
            'freigeschaltet' => $user->achievements()->orderBy('sort_order')->get(),
            'offen' => Achievement::query()
                ->whereNotIn('id', $user->achievements()->pluck('achievements.id'))
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    /**
     * Rangliste, optional auf die eigene Freundesliste eingeschränkt
     * (spec.md 2.10).
     */
    public function leaderboard(Request $request): View
    {
        $user = $request->user();
        $nurFreunde = $request->boolean('freunde');

        $freundIds = $user->friends()->pluck('users.id')->push($user->id);

        $rangliste = User::query()
            ->select('users.id', 'users.name', 'users.xp')
            ->when($nurFreunde, fn ($q) => $q->whereIn('users.id', $freundIds))
            ->withCount([
                'ownerships as gesammelt' => fn ($q) => $q->where('owned', true),
                'ownerships as shinys' => fn ($q) => $q->where('owned_shiny', true),
            ])
            ->orderByDesc('xp')
            ->orderByDesc('gesammelt')
            ->limit(50)
            ->get();

        return view('trainer.leaderboard', [
            'rangliste' => $rangliste,
            'nurFreunde' => $nurFreunde,
            'eigenerRang' => $rangliste->search(fn (User $row) => $row->id === $user->id),
            'freunde' => $user->friends()->get(),
            'anfragen' => Friendship::with('user')
                ->where('friend_id', $user->id)
                ->where('status', Friendship::STATUS_PENDING)
                ->get(),
        ]);
    }

    public function addFriend(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $freund = User::where('email', $validated['email'])->firstOrFail();
        $user = $request->user();

        if ($freund->is($user)) {
            return back()->withErrors(['email' => 'Du kannst Dich nicht selbst hinzufügen.']);
        }

        Friendship::updateOrCreate(
            ['user_id' => $user->id, 'friend_id' => $freund->id],
            ['status' => Friendship::STATUS_PENDING],
        );

        return back()->with('status', "Freundschaftsanfrage an {$freund->name} verschickt.");
    }

    public function acceptFriend(Request $request, Friendship $friendship): RedirectResponse
    {
        abort_unless($friendship->friend_id === $request->user()->id, 403);

        DB::transaction(function () use ($friendship) {
            $friendship->update(['status' => Friendship::STATUS_ACCEPTED]);

            // Gegenrichtung mit anlegen, damit die Freundschaft beidseitig gilt.
            Friendship::updateOrCreate(
                ['user_id' => $friendship->friend_id, 'friend_id' => $friendship->user_id],
                ['status' => Friendship::STATUS_ACCEPTED],
            );
        });

        return back()->with('status', 'Freundschaft bestätigt.');
    }
}
