<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Email Notifications — sub-menu kedua di bawah menu "SLA".
 *
 * Satu baris = satu event. Tab "Events" mengatur KAPAN & KE SIAPA (recipients +
 * aktif/nonaktif), tab "Templates" mengatur ISI email (subject + body). Keduanya
 * kolom pada tabel yang sama supaya event dan templatenya tidak bisa terpisah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('event_key')->unique();      // penanda event di kode, mis. 'proposal.approved'
            $table->string('category');                 // pengelompokan di UI
            $table->string('name');
            $table->string('description')->nullable();
            $table->json('recipients');                 // daftar kunci penerima (lihat model)
            $table->string('subject');
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $baris = [];
        foreach (self::BAWAAN as [$key, $kategori, $nama, $desc, $penerima, $subjek, $isi]) {
            $baris[] = [
                'event_key' => $key, 'category' => $kategori, 'name' => $nama, 'description' => $desc,
                'recipients' => json_encode($penerima), 'subject' => $subjek, 'body' => $isi,
                'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        DB::table('email_notifications')->insert($baris);
    }

    public function down(): void
    {
        Schema::dropIfExists('email_notifications');
    }

    /** [event_key, category, name, description, recipients, subject, body] */
    private const BAWAAN = [
        ['idea.submitted', 'Idea', 'Idea Submitted', 'An idea has been submitted and needs review.',
            ['committee'], '[TMS] New idea awaiting your review: {record_id}',
            "Hi {recipient_name},\n\n{actor_name} submitted a new idea: {record_name} ({record_id}).\n\nPlease review it before {due_date}.\n\n{link}"],
        ['idea.approved', 'Idea', 'Idea Approved', 'An idea has been approved at the final layer.',
            ['submitter'], '[TMS] Your idea has been approved: {record_id}',
            "Hi {recipient_name},\n\nYour idea {record_name} ({record_id}) has been approved by {actor_name}.\n\n{link}"],
        ['idea.rejected', 'Idea', 'Idea Rejected', 'An idea has been rejected.',
            ['submitter'], '[TMS] Your idea has been rejected: {record_id}',
            "Hi {recipient_name},\n\nYour idea {record_name} ({record_id}) has been rejected by {actor_name}.\n\n{link}"],

        ['proposal.submitted', 'Project Proposal', 'Proposal Submitted', 'A proposal has been submitted to the Project Sponsor.',
            ['project_sponsor'], '[TMS] Proposal awaiting your approval: {record_id}',
            "Hi {recipient_name},\n\n{actor_name} submitted the proposal {record_name} ({record_id}) for your approval.\n\n{link}"],
        ['proposal.pending_approval', 'Project Proposal', 'Approval Pending', 'A proposal has reached an approval layer.',
            ['current_approver'], '[TMS] Waiting for your approval: {record_id}',
            "Hi {recipient_name},\n\nThe proposal {record_name} ({record_id}) is waiting for your decision.\n\nPlease respond before {due_date}.\n\n{link}"],
        ['proposal.revision_required', 'Project Proposal', 'Revision Required', 'A proposal has been returned to the Project Leader.',
            ['project_leader'], '[TMS] Revision required: {record_id}',
            "Hi {recipient_name},\n\n{actor_name} requires a revision on {record_name} ({record_id}).\n\nPlease update the proposal and submit it again.\n\n{link}"],
        ['proposal.approved', 'Project Proposal', 'Proposal Approved', 'A proposal has passed the final approval layer.',
            ['project_leader', 'project_sponsor'], '[TMS] Proposal approved: {record_id}',
            "Hi {recipient_name},\n\nThe proposal {record_name} ({record_id}) has been approved.\n\n{link}"],
        ['proposal.rejected', 'Project Proposal', 'Proposal Rejected', 'A proposal has been rejected by an approval layer.',
            ['project_leader'], '[TMS] Proposal rejected: {record_id}',
            "Hi {recipient_name},\n\nThe proposal {record_name} ({record_id}) has been rejected by {actor_name}.\n\n{link}"],

        ['project.completion_submitted', 'Project', 'Completion Submitted', 'A completion request has been submitted.',
            ['current_approver'], '[TMS] Completion request awaiting review: {record_id}',
            "Hi {recipient_name},\n\n{actor_name} submitted a completion request for {record_name} ({record_id}).\n\n{link}"],
        ['project.completed', 'Project', 'Project Completed', 'A project has been marked completed.',
            ['project_leader', 'project_sponsor'], '[TMS] Project completed: {record_id}',
            "Hi {recipient_name},\n\nThe project {record_name} ({record_id}) is now marked as completed.\n\n{link}"],
        ['project.cancelled', 'Project', 'Project Cancelled', 'A project has been cancelled.',
            ['project_leader', 'project_sponsor'], '[TMS] Project cancelled: {record_id}',
            "Hi {recipient_name},\n\nThe project {record_name} ({record_id}) has been cancelled by {actor_name}.\n\n{link}"],

        ['sla.reminder', 'SLA', 'SLA Reminder', 'The review limit set in SLA Settings is approaching.',
            ['current_approver'], '[TMS] Reminder: {record_id} is due on {due_date}',
            "Hi {recipient_name},\n\n{record_name} ({record_id}) is still waiting for your decision and is due on {due_date}.\n\n{link}"],
        ['sla.breached', 'SLA', 'SLA Breached', 'The review limit set in SLA Settings has been exceeded.',
            ['current_approver', 'admin'], '[TMS] SLA breached: {record_id}',
            "Hi {recipient_name},\n\n{record_name} ({record_id}) has passed its review limit ({due_date}) and is still pending.\n\n{link}"],
    ];
};
