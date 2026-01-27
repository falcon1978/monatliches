<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function entries()
    {
        return $this->hasMany(Entry::class);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public static function createDefaultsForUser(User $user): void
    {
        $defaults = [
            ['name' => 'Bank', 'type' => 'ist'],
            ['name' => 'Bar', 'type' => 'ist'],
            ['name' => 'Kundeneinnahmen offen', 'type' => 'forecast'],
            ['name' => 'Partnerin Verrechnung', 'type' => 'clearing'],
        ];

        foreach ($defaults as $account) {
            static::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => $account['name'],
                ],
                ['type' => $account['type']]
            );
        }
    }
}
