<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * XP-Schwelle je Trainer-Level (spec.md 2.9). Level 1 startet bei 0 XP,
     * danach waechst der Abstand quadratisch: Level n braucht 50*(n-1)^2 + 50*(n-1) XP.
     */
    public const MAX_LEVEL = 100;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'xp',
        'login_streak',
        'last_login_date',
        'profile_public',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Die Spaltendefaults greifen erst in der Datenbank – ein frisch angelegtes
     * Modell hätte xp sonst als null, und die XP-Verrechnung liefe auf einen
     * Fehler. Hier stehen sie deshalb auch am Modell.
     *
     * @var array<string,mixed>
     */
    protected $attributes = [
        'xp' => 0,
        'login_streak' => 0,
        'profile_public' => false,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_date' => 'date',
            'profile_public' => 'boolean',
        ];
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    public function ownerships(): HasMany
    {
        return $this->hasMany(UserPokemonForm::class);
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class)->withTimestamps();
    }

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(Achievement::class)->withPivot('unlocked_at')->withTimestamps();
    }

    public function friendships(): HasMany
    {
        return $this->hasMany(Friendship::class);
    }

    public function friends(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'friendships', 'user_id', 'friend_id')
            ->wherePivot('status', Friendship::STATUS_ACCEPTED)
            ->withTimestamps();
    }

    /**
     * Legt Einstellungen bei Bedarf mit den Defaults an, damit der Rest der App
     * sich nie um einen fehlenden Datensatz kümmern muss.
     *
     * Das Ergebnis wird als geladene Relation zurückgeschrieben: Views rufen die
     * Methode mehrfach pro Request auf, und ohne das würde jeder weitere Aufruf
     * erneut einzufügen versuchen und am Unique-Index auflaufen.
     */
    public function settingsOrDefault(): UserSetting
    {
        if ($this->relationLoaded('settings') && $this->settings !== null) {
            return $this->settings;
        }

        $settings = $this->settings()->firstOrCreate([]);
        $this->setRelation('settings', $settings);

        return $settings;
    }

    /** Gesamt-XP -> Trainer-Level (spec.md 2.9). */
    public function level(): int
    {
        return self::levelForXp($this->xp ?? 0);
    }

    public static function levelForXp(int $xp): int
    {
        $level = 1;

        while ($level < self::MAX_LEVEL && $xp >= self::xpForLevel($level + 1)) {
            $level++;
        }

        return $level;
    }

    /** Benoetigte Gesamt-XP, um dieses Level zu erreichen. */
    public static function xpForLevel(int $level): int
    {
        $steps = max(0, $level - 1);

        return 50 * $steps * $steps + 50 * $steps;
    }

    /** Fortschritt innerhalb des aktuellen Levels in Prozent. */
    public function levelProgressPercent(): float
    {
        $level = $this->level();

        if ($level >= self::MAX_LEVEL) {
            return 100.0;
        }

        $current = self::xpForLevel($level);
        $next = self::xpForLevel($level + 1);
        $span = $next - $current;

        return $span > 0 ? round((($this->xp - $current) / $span) * 100, 1) : 0.0;
    }

    public function xpToNextLevel(): int
    {
        $level = $this->level();

        return $level >= self::MAX_LEVEL ? 0 : self::xpForLevel($level + 1) - $this->xp;
    }
}
