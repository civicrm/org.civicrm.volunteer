# Volunteer assignments

An "assignment" links a CiviCRM contact to a specific [volunteering opportunity](./opportunities.md). After defining your opportunities, it's time to start assigning some volunteers to these opportunities!

## Allowing volunteers to self-assign

Volunteers can use the [sign-up form](./sign-up-form.md) to assign themselves to specific opportunities.


## Manually Assigning Volunteers {:#assign-volunteers}

A user with the proper [permissions](./installation.md#permissions) *(henceforth know as a "staff member")* can sign anyone up to fill a volunteering opportunity.

1. Go to **Volunteers > Manage Volunteer Projects**
2. Find the project
3. Choose **Assign volunteers** (from the `…` menu, or by opening the project and continuing to the **Assign volunteers** step)

The screen is organized around three metric tiles — volunteers assigned to a shift, open spots left to fill, and available-but-unplaced volunteers — a shift rail on the left, and a panel for the selected shift.

### Making and editing assignments

With a shift selected in the panel:

* Type a name (or paste an email address) into **Add a volunteer to this shift** to search for — or create — a contact, and add them to the shift.
* Drag a card from the **Available volunteers** pool at the bottom onto a shift in the rail, or onto one of the **Open spot** rows in the panel.
* Open a volunteer's `…` row menu to **Move to** another shift, **Also add to** another shift (copying the assignment), or **Remove from this shift**.

Placement updates the board immediately and is undone if the server rejects the write.

When a shift has reached the required number of volunteer assignments, CiviVolunteer won't allow any more — one "Open spot" placeholder is shown for each unfilled slot, and there are none when the shift is full.

!!! caution
    When you assign a contact to an opportunity, CiviVolunteer does not check whether the contact is already assigned to a different opportunity, overlapping in time. You will have to take this logic into account to avoid double-booking volunteers.

### The Available Volunteers list

The pool of "Available volunteers" at the bottom is populated by either of the following actions:

* A volunteer uses the [sign-up form](./sign-up-form.md) and chooses the project's "any time" option instead of a specific shift *(which is only possible if "Let people volunteer without picking a shift" is checked while defining [opportunities](./opportunities))*
* A staff member adds a contact there when placing them without a shift.

This Available Volunteers list will persist even after closing the Assign volunteers screen. Think of it as the people you have "on deck", waiting to be placed into a specific opportunity.

From each shift's panel you can also continue straight to recording what happened with **Log hours for this shift**.

## Confirmation emails

When a person fills out the [sign-up form](./sign-up-form.md), CiviVolunteer sends them a confirmation email with the [project managers](./projects.md#manager) BCC'd. (This email is *not* sent when using "Assign volunteers".)

!!! tip
    To edit the text in the confirmation email

    1. Go to **Administer > CiviMail > Message Templates**
    2. Select **System Workflow Messages**
    3. Find **Volunteer - Registration (on-line)** and click **Edit**.

## How assignments are stored {:#storage}

Assignments are activities, and thus are viewable within the Activities tab for each contact. This also means that you can used the activities fields within the Advanced Search for contacts to filter based on volunteering assignments to some extent.

!!! failure "Do not add assignments by creating new activities"
    CiviCRM will let you add a new "Volunteer" activity to a contact through the Activities tab on the contact's record, but don't do this. You need to create new assignments using one of the methods described above to receive all the expected functionality within CiviVolunteer.

## Viewing a roster of all assignments {:#roster}

The **Roster** step of the project workflow shows everyone assigned to the project — Volunteer / Email / Phone / Role & time — grouped by date, with historical rows available via **Include past assignments**.

From the roster you can:

* search by name, role, email or phone, and filter by assignment status;
* select volunteers and compose an email to them, or use the per-row **Email**, **Call** and **SMS** actions *(email and SMS actions appear only when outbound mail and an SMS provider are configured)*;
* **Print roster** for a print-optimized layout;
* **Export CSV** of every visible row, with spreadsheet-formula characters made safe.

Roster access follows project authority: a project's owners and managers, and holders of *edit all volunteer projects*, may view it. For even more control over what data is displayed, use a [report](./reporting.md).
