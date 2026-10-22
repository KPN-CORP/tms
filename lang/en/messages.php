<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Messages
    |--------------------------------------------------------------------------
    |
    | Text the server produces outside the flash/validation channels: HTTP abort
    | reasons, and the few labels a controller computes rather than Vue.
    |
    */

    'abort' => [
        'idea_not_approved' => 'Idea is not approved yet.',
        'only_draft_editable' => 'Only draft ideas can be edited.',
        'committee_only' => 'This page is for committee members only.',
        'idea_layer_committee_only' => 'Only the committee of the active layer may decide on this idea.',
        'project_layer_committee_only' => 'Only the committee of the active layer may decide on this project.',
        'layer_unassigned' => 'The active layer has no assigned committee.',
        'only_last_layer_creates_project' => 'Only the last committee layer may create a project.',
        'no_shell_access' => 'You do not have access to this project shell.',
        'revision_only_committee_review' => 'Revision Required only applies to a proposal that is under committee review.',
        'only_assigned_approver' => 'Only the assigned approver may decide on this change request.',
        'no_approver_yet' => 'This request has no approver yet; it stays pending until one is determined.',
        'only_leader_submits_changes' => 'Only the Project Leader of a running project may submit changes.',
        'only_leader_applies_update' => 'Only the Project Leader may apply an approved update.',
        'only_team_requests_update' => 'Only members of an ongoing project team may request an update.',
        'only_team_uploads' => 'Only project team members may upload attachments.',
        'only_leader_or_team_edits' => 'Only the Project Leader (during draft/revision) or a team member while the project is running may edit this.',
        'only_leader_or_admin_adds_members' => 'Only the Project Leader or an administrator may add team members.',
        'only_leader_or_admin_edits_members' => 'Only the Project Leader or an administrator may change team members.',
        'only_leader_or_admin_removes_members' => 'Only the Project Leader or an administrator may remove team members.',
        'cancel_not_allowed' => 'Only the Project Leader, Sponsor, last idea committee layer, or a Super Admin may cancel.',
        'leadership_not_allowed' => 'You are not allowed to change the Project Sponsor or Leader.',
        'file_not_found' => 'File not found.',
        'no_attachment' => 'No attachment.',
    ],

    /*
    | Change-request types, for the flash messages the server writes. The Vue
    | side keeps its own copy (locales/*.ts, `changeTypes`) because those labels
    | stay on screen and must follow the switcher without a reload.
    */
    'labels' => [
        'all_units_bu_wide' => 'All Units (BU-wide)',
        'all_units' => 'All Units',
    ],

    'change_types' => [
        'team_change' => 'Team Change',
        'plan_indicator_change' => 'Plan & Indicator Change',
        'budget_change' => 'Budget Change',
        'leadership_change' => 'Project Sponsor & Leader Change',
    ],

    'budget' => [
        'empty_editable' => 'No budget yet.',
        'empty_final' => 'No budget submitted.',
    ],

    'validation' => [
        'concurrent_edit_by' => 'This record was changed by :name at :time. Reload the page and reapply your change so their update is not overwritten.',
        'concurrent_edit' => 'This record was changed by someone else while you were editing. Reload the page and reapply your change.',
        'team_smaller_than_roles' => 'Max Person in Team (:max) cannot be less than the total Require Role (:total).',
        'dev_login_invalid_key' => 'Invalid development login key.',
        'user_not_found' => 'User not found.',
        'import_mimes' => 'The file must be .xlsx or .csv. Old .xls files are not supported - save them as .xlsx first.',
        'import_max' => 'The file must not be larger than 2 MB.',
        'member_not_found' => 'The selected name was not found in the employee directory.',
    ],
];
