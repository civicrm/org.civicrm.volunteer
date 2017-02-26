# Logging volunteer hours

Staff can log actual hours worked by each volunteer on a regular basis which can be useful to track for funder reports. 

## How to

Log hours as follows:

1. Go to **Volunteers > Manage Volunteer Projects**
1. Find your project
1. Click **Log Hours**
1. Fill out the **Actual Duration** and **Status** columns.

!!! bug
    As of version 2.2.1, a known [bug]( https://issues.civicrm.org/jira/browse/VOL-245) prevents you from logging *some* hours unless you log *all* hours for all assigned volunteers at once.

"Hours worked" are stored within the activity for the [assignment](/assignments). See [reporting](/reporting) for more info about viewing this data after it's logged.


## Commending volunteers

Within the **Log hours** screen, you can also click the star icon to write a "commendation" for the volunteer's performance within the project. It just gets stored in CiviCRM &mdash; it doesn't get emailed to them.

Commendations are stored as separate activities (with the type "Volunteer Commendation"). Once made, you can see them on the **Activities** tab for a contact.

!!! caution "Caveat"
    Commendations are associated with *projects* not with *assignments*. If you have a volunteer who worked the "Set up" assignment and the "Clean up" assignment for an event, you will only be able to write *one* commendation for them, as a summary of their performance throughout the entire project.
