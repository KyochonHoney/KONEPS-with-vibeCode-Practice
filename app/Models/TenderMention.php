<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderMention extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'tender_id',
        'user_id',
        'mention',
    ];

    /**
     * Get the tender that owns the mention.
     */
    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    /**
     * Get the user that owns the mention.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
