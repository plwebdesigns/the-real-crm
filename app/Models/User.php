<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;

#[Fillable(['name', 'email', 'password', 'is_admin', 'is_super_admin', 'location_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_admin' => false,
        'is_super_admin' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'password_set_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_super_admin' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (User $user): void {
            if ($user->isDirty('password')) {
                $user->password_set_at = now();
            }
        });
    }

    public function hasSetPassword(): bool
    {
        return $this->password_set_at !== null;
    }

    /**
     * Build the signed link that confirms this email address.
     */
    public function emailVerificationUrl(): string
    {
        return URL::temporarySignedRoute(
            Filament::getDefaultPanel()->generateRouteName('auth.email-verification.accept'),
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            [
                'user' => $this->getKey(),
                'hash' => sha1($this->getEmailForVerification()),
            ],
        );
    }

    /**
     * Send Filament's signed verification link after the surrounding transaction commits.
     */
    public function sendEmailVerificationNotification(): void
    {
        $notification = app(VerifyEmail::class);
        $notification->url = $this->emailVerificationUrl();

        $this->notify($notification->afterCommit());
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function canAccessLocation(?int $locationId): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return $this->location_id !== null
            && $locationId !== null
            && $this->location_id === $locationId;
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsToMany<Sale, $this, SaleUser>
     */
    public function sales(): BelongsToMany
    {
        return $this->belongsToMany(Sale::class)
            ->using(SaleUser::class)
            ->withPivot(['commission_percent', 'net_commission'])
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<Lead, $this>
     */
    public function leads(): BelongsToMany
    {
        return $this->belongsToMany(Lead::class)
            ->withTimestamps();
    }

    /**
     * @return HasMany<LeadActivity, $this>
     */
    public function leadActivities(): HasMany
    {
        return $this->hasMany(LeadActivity::class);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): Builder
    {
        if ($user->is_super_admin) {
            return $query;
        }

        return $query->where('location_id', $user->location_id);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    #[Scope]
    protected function atLocation(Builder $query, ?int $locationId): Builder
    {
        if ($locationId === null) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where('location_id', $locationId);
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    #[Scope]
    protected function inLocation(Builder $query, ?int $locationId): Builder
    {
        if ($locationId === null) {
            return $query;
        }

        return $query->where($query->qualifyColumn('location_id'), $locationId);
    }
}
