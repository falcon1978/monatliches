<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'accent_color',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function months()
    {
        return $this->hasMany(Month::class);
    }

    public function entries()
    {
        return $this->hasMany(Entry::class);
    }

    public function recurringTemplates()
    {
        return $this->hasMany(RecurringTemplate::class);
    }

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public static function accentPresets(): array
    {
        return [
            '#2f6f3e',
            '#1d4ed8',
            '#0f766e',
            '#7c3aed',
            '#c2410c',
            '#be123c',
            '#0ea5e9',
            '#f59e0b',
        ];
    }
}
