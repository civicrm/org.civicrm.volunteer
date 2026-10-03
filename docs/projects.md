# Volunteer Projects

CiviVolunteer uses "projects" to compartmentalize different kinds of volunteering. All volunteering information must be associated with a specific project.

To see all the active projects, go to **Volunteers > Manage Volunteer Projects**. If you're just starting out, you probably won't see any projects here, so below we'll create one.

## Characteristics of a project {:#characteristics}

In addition to simple settings such as "Title" and "Description", each project has the following characteristics:

* **Multiple [volunteering opportunities](./opportunities.md):** Within each project, you can define many different opportunities and assign contacts to those opportunities. In turn, each opportunity can be filled by multiple [assignments](./assignments.md) to separate volunteers.

* **Ongoing, or event-based:** Projects, by themselves, do not have dates &mdash; so a simple project may be considered "ongoing". If you wish to specify a time frame for a project, you can [associate it with an event](#events).

* **Active, or not:** Projects can be marked as active or inactive. Only *active* projects will be displayed to potential volunteers. You may wish to keep a project inactive while it is still in the planning stages, or after it is completed. Inactive projects are described in the management screens as "Archived", but no data is moved or hidden from staff — "Archived" is just presentation language for an inactive project.

* **Location:** Specifies the physical location (as an address) where the volunteering will take place. The location is useful if potential volunteers want to know which projects are in their vicinity.

* **Campaign:** Projects can be associated with [campaigns](https://docs.civicrm.org/user/en/stable/campaign/what-is-civicampaign/). The campaign is copied onto every volunteer assignment activity created for the project, so volunteer hours can be reported per campaign.

    !!! tip
        You can restrict the campaigns available for association with volunteering projects (by campaign type) by choosing **Volunteers > Configure Volunteer Settings** and looking in the **Global Settings** section. The same section holds **Use the backend theme on public volunteer pages**, on by default, which renders the public opportunity browser and signup form with CiviCRM's backend theme so they match the management screens; turn it off to let your frontend theme apply to them.

* **Multiple [relationships](#relationships) to contacts:** In order to control editing access and email notifications for each project, we must add relationships from the project to specific CiviCRM contacts.

* **Multiple [registration profiles](#profiles):** Specify which questions to ask volunteers when they [sign up](./sign-up-form.md).


## The project workflow

Editing a project is a five-step workflow, with one screen per step:

| Step | What it's for | Saving |
| --- | --- | --- |
| **Details** | Title, description, campaign, location, relationships, registration profiles | Explicit **Save** |
| **Shifts & roles** | Defining the [opportunities](./opportunities.md) | Automatic as you edit |
| **Assign volunteers** | Placing volunteers into shifts | Automatic as you edit |
| **Roster** | Read-only view of everyone assigned | n/a |
| **Hours** | Recording what actually happened | Explicit save |

The header is shared by all five steps: it shows the project's status, its beneficiaries, how many of its spots are filled, a preview of the public page, and the save/autosave state. Changing the status select in the header activates or archives the project immediately.

For a new project, the four downstream tabs are disabled until the project exists. **Save and set up shifts** on the Details screen creates the project and moves you straight on to Shifts & roles; a plain **Save** keeps you on Details. Creating a project requires a title, at least one project relationship, and at least one registration profile — the form refuses to save otherwise and tells you which requirement is missing.

On Details and Hours, CiviVolunteer warns you before leaving the screen if you have unsaved changes.

## Creating a new project {:#new}

### New stand-alone project {:#stand-alone}

A "stand-alone" project is not associated with an event, and thus can be ongoing. Create one as follows:

1. Choose **Volunteers > New Volunteer Project** (or **New Project** from the project management screen).
2. Fill out the Details screen and click **Save and set up shifts**.

Next, define your [volunteer opportunities](./opportunities.md) on the Shifts & roles screen, and continue through the remaining steps.

### New event-based project {:#events}

To associate a project with an event, the project must be created from within the event's configuration.

1. First create the event.
2. Configure the event, and choose the **Volunteers** tab.
3. The project editor opens at the Details step, with the project title prefilled from the event. Adjust project settings as necessary.
4. Click **Save** (or **Save and set up shifts**) to create the project and associate it with the event.

From the event's Volunteers tab you can then walk through the complete workflow — Shifts & roles, Assign volunteers, Roster and Hours — without leaving the event. You can also return to the project at any time via **Volunteers > Manage Volunteer Projects**; the event a project belongs to is shown under the project's title in the list.

!!! note
    When an event has an associated volunteer project, the event's info page will show a "Volunteer Now" button which takes users to a [sign-up form](./sign-up-form.md) showing all the available [volunteer opportunities](./opportunities.md) defined for this project.

!!! note
    Copying an event copies its volunteer project — the shifts, roles and profile configuration — but *not* the volunteer assignments: a copy of an event starts with an empty roster. Deleting an event does not delete its project; the project is unlinked from the event and becomes a stand-alone project, so the record of who volunteered is preserved.

## Managing existing projects

**Volunteers > Manage Volunteer Projects** opens a redesigned management screen with two views, switched by the **List / Dashboard** toggle (the choice is remembered in the URL as `?view=list|dashboard`):

* **List** — a searchable, filterable table of projects: search by name, filter by campaign and beneficiary, and switch between Active, Archived and All. Each row shows the beneficiary, the next shift, aggregate staffing, upcoming roles, a "Needs volunteers" badge when an active project has open future capacity, and a link back to the associated event. Row actions are **Edit** plus a `…` menu with **Shifts & roles**, **Assign volunteers**, **View roster**, **Log hours** and **Public signup**. Bulk actions (enable, disable, delete) apply to selected projects.
* **Dashboard** — four summary metric cards, an attention queue of the three items most needing action (shifts to staff, shifts awaiting hours), "Up Next" projects by next shift, and "This Week"'s shifts.

Deleting a project is refused while any volunteer assignment exists in any status, so completed volunteer history cannot be orphaned.

## Project relationships {:#relationships}

A common scenario is for an organization to have multiple volunteering projects, with separate staff members responsible for each project in various capacities. To accomodate these needs, CiviVolunteer relates each project to different contacts with *project relationships*.

!!! tip
    The default relationships used for new projects can be be configured within the [project defaults](#defaults).

Out of the box, CiviVolunteer provides the following project relationship types and functionality:

### Owner

Contacts listed as "Owner" of a project will have control over editing and deleting the project.

!!! caution "Permissions required"
    The following permissions make use of this *owner* relationship and must be set properly to take advantage of this access restriction functionality.

    * CiviVolunteer: edit own volunteer projects
    * CiviVolunteer: delete own volunteer projects

### Manager

Contacts listed as "Manager" will be BCC'd on all confirmation emails sent by CiviCRM to volunteers who fill out the [sign up form](./sign-up-form.md).

### Beneficiary

When activities are created for volunteering assignments, all contacts listed as "Beneficiary" of the volunteering project will be attached to these activities in the "With Contact" field.

The beneficiary relationship can also be used to report the total number of hours volunteered for specific beneficiaries.

### Other project relationships

Other types of project relationships can be added for any specific [reporting](./reporting.md) needs of your organization. To add a new type of project relationship choose **Volunteers > Configure Project Relationships**.


## Profiles for volunteer registration {:#profiles}

When people sign up to volunter, CiviVolunteer uses profiles to control the questions in the [sign-up form](./sign-up-form.md).

!!! tip
    Read more about [CiviCRM profiles](https://docs.civicrm.org/user/en/stable/organising-your-data/profiles/) (in the User Guide) to learn how to edit the fields within a profile and add new profiles.

These profiles are set per-project, and multiple profiles can be used in sequence.

Edit the project's Details screen to select the profile(s) you would like to use. The profile picker shows a plain select of the available profiles together with a read-only summary of each profile's fields. **Create profile**, **Edit fields** and **Preview** controls (shown to users who may administer CiviCRM profiles) open CiviCRM's own profile administration screens in a new tab, so unsaved project changes are never lost; **Refresh profiles** re-reads the list, and the list also refreshes automatically when the project window regains focus. If a project references a profile that has since been deleted, the picker says so instead of silently dropping the assignment.

To edit the fields within the profiles go to **Administer > Customize Data and Screens**. Also read about [custom data](./custom-data.md) if you want to add fields within these profiles that do not correspond to any fields already in CiviCRM.

### Group registration

When volunteers sign up (i.e. register), you can also offer them the option of registering *other* people, too. We call this "group registration", and when it's enabled, the registration form asks the volunteer whether they are bringing other people, and then asks for the "Number of Additional Volunteers". Subsequently, CiviVolunteer will ask questions about each of the additional volunteers and these questions that CiviVolunteer presents to the *additional* volunteers can even be *different* from questions presented at first to the person signing everyone up.

To enable group registration, select at least one profile to be used for "Group Registration" or "Both".

Now let's say you want to ask different questions of the additional volunteers. *(Perhaps you want to collect a phone number for the "primary" volunteer who is signing everyone up, but don't feel this is necessary to collect for all the other volunteers signed up by this person)*. Then choose different profiles to be used for "Individual Registration" vs "Group Registration". The fields in the "Individual Registration" profile will be presented first. Then the fields in the "Group Registration" profile will be presented for each additional volunteer.


## Changing the default project settings {:#defaults}

To change the default settings when creating a new project, choose **Volunteers > Configure Volunteer Settings**.
