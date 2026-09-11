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

    protected $fillable = ['idea_id', 'layer', 'user_id', 'on_behalf_of_id', 'decision', 'note'];

    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

    /** Committee yang seharusnya memutus, bila keputusan diambil atas namanya. */
    public function onBehalfOf()
    {
        return $this->belongsTo(User::class, 'on_behalf_of_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
