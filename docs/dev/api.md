# API functions

CiviVolunteer's API surface is API4-first. Every runtime call the extension makes goes through API4, and the APIv3 actions of earlier releases remain available as deprecated compatibility adapters over the same implementations.

## Entities

| Entity | Kind | Purpose |
| --- | --- | --- |
| `VolunteerProject` | DAO entity | Volunteer projects, with aggregate actions for the UI |
| `VolunteerNeed` | DAO entity | Opportunities ("needs"/shifts) within a project |
| `VolunteerProjectContact` | DAO entity | Project relationships (owner, manager, beneficiary, ...) |
| `VolunteerAssignment` | Basic entity | Volunteer activities, via the assignment service |
| `VolunteerCommendation` | Basic entity | Commendation activities, via the commendation service |
| `VolunteerHoursReport` | SQL view (read-only) | Reporting rows for the Volunteer Hours report |
| `VolunteerUtil` | utility | UI-support reads (permissions, profiles, supporting data, countries, custom fields) |

## Notable actions

### VolunteerProject

* `get` / `create` / `update` / `delete` — field-level writes through the guarded BAOs. `delete` delegates to `CRM_Volunteer_BAO_Project::deleteProject()` and is refused while assignments exist.
* `commit` — the aggregate write used by the project editor: contacts, profiles and nested location in one call.
* `search` — aggregate read supporting the filters a plain DAO get cannot express (`project_contacts`, `proximity`, `beneficiary`). Public reads return an allow-list of public fields; `context: 'edit'` returns full rows to authorized editors.
* `getManageOverview` — the list/dashboard bundle: summary metrics, enriched project rows (beneficiaries, campaign label, upcoming roles, next shift, staffing), attention queue, up-next and this-week records.
* `getWorkflowContext(projectId)` — the per-project bundle the editing workflow reads on every step: the project (edit context), its needs and assignments, the capacity summary, the workflow and project supporting data, and beneficiary display names. Each part is produced by the same guarded read as the corresponding standalone action.
* `getLocationOptions`, `getLocation`, `saveLocation` — location block reads and writes. Writes go to a private location block so shared locations are never modified.
* `removeProfile` — removes a project's profile join, verifying it belongs to the project.

### VolunteerNeed

* `get` / `create` / `update` / `delete` — the last of which reparents a dated shift's assignments to the project's flexible need.
* `search` — the public opportunity search, requiring *register to volunteer*. Accepts `project`, `date`, `role`, `beneficiary`, `proximity`, `campaignId`, and `timeFilter` (`all`, `weekends`, `evenings`, `no_fixed_time`), applied in site-local time.

### VolunteerAssignment

* `get` / `create` / `update` / `delete` — normal assignment CRUD; `get` returns only Scheduled and Available activities, and assignment columns are prefixed by activity role (`assignee_display_name`, `assignee_email`, ...).
* `getRoster(projectId, includePast)` — display-ready roster rows and totals; checks roster (project view) authority; excludes flexible-need rows.
* `getHourEntries(projectId, volunteerNeedId)` — editable rows, selectable statuses and shift metadata for the Hours screen; checks project update access.
* `logHours(projectId, volunteerNeedId, entries)` — validates and saves a batch transactionally, returning refreshed rows. Hours are stored in minutes; statuses other than Scheduled/Available may exceed capacity (they record what already happened).
* `getCapacity(projectId)` — filled/total capacity and a per-need breakdown, counted across **every** non-deleted activity status (unlike `get`, so fully-attended projects don't read as empty).

### VolunteerCommendation

* `get` / `create` / `update` / `delete`; the project and volunteer of a commendation are immutable after creation.

### VolunteerHoursReport

Read-only rows over a database view: one row per non-deleted volunteer activity on a scheduled (non-flexible) need, with volunteer, project, role, status, scheduled and completed time. Backs the SearchKit **Volunteer Hours** report; requires *edit all volunteer projects*.

### VolunteerUtil

`getPermissions`, `getProfiles`, `getSupportingData`, `getCountries`, `getCustomFields` — the reads the Angular UI uses. `getSupportingData` and `getCountries` are public and check contextually.

## Permissions and safety rules

* Reads are row-scoped: checked `get`s see global managers' or owned/managed projects' rows; public reads see active public opportunities and public fields only.
* Writes require the corresponding global project permissions (create/edit/delete own/all); project-contact writes additionally require *edit volunteer project relationships* (see [permissions](../installation.md#permissions)).
* `save` and `replace` are denied on all volunteer entities; they would bypass the aggregate services' capacity and authorization checks. The generic `setvalue` action is refused for the same reason.
* `checkPermissions` is stripped from browser requests, so no JavaScript call can disable a check.

## APIv3 compatibility

The `api/v3/*.php` actions remain, unchanged in name and result envelope, but they are now thin adapters over the API4 actions above. New code should use API4. `VolunteerUtil.getbeneficiaries` has no API4 counterpart by design — read `VolunteerProjectContact` (relationship `volunteer_beneficiary`) and resolve names through `Contact` instead.

## Calling from JavaScript

Angular code uses `crmApi4(entity, action, params, index)`, which resolves to the rows themselves (pass `index: 'id'` — a string — when a map is needed). See the extension's own `ang/volunteer/*.js` files for examples.
