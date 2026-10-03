# Logging volunteer hours

Staff can log actual hours worked by each volunteer on a regular basis which can be useful to track for funder reports. 

## How to

Log hours as follows:

1. Go to **Volunteers > Manage Volunteer Projects**
1. Find your project
1. Open the **Hours** step (from the `…` menu, or by opening the project and stepping through the workflow; **Log hours for this shift** on the Assign volunteers screen brings you here with the shift already selected)
1. Choose which shift you are logging hours for, or **All shifts** to work on the whole project at once.
1. For each volunteer, set the **Status**, the **Hours worked**, and optionally a **Note**.

The whole screen saves as one transaction: if one row fails validation, nothing is partially saved. Bulk controls above the table let you **Mark everyone Attended** and set a number of hours for everyone attended at once.

* The status dropdown offers every enabled activity status (plus the status a row already holds, so re-saving doesn't rewrite it). The label "Attended" corresponds to CiviCRM's existing "Completed" status.
* Hours are stored to the minute; the form rounds fractional hours you enter to the nearest minute.
* **Add someone who wasn't signed up** records a walk-in — a volunteer who turned up but was never assigned. A walk-in is dated to the shift you are logging against.
* Logging hours for a walk-in is allowed even past a shift's nominal capacity (it records something that already happened); normal assignment creation never exceeds capacity.

"Hours worked" are stored within the activity for the [assignment](./assignments.md). See [reporting](./reporting.md) for more info about viewing this data after it's logged.


## Commending volunteers

Within the **Log hours** screen, you can also click the star icon to write a "commendation" for the volunteer's performance within the project. It just gets stored in CiviCRM &mdash; it doesn't get emailed to them.

Commendations are stored as separate activities (with the type "Volunteer Commendation"), carrying the project's campaign. Once made, you can see them on the **Activities** tab for a contact.

!!! caution "Caveat"
    Commendations are associated with *projects* not with *assignments*. If you have a volunteer who worked the "Set up" assignment and the "Clean up" assignment for an event, you will only be able to write *one* commendation for them, as a summary of their performance throughout the entire project.
