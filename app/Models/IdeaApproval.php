<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class IdeaApproval extends Model
{
    use LogsActivity;

    public function activityLabel(): string
    {
        return ucfirst((string) $this->decision) . ' — Layer ' . $this->layer . ' (idea #' . $this->idea_id . ')';
    }

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
