<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Services\RBAC\RoleScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Idea extends Model
{
    use LogsActivity;

    // Tabel ideas memakai modified_at (bukan updated_at bawaan Laravel).
    const UPDATED_AT = 'modified_at';

    /** Label untuk audit trail (LogsActivity). */
    public function activityLabel(): string
    {
        return $this->idea_id ?? ($this->idea_name ?? 'Idea #' . $this->getKey());
    }

    /** Jenis approval untuk SLA (ide selalu 'idea' saat direview). */
    public function slaApprovalType(): ?string
    {
        return in_array($this->status, ['submitted', 'review'], true) ? 'idea' : null;
    }

    /** Kapan masuk layer review saat ini (approval terakhir, atau waktu submit). */
    public function reviewSince(): ?\Illuminate\Support\Carbon
    {
        return $this->approvals()->latest('created_at')->first()?->created_at ?? $this->modified_at;
    }

    /**
     * Batasi ide sesuai restrict scope role $user (union lintas role),
     * hierarki Business Unit -> Company -> Location.
     * Kolom company/location NULL = ide level BU → tetap tampil bila BU cocok
     * (restrict Company/Location hanya menyembunyikan ide yang ditandai ke
     * company/location di luar scope).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $scope = app(RoleScopeService::class);

        return $query
            ->whereIn('business_unit_id', $scope->businessUnitIds($user))
            ->where(fn ($q) => $q->whereNull('company_id')->orWhereIn('company_id', $scope->companyIds($user)))
            ->where(fn ($q) => $q->whereNull('location_id')->orWhereIn('location_id', $scope->locationIds($user)));
    }

    protected $fillable = [
        'user_id',
        'idea_id',
        'idea_name',
        'problem',
        'description',
        'expected_outcome',
        'business_unit_id',
        'business_unit_name',
        'company_id',
        'company_name',
        'location_id',
        'location_name',
        'department_id',
        'department_name',
        'idea_docs',
        'status',
        'current_layer',
        'project_id',
        'feedback',
        'feedback_docs',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvals()
    {
        return $this->hasMany(IdeaApproval::class)->latest();
    }

    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function attachments()
    {
        return $this->hasMany(IdeaAttachment::class)->latest();
    }
}
