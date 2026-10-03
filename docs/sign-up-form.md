# Self-service volunteer sign-up form

CiviVolunteer allows volunteers to sign themselves up for [opportunities](./opportunities.md) that you've defined. Each time someone signs up, CiviVolunteer creates an [assignment](./assignments.md). It also makes sure to offer only the opportunities which have not yet reached the desired number of assignments (so that you don't overbook anything).

## Configuration

To get your sign up form working, make sure to do the following. 

* Configure permissions

    !!! caution "Permissions required"
        To provide self-service sign-up for anonymous and/or authenticated users, you will need to enable the following permissions:

        *  CiviVolunteer: register to volunteer
        *  CiviCRM: access AJAX API

* Set up profiles &mdash; the questions on the sign up form are controlled within the [profiles set for the project](./projects.md#profiles), and these profiles also control whether the form will allow groups to sign up together.


## The public opportunities page

Volunteers pick their shifts on a public, two-step page: first they choose one or more shifts, then confirm with their details. Nothing is confirmed until they finish.

### Picking shifts

* **Quick filters** — *All shifts*, *Weekends*, *Evenings* (starts at or after 5:00 PM), and *No fixed time* — narrow the list at a glance and are bookmarkable.
* **More filters** reveals the detailed controls: date, role, campaign, and the organizer and location searches described below.
* Shifts are grouped by start date; ongoing shifts appear in their own "No fixed time" group, and the project's flexible need (if offered) appears under "Any time you like".
* Each card shows the role, project, time, description, remaining capacity and duration.
* Selections collect in the sticky **"Your shifts"** sidebar, where they can be removed. **Continue to your details** stays disabled until at least one valid shift is selected.

If filters change while shifts are selected, the selections are kept; if a previously selected shift has since filled up, it is dropped with a warning.

### Filtering opportunities by location

The **More filters** panel can always match project locations by street, city, State/Province, postal-code prefix, and country. When the optional Geocoder extension is enabled and configured as CiviCRM's geocoding provider, the **Within** fields are also available. Entering a distance changes the location fields into the center of a radius search; leaving the distance blank continues to use ordinary address matching.

Distance searches compare against the coordinates stored on each project's address. Existing project addresses which predate Geocoder may need to be re-saved or batch-geocoded before they appear in proximity results.

### Entering details

The confirmation step shows the project's registration [profiles](./projects.md#profiles), a summary of the chosen commitments in the sidebar, and — if the project enables group registration — an **"I am bringing other people"** disclosure that reveals the additional-volunteer questions.

**Back to shifts** returns to the shift picker with the filters and every still-available selection restored. Submitting sends a confirmation email with the date, time and location (with the project's [managers](./projects.md#manager) BCC'd), then returns the volunteer to the page they came from.

## Accessing the sign-up form

### Sign-up form for all projects

The main sign up form will offer all opportunities to volunteers, even if they are defined within separate projects. To find the link to your main sign up form, go to **Volunteers > Search for Volunteer Opportunities**. The URL will look like this:

`http://example.org/civicrm/vol/#/volunteer/opportunities`

### Sign-up form for a specific project

It's also possible to access a project-specific sign up form that only offers visitors opportunities defined for a specific project.

If the project is associated with an event, the event's info page will show a "Volunteer Now" button which takes the user to a project-specific sign-up form, and returns them to the event afterwards.

If the project is *not* associated with an event, you can find it's project-specific sign up form as follows:

1. Find your project within **Manage Volunteer Projects**
1. Choose **Public signup** from the project's `…` menu, or use a URL like the one below, with your project's ID number instead of `3`:

    `https://example.org/civicrm/vol/#/volunteer/opportunities?project=3`

### Embedding

The opportunities page accepts a `hideSearch` parameter (`hideSearch=1` or `hideSearch=always`) that removes the filter controls entirely, for sites that want to present a fixed list of shifts.
