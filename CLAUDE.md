# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

TMS ("kpn-pm") — an internal KPN Corporation Idea → Project management system. Laravel 10 + Inertia 1 + Vue 3 (TypeScript) + Tailwind 3; every reachable page is Vue (see **Frontend** below). Employees submit **Ideas**; approved ideas become **Project Shells**, which grow a proposal (indicators, implementation plan, budget, team), get approved through the Sponsor plus a multi-layer committee, then run through implementation and completion. Changes made while a project is running are staged as change requests rather than applied directly.

Source comments and command output are written in Indonesian; user-facing UI strings are English. Match that when editing existing files. Newer services mix Indonesian identifiers into PHP code too (`aktif()`, `$atasNama`, `catat()`, `ringkasPerubahan()`) — follow the surrounding file rather than normalising.

## Commands

```bash
composer dev          # serve + queue:listen + vite, all at once (preferred)
```

```bash
php artisan serve
npm run dev           # vite dev server
npm run build         # production assets into public/build
npm run type-check    # vue-tsc over resources/js — run before committing frontend work
```

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder   # RBAC source of truth
php artisan optimize:clear                              # after config/route/view changes
```

Tests (PHPUnit 10, only the stock Breeze suite exists — no tests cover any TMS feature):

```bash
php artisan test
php artisan test --filter=AuthenticationTest
php artisan test tests/Feature/ProfileTest.php
```

`phpunit.xml` now pins the default connection to sqlite `:memory:`, and `tests/TestCase.php` refuses to run if it is anything else. Both are load-bearing: the Breeze tests use `RefreshDatabase`, which runs `migrate:fresh` on the default connection -- pointed at MySQL it drops every tm_system table and everything in them. That has happened; do not "fix" a failing suite by pointing it back at MySQL.

The suite currently stops immediately with a message about `pdo_sqlite`: the extension ships with this PHP build but is commented out in `php.ini`. Uncomment `extension=pdo_sqlite` (and `extension=sqlite3`) to make the tests runnable. Until then they fail safely rather than running against a real database.

Data reset (staging only — permanent):

```bash
php artisan tms:clear-config --force          # categories, committee assignments, SLA, guidelines
php artisan tms:clear-ideas-projects --force  # all ideas/projects + child tables
php artisan tms:reset-staging --force         # both of the above
```

All three also accept `--files` (delete the physical attachments too); `tms:clear-ideas-projects` additionally accepts `--logs` to wipe `activity_logs`.

Deployment is cPanel git-deploy via `.cpanel.yml`; the target (production `tms` vs staging `tms-stage`) is chosen from the server-side repo folder name, so the file is identical on `master` and `development` — never branch-edit it. It rsyncs code, runs `composer install` only when `composer.lock` changed, then `optimize:clear` + `config/route/view:cache`. It does **not** run `npm run build` or `php artisan migrate` — build assets locally and commit `public/build`, and run migrations on the server yourself.

## Two databases — the single most important constraint

- `mysql` (**tm_system**, `hcispanel_kpn_tms` locally) — the application's own data: ideas, projects, committee assignments, roles/permissions, activity logs, SLA. All migrations run here.

  The migration history was squashed: `database/migrations/` holds 12 consolidated `2026_01_01_0000NN_*` files describing the final schema; the 62 historical files are archived (and unused — Laravel doesn't recurse) in `database/migrations_legacy/`, alongside `squash-reconcile.sql` for databases that still carry the old `migrations` rows. `migrate:fresh` now works from zero — keep it that way: add changes as new migrations, don't edit the consolidated ones.
- `kpncorp` (**hcis** / Darwinbox mirror, `hcispanel_hc_stage` locally) — **READ-ONLY**. Users, employees, and the org hierarchy live here.

`App\Models\User` sets `$connection = 'kpncorp'` and hard-blocks writes: a `saving` hook returns `false`, and `delete()` is overridden to return `false` *without* firing the `deleting` event (Spatie's `HasRoles` listener would otherwise detach every role from tm_system). Never "fix" this by re-enabling writes; the User Management routes in `routes/web.php` and the sidebar entry are commented out for exactly this reason, even though `UserController` and `resources/views/admin/user/*` still exist.

Because `User` lives on `kpncorp`, any model reached through it via `morphToMany`/`belongsToMany` inherits that connection unless it declares otherwise. `Role`, `Permission`, `EmailNotification`, `EmailNotificationSchedule` therefore pin `protected $connection = 'mysql'` explicitly. Add that pin to any new model on a pivot path from `User`, and use `DB::connection('mysql')->table(...)` for cross-connection pivots (see `RoleScopeService::roleEmployeeIds`).

`App\Models\Kpn*` (KpnUser, KpnEmployee, KpnBusinessUnit, KpnCompany, KpnLocation, KpnDepartment) are the read-only hcis-side models. `App\Models\BusinessUnit|Company|Location|Department` are the *local* tm_system mirrors. `App\Services\OrgResolver` bridges them by find-or-create **on name**, so Ideas and Committee Assignments resolve to identical FK values and routing matches. `OrgController` serves the hcis-backed cascade dropdowns (`/org/business-units`, `/org/departments`, `/org/employees`, …) as JSON; `businessUnitsLocal` is the variant that returns local FK ids.

## Frontend — Inertia + Vue 3 + TypeScript

Every page a user can reach is Vue. Controllers return `Inertia::render(...)`; nothing else about them changed. Routes, middleware, Spatie permissions, `RoleScopeService` and `ReportOverride` were untouched by the move — authorization stays entirely server-side.

- `resources/views/app.blade.php` is the Inertia root template.
- `resources/js/app.ts` boots the app. Pages are resolved with a lazy `import.meta.glob`, so **every page is its own chunk**; `vendor` (Vue + Inertia) is split out in `vite.config.ts` so its cache survives deploys.
- `AppLayout.vue` is a **persistent layout** — sidebar and topbar are not re-mounted between visits. Guest pages opt out with `defineOptions({ layout: null })` and wrap themselves in `AuthLayout`. `app.ts` tests the layout against `undefined`, not `??=`, precisely so `null` means "no shell".
- Path alias `@` → `resources/js`.

### Where things live

| Path | What |
| --- | --- |
| `resources/js/pages/` | One component per Inertia page; the name matches `Inertia::render('Ideas/Index')`. |
| `resources/js/layouts/` | App shell: `AppLayout`, `partials/AppSidebar`, `AppTopbar`, `NavEntry`. |
| `resources/js/components/ui/` | The UI kit. Import from the barrel (`@/components/ui`), never by file path. |
| `resources/js/components/list/` | List-page pieces: `ListToolbar`, `TabBar`, `RowActions`, `MetricNotice`. |
| `resources/js/composables/` | `useListQuery`, `useClientTable`, `useConfirm`, `useToast`, `useTheme`, `useRemoteOptions`. |
| `resources/js/lib/` | Pure helpers: `format`, `datetime`, `status`, `query`. |
| `resources/js/types/` | `models.ts` (payload contracts), `inertia.d.ts` (shared props). |
| `resources/js/locales/` | `en.ts` (reference shape), `id.ts`, `index.ts` (lazy loader). |
| `lang/{en,id}/` | Server-side strings: `validation`, `auth`, `passwords`, `flash`, `messages`. |

### Rules that matter

- **Colors are semantic tokens only.** `resources/css/app.css` defines `--color-*` for light and dark; `tailwind.config.js` maps them to `surface`, `ink`, `line`, `brand`, `success`, `warning`, `danger`, `info`, `neutral`, `avatar`. Components must not write `gray-200` / `red-700` -- that is what breaks both dark mode and any future palette change. Status -> tone mapping lives in `resources/js/lib/status.ts`; the status **labels** stay in PHP (`Project::STATUS_BADGES`, `Project::SHELL_STATUS_BADGES`, `Idea::STATUS_LABELS`) and are sent as `status_label`.
- **Shared props are closures** in `HandleInertiaRequests` (`auth.can`, `nav`, `flash`, `reportContext`), so a partial reload that asks only for the list prop does not recompute Task Box badges or the permission map. Keep new expensive props closures too.
- **The sidebar is data, not markup.** `App\Support\Navigation\SidebarBuilder` decides visibility, badges and active state; `NavEntry.vue` only renders. Add menu items there, keyed on a permission rather than a role name.
- **Every table sorts and paginates, by one of two routes.** A list the controller already paginates (My Ideas, Task Box, Manage Project, Project Shell, Review, Report, Activity Log) sorts on the SERVER: add the column to that controller's `sortable` whitelist, then give the `<UiTh>` a matching `sort-key`. A table whose rows arrive whole in the payload (the Admin Setting pages, the recipients preview, the four Project Detail sections) sorts in the CLIENT through `useClientTable`, which returns the page slice plus a `meta` that drops straight into `<UiListFooter>`. Never sort a server-paginated list in the client — it would reorder one page, not the list. Columns whose text comes from the hcis database (person names) cannot be sorted in SQL at all, so they stay plain `<UiTh>`.
- **Lists use `useListQuery`** — the client half of `Concerns\HasListQuery`. It issues partial reloads (`only: [...]`), debounces search, preserves scroll and replaces history. Controllers send rows through `->through(fn ($model) => [...])` so the paginator meta survives and only UI-facing columns are exposed.
- **Flash messages are global.** `HandleInertiaRequests` shares `success` / `error` / `status`; `UiToastHost` in the layout renders them. Do not add per-page flash blocks.
- **Confirmations are `await confirm({...})`** from `useConfirm`, not `data-confirm` attributes.
- **Controls wrapped in a `<div>` forward their attributes by hand.** `UiInput` and `UiSelect` render a wrapper (for icons and the chevron), so `inheritAttrs: false` is set and `id` / `autocomplete` / `name` are bound to the real `<input>`/`<select>`; only `class` stays on the wrapper. Without that, a `<label for>` points at a `div`.
- **A download, or any file served by a controller, is a plain `<a>`** -- never `<Link>`, because those responses are not Inertia responses. `AppLink`'s `external` prop covers that and any URL outside the SPA.

### Multilanguage (EN / ID)

English and Bahasa, **default English**, switched from the topbar -- the same arrangement as **kpn-tmp**, which this is modelled on.

- The choice is **client-side only**: `useLocale` keeps it in a shared `ref` and mirrors it to `localStorage` under `tms-locale`, exactly like `useTheme`. No column, no session, no request -- so switching is instant and nothing reloads.
- `resources/js/locales/en.ts` is the **reference shape**; `id.ts` is typed `LocaleMessages`, so a key added to one and forgotten in the other fails `npm run type-check` instead of rendering blank.
- English ships in the main bundle (it is the shape and the fallback); every other language is its own chunk, fetched on first use. `app.ts` awaits `prepareLocale()` before mounting so the first paint is already in the stored language.
- `UiLanguageSwitcher` is the trigger. It sits in `AppTopbar` (left of the theme toggle) and in `AuthLayout`, so guests can switch before signing in. One component, two places -- do not fork it.
- **Sidebar labels come from the server as a key + an English fallback.** `SidebarBuilder::item()` takes `$key` first; `NavEntry` / `AppSidebar` look it up under `nav` / `nav.groups` and fall back to the server's `label`. Add a menu item there *and* in both dictionaries -- without the dictionary entry the item still renders, in English.
- Placeholders are `{name}`; fill them with `tr(template, { name })` from `useLocale`.
- **Server-sent labels are translated from their KEY, not their text** -- `resources/js/lib/labels.ts` (`useLabels`). Status codes (`status` next to `status_label`), tab keys, Dashboard metric keys and Report column names all arrive paired with an English label, and the label is the fallback, so a new status or tab in PHP renders in English rather than blank. Two keys are not unique and need the label: tab `review` means "On Review" under Project Proposal but "Completion Review" under Project Completion, so `tab()` slugifies the label first; Report column names have no key at all, so `field()` slugifies `'Business Unit'` into `businessUnit`.
- The three project list pages carry a phase `key` (`proposal` / `implementation` / `completion`) from `ProjectController::projectList`, for the same reason the sidebar does.
- Defaults that depend on the language are stored as `null`, never as an English string -- `UiTable`'s empty text, `useConfirm`'s button labels, `UiSpinner`'s label, the combobox placeholders. The component patches them from the dictionary at render, so an open dialog re-renders when the language changes.

#### The split: who translates what

Two dictionaries, and the line between them is **how long the text stays on screen**.

- **Labels the server sends but Vue keeps showing** -- statuses, tabs, metric cards, approval types, change types, report columns and types, Activity Log subject types, Spatie permissions -- are translated **client-side** from a key, with the server's English label as the fallback (`lib/labels.ts`). They have to follow the switcher without a reload, and a partial reload would not refetch them.
- **Sentences the server produces for one request** -- flash messages, validation errors, HTTP abort reasons -- are translated **server-side** by Laravel, in `lang/{en,id}/`. They appear immediately after the request that made them, so they are already in the right language and never need to change in place.

`lang/en` and `lang/id` must hold the **same key set**: a key missing from `id` silently falls back to the English string rather than erroring, which is the failure that is hardest to spot. `validation.php` carries an `attributes` map so messages name the field as the screen does ("Kolom Nama Idea wajib diisi", not `idea_name`).

#### How the server learns the locale

`useLocale` writes the choice to a `tms-locale` cookie as well as to `localStorage`, and `App\Http\Middleware\SetLocale` reads it (registered in the `web` group *before* `HandleInertiaRequests`, so shared props are already translated). The cookie is listed in `EncryptCookies::$except` because JavaScript writes it; `SetLocale` accepts only `en` / `id` and ignores anything else, so nothing can be smuggled in through it. It is rewritten on every boot, so a cookie that expires while `localStorage` still remembers the choice heals itself on the next request.

- `ProjectChangeStagingService::TYPE_LABEL` stays English: those strings are also **written into** `project_updates.description` as an audit trail, and translating at write time would store the author's language. `::label()` is the translated variant, for text a user reads.
- Still English, deliberately: `Permission::displayLabel()` and `CommitteeAssignment::TYPES` (both are the fallback the client translates from), and the audit text already stored in the database.


### Design system

The look follows the sibling projects **kpn-tmp** and **kpn-coi**, which share one palette and shape language. Re-read theirs before inventing anything new here.

| | Value |
| --- | --- |
| Brand | `#ab2f2b`, hover `#8a2522` -- brick red, not Tailwind's `red-700` |
| Page / surface | `#f4f6f8` / `#ffffff`, muted `#f8fafc` |
| Text | heading `slate-800`, body `#555555`, label `slate-400` |
| Line | `#e2e8f0` |
| Status | success `#2f855a`, danger `#c53030`, warning `#b45309`, info `sky-600` |
| Font | Albert Sans (bunny.net), loaded in `resources/views/app.blade.php` |
| Shape | cards `rounded-xl` + 1px border + `shadow-card`; controls and buttons `rounded-md` |
| Shell | sidebar `--sidebar-width` 260px, topbar `--topbar-height` 64px |

Two details carry the look and are easy to lose:

- **The active sidebar item is a 4px left border**, not a rounded pill. Every item keeps a transparent `border-l-4` so the label does not shift when it becomes active.
- **The sidebar logo block and the topbar share `--topbar-height`**, so their bottom borders read as one continuous line. Sizing either by padding lets the two drift apart.

The reference projects draw icons with Font Awesome. TMS keeps inline SVG instead -- no icon font to download, and the markup already existed. That is the one deliberate divergence.

### The Project Detail page

`projects/show` is the densest page in the app and is split rather than written as one component: `pages/Projects/Show.vue` only assembles sections from `components/projects/sections/*` (Team, Implementation, Indicator, Budget, Attachments, History, BottomActions) plus `ChangeRequestPanel`, `LeadershipForm` and `CollapsibleSection`.

Two rules keep it honest:

- **Authorization is computed once, in `ProjectController::show()`.** That method still builds the same `$data` array it always did, including the view-only pass that zeroes every mutating flag; `showPayload()` only reshapes it for Vue. Do not recompute a permission in a component — read it from `abilities`.
- **Every row carries its own `version` and URLs.** The `version` is `RecordVersion::of($model)` and goes back as `_version` on edit, so `PreventsConcurrentEdits` still rejects a stale write. Dropping it silently disables that protection.

Rows that edit a running project do not change it directly — they accumulate as change requests (`ProjectChangeStagingService`). `pending` on a row and `<PendingBadge>` are what tell the user their input is saved but not yet in effect.

### Links and `InertiaRoutes`

`App\Support\Navigation\InertiaRoutes` lists every route whose page is Vue. The server sends an `external` flag next to each URL it emits and `AppLink.vue` picks `<a>` or `<Link>`. That list now covers every reachable page, so `external` is false everywhere; the class and the `external` props can be deleted whenever you want to simplify, as long as no Blade page comes back.

### Dead Blade

No controller renders a Blade page any more except `UserController`, whose routes are commented out (see the two-databases section) -- the views under `resources/views/admin/user/` are unreachable. `resources/js/app.js` (Alpine) and `resources/views/layouts/app.blade.php` are still built so those pages would work if re-enabled; re-enabling them properly means porting them to Vue first.

The Blade views for every ported page are kept as a rollback aid and should be deleted once the Vue pages are validated on staging.

## Authentication

Two entry points, both landing on the hcis `users` table:

- `/login` — `LoginRequest::attemptLogin()` tries `Auth::attempt` first, then falls back to `HcisAuthService::attempt()` (manual hash check for legacy hashing), then `ensureRole()` grants the default `Employee` role in tm_system. `HcisAuthService::mirror()` is a *lookup*, not an insert — it finds the user by email and only tops up the role.
- `/dbauth` — `SsoController` handles the Darwinbox SSO handoff (base64 + XOR payload, token verified against the Darwinbox `checkToken` endpoint).

Note `SsoController` currently contains hard-coded API keys and a Basic auth header. Leave them alone unless you're deliberately moving them to config; don't copy that pattern into new code.

## Authorization — four independent layers

1. **Permissions** (Spatie, dot-notation). Route middleware `permission:idea.create`, or `permission:guideline.view|guideline.upload` for either-of. `RolesAndPermissionsSeeder` is the source of truth for role→permission mapping. `AppServiceProvider` installs a `Gate::before` so **Super Admin passes every ability** — never add explicit Super Admin checks to gates.
2. **Committee membership** — `committee.member:idea` middleware (`EnsureCommitteeMember`). The model is "assignment = capability": being listed in `committee_assignments` is itself enough to open a review page; *which* item/layer you may act on is re-checked in the controller (`isCurrentReviewer`, `isReviewer`).
3. **Role scope** (`RoleScopeService`) — data-row filtering by Business Unit → Company → Location → Employee, restricted per role and **unioned across a user's roles**. An empty dimension on a role means "all" for that role, so one unrestricted role opens everything on that dimension. A user with no roles sees nothing. Applied via `Idea::scopeVisibleTo()` and friends; always apply scope *before* `HasListQuery` search/sort.
4. **Report context override** (`App\Support\ReportOverride`) — **read this before touching any authorization check.** Cross-organisation authority (`override.role`, i.e. Super Admin) is alive *only* when the request carries `from=report` **and** the user holds `override.role`. Everywhere else — My Ideas, My Project, Task Box, Project Shell, the sidebar, `EnsureCommitteeMember` — a Super Admin is treated as an ordinary employee who sees only what involves them. The `from=report` marker expresses *intent*, not permission: adding it to a URL by hand grants nothing without the permission. Links and forms on pages reached from Report must carry it forward via `ReportOverride::params()`, or the context is lost on the next request.

When an override user acts in place of the real approver, the decision is recorded **on behalf of** that person: `IdeaWorkflowService::approve(..., $atasNama)` and the `actingAs()` wrappers on `ProjectApprovalWorkflowService` / `ProjectChangeStagingService` write `on_behalf_of_id` on `idea_approvals` / `project_approvals`. Keep that trail — don't collapse it into a plain approval by the admin.

## Approval workflows

All committee waterfalls read `committee_assignments`, keyed by `approval_type`. `CommitteeAssignment::TYPES` is the configurable set: `idea`, `project_proposal`, `team_change`, `plan_indicator_change`, `budget_change`, `project_completion`. (`leadership_change` is a change type but deliberately **not** configurable — see below.)

- **`IdeaWorkflowService`** — routes on **Business Unit + Department**. A department-specific assignment beats a BU-wide one (`department_id IS NULL`); if none matches the idea's department, the BU-wide chain is used. Idea statuses: `draft → submitted → review (layer 1..n) → approved → project_created`, or `rejected`; reject at any layer stops routing.
- **`ProjectApprovalWorkflowService`** — same mechanic, generic over flow. `FLOWS` maps project *status* → committee type + final/reject status: `committee_review → project_proposal` (final `approved`, reject `rejected`, and `no_committee` falls back to `submitted` rather than auto-approving), `completion_review → project_completion` (final `completed`, reject back to `approved`). For budget-scoped types (`CommitteeAssignment::BUDGET_SCOPED_TYPES` = `project_proposal`, `budget_change`) routing also matches the project's total budget (Σ qty × unit_price) against `budget_min..budget_max`, picking the **narrowest** matching range; `BUDGET_MAX_UNBOUNDED` is the "≥ min" sentinel.
- **Layer 1 belongs to the Project Sponsor** on every type in `CommitteeAssignment::SPONSOR_LAYER_TYPES` (everything except `idea`), so committee config for those starts at Layer 2. The Sponsor's own decision isn't part of `FLOWS` — it runs through `ProjectController::sponsorApprove` / `sponsorRevision` at status `submitted` (`SPONSOR_PENDING_STATUS`), but still surfaces in the Task Box via `reviewQueueFor`.
- Both services auto-approve a layer when the submitter *is* that layer's committee member (capped by `MAX_AUTO_SKIP = 20`).

Project statuses: `draft`, `submitted`, `revision_required`, `committee_review`, `approved`, `rejected`, `ongoing`, `delayed`, `completion_review`, `completed`, `cancelled`. `Project::STATUS_BADGES` is the single label/colour map; `EDITABLE_STATUSES`, `EXECUTION_STATUSES` and `POST_PROPOSAL_STATUSES` define what the Leader may edit, what counts as "running", and when Actual fields become relevant. Because those Tailwind classes live in PHP, `tailwind.config.js` scans `./app/**/*.php` — don't remove that content path.

`ongoing` / `delayed` are **not** set by hand: `ProjectController::recomputeExecutionStatus()` derives them from the Implementation Plan rows (any `Delayed` activity → `delayed`; any started activity *or* any activity whose planned start has arrived → `ongoing`; otherwise back to `approved`). `ImplementationPlan::getStatusLabelAttribute()` is where per-activity Not Started / On Going / Delayed / Completed (On Time|Late) is decided.

Cancellation has no column of its own: the reason lives in `project_status_logs.remarks` prefixed with `"Cancelled: "`, read back through `Project::cancellation()` / `cancellationReason()`. `Project::proposalApproved()` walks the status log because a cancelled project can have been cancelled either side of approval.

### Change staging (projects in execution)

`ProjectChangeStagingService` — once a project is running, Leader edits do not apply immediately. They accumulate into a single `ProjectUpdate` draft per `(project, change_type)` (a `revision_required` request absorbs further edits too, so a revision stays one request), are submitted as a change request, and only take effect on `applyUpdate`. Sections map as:

| Section | `change_type` | Approver |
| --- | --- | --- |
| Team Members | `team_change` | Layer 1 Sponsor, then configured committee from Layer 2 |
| Implementation Plan & Indicator | `plan_indicator_change` | same |
| Budget | `budget_change` | same (budget-range scoped) |
| Project Sponsor & Leader | `leadership_change` | **the last committee layer of the originating idea** — never `committee_assignments`; `approver_role` is `idea_committee`, or `unassigned` (request hangs) when the idea has no committee |

`Project::leadershipRights()` is the single source of truth for who may change Sponsor/Leader and whether it applies instantly: the idea's last-layer committee member (instant), a Report-context override holder, the Project Sponsor (may change the Leader only, via approval), or the Leader. A role *on this project* is checked before any global permission, so an admin who happens to be the Sponsor is bound by Sponsor rules.

Actual/realisation data bypasses staging and uses the Leader-submits → Sponsor-approves baseline path.

`ProjectUpdateService` handles the **legacy** update-request types still in `ProjectUpdate::CHANGE_TYPES` (`budget`, `planning`, `team`, `general`) that predate staging; `legacyTypes()` is literally "all change types minus the staged ones". Both services contribute to the project Task Box count. New work belongs in `ProjectChangeStagingService`.

### Committee configuration & coverage

- `CommitteeAssignmentController` drives Admin Setting → Committee Assignment. A set is one `(approval_type + BU + Unit + budget range)` combination with rows per layer, capped at `MAX_LAYERS = 10`.
- `CommitteeLayerImport` + `App\Support\SimpleXlsx` parse an uploaded `.xlsx`/`.csv` template into layer→approver rows. The import **only fills the form** — nothing is saved until Save, so bad files can be corrected first. The `employee_id` column in the template is intentionally ignored (a row applies to a combination, not a person). `SimpleXlsx` is a hand-rolled reader/writer (no PhpSpreadsheet) that always returns strings so leading zeros in `employee_id` survive.
- `ApprovalCoverageReport` renders the "unassigned layer" warnings on that index page. It is **derived from live data on page load**, not stored notifications — so warnings vanish on their own once assignments exist, and pre-existing stuck items are detected too. Three categories: `blocked` (item waiting at an unowned layer), `gaps` (a hole in the chain, e.g. L1 and L3 but no L2 — dangerous because `approve()` only looks for layer+1), `risks` (not stuck yet but certain to break).

## Dashboard & Report

`App\Services\Dashboard\MetricScopeService::METRICS` is the **single definition** of every dashboard metric (5 idea + 6 project). `DashboardController` calls it for the card numbers and `ReportController` calls the same query for the row list when a card is clicked, so the two can never drift. Add a metric there, not in a controller.

The dashboard's "Act as" dropdown (Myself / Admin) is gated by the `dashboard.act-as` permission, not by a role name, so new roles can be granted it from Role Management. Without the permission the scope is locked to Myself. Business Unit and Unit filters match on the **name snapshot** stored on the idea, the same way My Ideas / My Project filter.

Report (`permission:report.view`) is the only cross-organisation view; it is also the page that turns on `ReportOverride`, including the pencil button that lets an `override.role` holder act on behalf of a committee member. `ReportController::download` streams CSV with the same filters and search.

## Shared mechanics to reuse

- `Concerns\HasListQuery` — uniform search + whitelisted sort + per-page (`10|25|50|100`) for every index page. Sort keys are a whitelist; dotted `searchable` entries become `whereHas`. `paginateListCollection()` exists for the cases where the final filter has to happen in PHP.
- `Concerns\PreventsConcurrentEdits` + `Support\RecordVersion` — `withRecordLock()` combines `lockForUpdate()` serialisation with an optimistic-lock token. The token is a sha1 of the record's attributes (not `updated_at`, which is only second-precision). Forms carry it via the `<x-record-version>` component in the `_version` field; a missing token is tolerated for backwards compatibility, a mismatched one is rejected with who-changed-what (read from `activity_logs`).
- `Concerns\HandlesFileAttachments` — validation (10 MB; `pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,zip,csv,txt`), store, inline `view`, `download`, delete. Authorization stays in the caller.
- `Models\Concerns\LogsActivity` — drop-in audit trail writing create/update/delete diffs to `activity_logs`. Override `activityLabel()` for a human-readable name, `$activityExclude` for extra ignored fields, and `activityUserFields()` to list user-id columns (single or JSON array) — their names are snapshotted into the log *at write time*, so the trail still reads correctly after someone leaves the team or disappears from hcis.
- `SlaService` — info-only On Time / Due Soon / Overdue from `sla_settings`; rendered by `<x-sla-badge>`. It sends nothing.
- `Services\Notification\EmailRecipientResolver` — resolves an `EmailNotificationSchedule` to hcis `employees` rows. See its docblock for the non-obvious column mapping (Business Unit → `group_company`, Location → `office_area` not `work_area_code`, Company → `company_name`). Filters AND across dimensions, OR within one, blank = unrestricted.

## Conventions & gotchas

- **Shell drafts are hidden by a global scope.** `Project::booted()` adds `excludeShellDrafts`, so *every* Project query (My Project, Implementation, Completion, review queues, dashboard, report) silently skips `is_shell_draft = true`. The only ways in are `scopeWithShellDrafts()` / `scopeOnlyShellDrafts()`. This is also why the draft-delete route takes `{shell}` as a plain string instead of a `{project}` model binding — route model binding would apply the scope and 404 the draft.
- Business IDs: ideas `I-{BU}-{YYYYMMDD}-{00000}`, projects `P-{BU}-{YYYYMMDD}-{CAT}-{00000}`. `BU_CODE_MAP` plus the auto-abbreviation fallback (`buCodeFromName`) is **duplicated** in `IdeaController` and `ProjectController` — change both or the two ID families drift apart.
- `Idea` uses `modified_at` as its `UPDATED_AT` column.
- Route ordering in `routes/web.php` matters: literal segments (`/projects/shell`, `/projects/shell-draft/{shell}`, `/projects/implementation`, `/projects/completion`) must stay above `/projects/{project}`.
- The sidebar (`resources/views/layouts/sidebar.blade.php`) builds menus from a `$groups` array of `[label, route, permission, show, badge, active, children]` items — nested one level for collapsible parents, where a parent may have `route => null`. Add new pages there, and prefer a permission over a hard-coded role name so new roles can be granted access from Role Management. The file runs real queries (committee membership, Task Box counts) on every page render; keep additions cheap and guarded the way the existing counts are.
- Two features are reachable by URL but hidden from the sidebar: **Activity Log** (`/admin/activity-logs`, route live, menu commented out — audit writing never stopped) and **User Management** (menu *and* routes commented out; see the two-databases section).
- Email Notifications is **configuration only** — schedules, recipients, subject/body placeholders are stored and resolvable, but nothing in the codebase sends mail and `Console\Kernel::schedule()` is empty. Don't assume a reminder went out.
- `app/Repositories/*.php` are all empty placeholder files; real logic lives in `app/Services`.
- Tailwind is **v3** via `postcss.config.js`. The stray v4 entries that used to sit in `package.json` `dependencies` are gone — don't reintroduce them or write v4 syntax.
- Status/badge classes written in PHP are why `tailwind.config.js` scans `./app/**/*.php`; it also scans `./resources/js/**/*.{vue,ts,js}`. Don't remove either content path.
- `ProjectController` (~2400 lines) and `resources/views/projects/show.blade.php` (~1400 lines) are the hot spots — read the surrounding section (the `/* ---- ... ---- */` banners) before editing.
