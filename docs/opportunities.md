# Volunteer Opportunities

After creating a [project](./projects.md), you must define the volunteering *opportunities* associated with the project. An opportunity is essentially a role and a time, but with a few more options and nuances.

To define opportunities, open the project (**Volunteers > Manage Volunteer Projects**, then **Edit**) and continue to the **Shifts & roles** step. The same screen is available as a dialog from the project list's `…` menu and from an event's Volunteers tab.

!!! summary "Technical note"
    "Volunteering opportunities" were formerly called "volunteering *needs*" and some of the old language still persists within the CiviVolunteer codebase and documentation. In the redesigned editor, each row on this screen is described as a *shift*.


## Characteristics of a volunteering opportunity

Each row on the Shifts & roles screen defines:

* **Role** - to specify what type of work is to be done, for example "Photographer", or "Clean up" *(more on [roles](#role) below)*
* **Number of volunteers needed** ("Spots") - when this number is reached with enough [assignments](./assignments.md), no further assignments will be possible
* **When** - to specify when the volunteering is to occur (with the option of having it be open-ended)
* **Visibility** - two checkboxes control availability:
    * **On the public page** - to control which opportunities are available for self-service [sign-up](./sign-up-form.md).
    * **Accepting sign-ups** - to temporarily stop public sign-ups (for example when a shift is being held for a particular group) without hiding it.

Changes on this screen save automatically as you edit; each row shows its own saving state, so a slow save on one row cannot overwrite a newer edit to another. Rows can be duplicated (the copy does not inherit the original's in-flight saves) or deleted. Filters above the table narrow the rows by date scope or custom range, schedule type, role, and sign-up status.

When you delete a shift that already has volunteers assigned, their assignments are not lost: they are moved to the project's flexible need, keeping their status, dates and logged time.

## Role

Each opportunity must have a role to specify what type of work is to be done with the volunteering.

### Defining your roles {:#defining-roles}

CiviVolunteer has a few pre-defined roles, but you will likely need to add your own roles for the specific needs of your organization.

To add and edit roles, go to **Volunteers > Configure Roles** (or follow **Manage roles** from the Shifts & roles screen).

!!! tip
    The roles defined within CiviVolunteer are used across *all volunteering projects*, so if you plan to have a large number of projects you may want to think about what set of roles you can best define to apply to all of them.

When defining roles, you can use the **Description** field to provide additional information about the role. The description is used in the following places. 

* On the [sign-up form](./sign-up-form.md), a small quote bubble displays next to each role to activate a pop-up with the role description.
* The description is also written into the confirmation email sent to the volunteer after signup.


## Time

Opportunities can specify the time frame using the following "schedule types":

* **Fixed shift:** Select this schedule type for a volunteer opportunity with a specific start time and duration. *Example: Pamphleteering starts at 9:00 AM and lasts for two hours.*
* **Any time in a window:** Select this schedule type for a volunteer opportunity that can be performed at any time inside a window between a start and end date/time. *Example: I need a volunteer to complete 15 hours of filing sometime during the month of December.*
* **Ongoing:** Select this schedule type for a volunteer opportunity with no time or duration constraints — only a start. *Example: I need a volunteer graphic designer.*

## Volunteering without a specific shift

Below the shift rows, the checkbox **Let people volunteer without picking a shift** controls the project's *flexible need*. When checked, the public [sign-up form](./sign-up-form.md) offers the project itself as an "Any time you like" choice in addition to its dated shifts. Volunteers who choose it appear in the "Available volunteers" pool when you [assign volunteers](./assignments.md), waiting to be placed into a specific shift.

Every project has exactly one flexible need; it always exists, and this checkbox only controls its visibility on the public page.
