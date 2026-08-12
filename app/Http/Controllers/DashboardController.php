<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use App\Models\ImplementationIndicator;
use App\Models\Project;

class DashboardController extends Controller
{
    public function index()
    {
        // Dashboard hanya untuk Admin/Super Admin. Non-admin diarahkan ke My Ideas
        // (menutup semua jalur: SSO, RedirectIfAuthenticated, akses URL langsung).
        $user = auth()->user();
        if (! $user || ! $user->hasAnyRole(['Admin', 'Super Admin'])) {
            return redirect()->route('ideas.index');
        }

        return view('dashboard', [
            'idea'    => $this->ideaMetrics(),
            'project' => $this->projectMetrics(),
        ]);
    }

    /** Idea Dashboard (T-10). */
    private function ideaMetrics(): array
    {
        $nonDraft = ['submitted', 'review', 'approved', 'rejected', 'project_created'];

        return [
            'draft'             => Idea::where('status', 'draft')->count(),
            'cumulative_submit' => Idea::whereIn('status', $nonDraft)->count(),
            'in_submitted'      => Idea::where('status', 'submitted')->count(),
            'approved'          => Idea::whereIn('status', ['approved', 'project_created'])->count(),
            'projects_generated' => Project::count(),
        ];
    }

    /** Project Dashboard (T-11). */
    private function projectMetrics(): array
    {
        $approvedPlus = ['approved', 'ongoing', 'delayed', 'completion_review', 'completed'];

        $avgImprovement = ImplementationIndicator::whereIn(
            'project_id',
            Project::where('status', 'completed')->pluck('id')
        )->avg('improvement');

        return [
            'in_submitted'   => Project::where('status', 'submitted')->count(),
            'cumulative_appr' => Project::whereIn('status', $approvedPlus)->count(),
            'ongoing'        => Project::where('status', 'ongoing')->count(),
            'delayed'        => Project::where('status', 'delayed')->count(),
            'completed'      => Project::where('status', 'completed')->count(),
            'avg_improvement' => $avgImprovement ? round((float) $avgImprovement, 2) : 0,
        ];
    }
}
