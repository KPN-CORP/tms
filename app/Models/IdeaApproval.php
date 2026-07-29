<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdeaApproval extends Model
{
    protected $fillable = ['idea_id', 'layer', 'user_id', 'decision', 'note'];

    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
