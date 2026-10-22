<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Flash Messages
    |--------------------------------------------------------------------------
    |
    | One-off messages shown after a redirect (HandleInertiaRequests shares them
    | as `flash.success` / `flash.error`, and UiToastHost renders them).
    |
    | The locale comes from the `tms-locale` cookie that the language switcher
    | writes -- see App\Http\Middleware\SetLocale. These messages appear right
    | after a request, so the cookie is always current.
    |
    */

    'ideas' => [
        'draft_saved' => 'Draft :id saved.',
        'draft_updated' => 'Draft :id updated.',
        'submitted' => 'Idea :id submitted.',
        'draft_deleted' => 'Draft :id deleted.',
        'approved' => 'Idea :id approved for your layer.',
        'approved_on_behalf' => 'Idea :id approved on behalf of :name.',
        'rejected' => 'Idea :id rejected.',
        'rejected_on_behalf' => 'Idea :id rejected on behalf of :name.',
        'attachment_deleted' => 'Attachment deleted.',
    ],

    'projects' => [
        'shell_draft_saved' => 'Project shell saved as draft.',
        'shell_created' => 'Project shell created (:id).',
        'shell_draft_deleted' => 'Draft project shell deleted.',
        'proposal_draft_saved' => 'Project Proposal saved as draft.',
        'needs_plan_indicator' => 'At least 1 Implementation Plan and 1 Success Indicator are required before submitting.',
        'weight_must_be_100' => 'Total Success Indicator weightage must be 100% (currently :total%).',
        'proposal_submitted' => 'Proposal submitted to the Project Sponsor.',
        'sponsor_approved' => 'Proposal approved by the Sponsor.',
        'sponsor_revision' => 'Proposal returned to the Project Leader for revision.',
        'review_approved' => 'Decision saved for :id.',
        'review_approved_on_behalf' => ':id approved on behalf of :name.',
        'review_revision' => 'Revision required: :id returned to the Project Leader.',
        'review_revision_on_behalf' => 'Revision required on behalf of :name: :id returned to the Project Leader.',
        'review_rejected' => 'Rejected: :id.',
        'review_rejected_on_behalf' => 'Rejected on behalf of :name: :id.',
        'completion_needs_indicator' => 'At least 1 Success Indicator is required before submitting completion.',
        'completion_submitted' => 'Completion request submitted.',
        'cancel_not_allowed' => 'A Completed/Cancelled project cannot be cancelled.',
        'cancelled' => 'Project cancelled.',
        'no_pending_change' => 'There is no pending change to submit.',
        'changes_submitted' => 'Submitted for approval: :items.',
        'saved_with_draft' => 'Saved. :count proposal field(s) are held as a draft change and take effect only after approval - press Update Project to submit.',
        'leadership_unchanged' => 'No change to the Sponsor or Leader.',
        'leadership_updated' => 'Sponsor/Leader updated.',
        'leadership_submitted' => 'Sponsor/Leader change submitted for approval by :name.',
        'leadership_pending' => 'Sponsor/Leader change submitted. It stays pending until an approver is determined.',
        'activity_added' => 'Activity added.',
        'activity_updated' => 'Activity updated.',
        'activity_removed' => 'Activity removed.',
        'actual_updated' => 'Actual implementation updated.',
        'attachment_removed' => 'Attachment removed.',
        'attachment_deleted' => 'Attachment deleted.',
        'attachments_uploaded' => ':count attachment(s) uploaded.',
        'indicator_added' => 'Indicator added.',
        'indicator_updated' => 'Indicator updated.',
        'indicator_removed' => 'Indicator removed.',
        'achievement_updated' => 'Achievement updated.',
        'indicator_achievement_updated' => 'Indicator achievement updated.',
        'budget_added' => 'Budget added.',
        'budget_updated' => 'Budget updated.',
        'budget_removed' => 'Budget item removed.',
        'actual_budget_updated' => 'Actual budget updated.',
        'members_added' => ':count team member(s) added.',
        'member_updated' => 'Team member updated.',
        'member_removed' => 'Team member removed.',
    ],

    'updates' => [
        'auto_approved' => 'Update request auto-approved. Please edit and then Apply.',
        'submitted_to' => 'Update request submitted to :approver.',
        'approver_committee' => 'Committee',
        'approver_sponsor' => 'Sponsor',
        'approved_applied' => ':label approved and applied to the project.',
        'approved_forwarded' => ':label approved; forwarded to layer :layer.',
        'legacy_approved' => 'Update request approved. The Project Leader can edit and then Apply.',
        'revision' => ':label returned to the Project Leader for revision.',
        'rejected' => ':label rejected.',
        'legacy_rejected' => 'Update request rejected.',
        'applied' => 'Changes applied (update closed).',
        'auto_approved_note' => 'Auto-approved (submitted by the Sponsor).',
    ],

    'committee' => [
        'saved' => 'Committee assignment saved.',
        'deleted' => 'Committee assignment deleted.',
    ],

    'guidelines' => [
        'uploaded' => 'Guideline ":title" uploaded.',
        'access_updated' => 'Access for ":title" updated.',
        'activated' => 'Guideline activated.',
        'deactivated' => 'Guideline deactivated.',
        'deleted' => 'Guideline ":title" deleted.',
    ],

    'categories' => [
        'created' => 'Category :code created.',
        'updated' => 'Category :code updated.',
        'activated' => 'Category activated.',
        'deactivated' => 'Category deactivated.',
        'in_use' => 'Category :code is still used by projects - it cannot be deleted. Archive it instead.',
        'deleted' => 'Category :code deleted.',
    ],

    'roles' => [
        'created' => 'Role ":name" created successfully.',
        'updated' => 'Role ":name" updated successfully.',
        'deleted' => 'Role ":name" deleted.',
        'users_updated' => 'Users for role ":name" updated.',
    ],

    'sla' => [
        'added' => 'SLA added.',
        'updated' => 'SLA updated.',
        'restored' => 'SLA restored.',
        'archived' => 'SLA archived.',
        'deleted' => 'SLA ":label" deleted.',
    ],

    'users' => [
        'created' => 'User ":name" created.',
        'updated' => 'User ":name" updated.',
        'deleted' => 'User ":name" deleted.',
        'cannot_delete_self' => 'You cannot delete your own account.',
    ],
];
