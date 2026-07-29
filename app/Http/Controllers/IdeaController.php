<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesFileAttachments;
use App\Models\BusinessUnit;
use App\Models\CommitteeAssignment;
use App\Models\Company;
use App\Models\Department;
use App\Models\Idea;
use App\Models\IdeaAttachment;
use App\Models\Location;
use App\Services\Idea\IdeaWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IdeaController extends Controller
{
    use HandlesFileAttachments;

    /**
     * My Ideas — ide milik user yang login (selain draft).
     */
    public function index(Request $request)
    {
        $ideas = Idea::where('user_id', $request->user()->id)
            ->where('status', '!=', 'draft')
            ->visibleTo($request->user())
            ->with(['businessUnit', 'department'])
            ->latest('modified_at')
            ->get();

        return view('ideas.index', compact('ideas'));
    }

    /**
     * Draft Ideas — ide milik user yang masih draft.
     */
    public function drafts(Request $request)
    {
        $ideas = Idea::where('user_id', $request->user()->id)
            ->where('status', 'draft')
            ->with(['businessUnit', 'department'])
            ->latest('modified_at')
            ->get();

        return view('ideas.drafts', compact('ideas'));
    }

    /**
     * Detail ide (read-only). Hanya pemilik ide.
     */
    public function show(Request $request, Idea $idea)
    {
        abort_unless($idea->user_id === $request->user()->id, 403);

        $idea->load(['businessUnit', 'department', 'user.businessUnit', 'user.department', 'attachments']);

        return view('ideas.show', compact('idea'));
    }

    public function create()
    {
        return view('ideas.create', $this->formData());
    }

    public function store(Request $request)
    {
        $isSubmit  = $request->input('action') === 'submit';
        $validated = $this->validateIdea($request, $isSubmit);

        $idea = Idea::create($validated + [
            'user_id' => $request->user()->id,
            'idea_id' => $this->generateIdeaId($request->user()),
            'status'  => $isSubmit ? 'submitted' : 'draft',
        ]);

        $this->storeAttachments($request, $idea);

        return redirect()
            ->route($isSubmit ? 'ideas.index' : 'ideas.drafts')
            ->with('success', $isSubmit
                ? "Idea \"{$idea->idea_name}\" submitted ({$idea->idea_id})."
                : "Draft \"{$idea->idea_name}\" saved.");
    }

    public function edit(Request $request, Idea $idea)
    {
        $this->authorizeOwnerDraft($request, $idea);

        return view('ideas.edit', $this->formData() + ['idea' => $idea]);
    }

    public function update(Request $request, Idea $idea)
    {
        $this->authorizeOwnerDraft($request, $idea);

        $isSubmit  = $request->input('action') === 'submit';
        $validated = $this->validateIdea($request, $isSubmit);

        $idea->update($validated + [
            'status' => $isSubmit ? 'submitted' : 'draft',
        ]);

        $this->storeAttachments($request, $idea);

        return redirect()
            ->route($isSubmit ? 'ideas.index' : 'ideas.drafts')
            ->with('success', $isSubmit
                ? "Idea \"{$idea->idea_name}\" submitted ({$idea->idea_id})."
                : "Draft \"{$idea->idea_name}\" updated.");
    }

    public function destroy(Request $request, Idea $idea)
    {
        $this->authorizeOwnerDraft($request, $idea);

        $idea->delete();

        return redirect()
            ->route('ideas.drafts')
            ->with('success', 'Draft deleted.');
    }

    /**
     * Review Ideas — antrean ide yang menunggu review oleh committee ini
     * (di layer masing-masing, sesuai routing waterfall).
     */
    public function review(Request $request, IdeaWorkflowService $wf)
    {
        $ideas = $wf->reviewQueueFor($request->user())
            ->with(['businessUnit', 'department', 'user'])
            ->latest('modified_at')
            ->get();

        return view('ideas.review', compact('ideas'));
    }

    /**
     * Detail review + aksi approve/reject.
     */
    public function reviewShow(Request $request, Idea $idea, IdeaWorkflowService $wf)
    {
        $user = $request->user();

        // Hanya committee dari BU ide ini (di layer mana pun) yang boleh melihat.
        $isCommitteeOfBu = CommitteeAssignment::where('business_unit_id', $idea->business_unit_id)
            ->where('user_id', $user->id)
            ->exists();
        abort_unless($isCommitteeOfBu, 403);

        // FR-078: begitu dibuka reviewer layer aktif -> On Review.
        if ($wf->isCurrentReviewer($idea, $user)) {
            $wf->markOnReview($idea);
            $idea->refresh();
        }

        $idea->load(['businessUnit', 'department', 'user.businessUnit', 'user.department', 'approvals.user']);

        return view('ideas.review-show', [
            'idea'      => $idea,
            'canReview' => $wf->isCurrentReviewer($idea, $user),
        ]);
    }

    public function approve(Request $request, Idea $idea, IdeaWorkflowService $wf)
    {
        abort_unless($wf->isCurrentReviewer($idea, $request->user()), 403);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;
        $wf->approve($idea, $request->user(), $note);

        return redirect()->route('ideas.review')
            ->with('success', "Idea \"{$idea->idea_name}\" approved for your layer.");
    }

    public function reject(Request $request, Idea $idea, IdeaWorkflowService $wf)
    {
        abort_unless($wf->isCurrentReviewer($idea, $request->user()), 403);

        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;
        $wf->reject($idea, $request->user(), $note);

        return redirect()->route('ideas.review')
            ->with('success', "Idea \"{$idea->idea_name}\" rejected.");
    }

    /* ---- Attachments (T-17) ------------------------------------------ */

    public function downloadAttachment(Request $request, Idea $idea, IdeaAttachment $attachment)
    {
        abort_unless($attachment->idea_id === $idea->id, 404);

        // Pemilik ide, atau committee dari BU ide ini, boleh mengunduh.
        $user      = $request->user();
        $isOwner   = $idea->user_id === $user->id;
        $isCommittee = CommitteeAssignment::where('business_unit_id', $idea->business_unit_id)
            ->where('user_id', $user->id)->exists();
        abort_unless($isOwner || $isCommittee || $user->hasRole('Super Admin'), 403);

        return $this->downloadAttachmentFile($attachment->file_path, $attachment->file_name);
    }

    public function destroyAttachment(Request $request, Idea $idea, IdeaAttachment $attachment)
    {
        $this->authorizeOwnerDraft($request, $idea); // hanya pemilik & saat draft
        abort_unless($attachment->idea_id === $idea->id, 404);

        $this->deleteAttachmentFile($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'Lampiran dihapus.');
    }

    /** Simpan file dari input attachments[] (opsional, multi-file). */
    private function storeAttachments(Request $request, Idea $idea): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        $request->validate(['attachments.*' => $this->attachmentRules()]);

        foreach ($request->file('attachments') as $file) {
            $idea->attachments()->create(
                $this->storeAttachmentFile($file, "idea-attachments/{$idea->id}", $request->user()->id)
            );
        }
    }

    

    /* ----------------------------------------------------------------- */

    private function formData(): array
    {
        return [
            'businessUnits' => BusinessUnit::orderBy('name')->get(),
            'departments'   => Department::with('businessUnit')->orderBy('name')->get(),
            'companies'     => Company::orderBy('name')->get(),
            'locations'     => Location::with('company')->orderBy('name')->get(),
        ];
    }

    

    private function validateIdea(Request $request, bool $isSubmit): array
    {
        // Saat submit semua mandatory; saat draft cukup idea_name.
        $required = $isSubmit ? 'required' : 'nullable';

        return $request->validate([
            'idea_name'        => ['required', 'string', 'max:255'],
            'problem'          => [$required, 'string', 'max:5000'],
            'description'      => [$required, 'string', 'max:10000'],
            'expected_outcome' => [$required, 'string', 'max:5000'],
            'business_unit_id' => [$required, 'integer', Rule::exists('business_units', 'id')],
            // Company & Location opsional (level BU bila kosong).
            'company_id'       => ['nullable', 'integer', Rule::exists('companies', 'id')],
            'location_id'      => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'department_id'    => [$required, 'integer', Rule::exists('departments', 'id')],
        ]);
    }


    private function authorizeOwnerDraft(Request $request, Idea $idea): void
    {
        abort_unless($idea->user_id === $request->user()->id, 403);
        abort_unless($idea->status === 'draft', 403, 'Only draft ideas can be edited.');
    }

    /**
     * Format: I-{BU}-YYYYMMDD-00000. BU = business unit pembuat (source).
     */
    private function generateIdeaId(\App\Models\User $creator): string
    {
        $buCode = BusinessUnit::find($creator->business_unit_id)?->code ?? 'GEN';
        $date   = now()->format('Ymd');
        $seq    = Idea::whereDate('created_at', now()->toDateString())->count() + 1;

        return sprintf('I-%s-%s-%05d', $buCode, $date, $seq);
    }
    
}
