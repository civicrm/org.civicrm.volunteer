# Volunteer assignments

An "assignment" links a CiviCRM contact to a specific [volunteering opportunity](/opportunities). After defining your opportunities, it's time to start assigning some volunteers to these opportunities!

## Allowing volunteers to self-assign

Volunteers can use the [sign-up form](/sign-up-form) to assign themselves to specific opportunities.


## Manually Assigning Volunteers {:#assign-volunteers}

A user with the proper [permissions](/installation#permissions) *(henceforth know as a "staff member")* can sign anyone up to fill a volunteering opportunity.

1. Go to **Volunteers > Manage Volunteer Projects**
2. Find the project
3. Choose **Assign Volunteers**

### The Available Volunteers list

The left side shows a list of "Available Volunteers" which is populated by either of the following actions:

* A volunteer uses the [sign-up form](/sign-up-form) and selects "Any" as the shift *(which is only possible if "Allow users to sign up without specifying a shift" is checked while defining [opportunities](/opportunities))*
* A staff member manually adds a contact to this list by clicking **Add Volunteer...** below it.

This Avilable Volunteers list will persist even after closing Assign Volunteers. Think of it as the people you have "on deck", waiting to be placed into a specific opportunity.

### Making and editing assignments

Volunteers must be added to the Available Volunteers list before they can be assigned to any opportunities. After this list contains some contacts, make assignments using any of the following methods:

* Drag and drop volunteers from Available Volunteers to the red **More Needed** boxs below the opportunities.
* Click the triangle icon to the right of a volunteer and choose **Move to** or **Copy to**.

![Assign Volunteers screenshot](/images/assign-volunteers.gif)

When an opportunity has reached the required number of volunteer assignments, CiviVolunteer won't allow any more.

!!! caution
    When you assign a contact to an opportunity, CiviVolunteer does not check whether the contact is already assigned to a different opportunity, overlapping in time. You will have to take this logic into account to avoid double-booking volunteers.

### Removing assignments

To remove an assignment, use the arrow button and choose **Move to** or **Delete**.

### Searching for volunteers based on skill level {:#searching}

If you have set up and collected [custom data](/custom-data) on volunteer skills and interests (using the "Volunteer Infomration" custom data set), you can quickly search for volunteers based on criteria within these fields as follows:

1. Within **Assign Volunteers**, hover over the box for an assignment which is still in need of volunteers
2. Notice a magnifying glass icon appear at the top right of this box
3. Click the magnifying glass icon.
4. Search, and select volunteers


## Confirmation emails

When a person fills out the [sign-up form](/sign-up form), CiviVolunteer sends them a confirmation email with the [project managers](/projects#manager) BCC'd. (This email is *not* sent when using "Assign volunteers".)

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

To see a summary of all the volunteers signed up for opportunities within a given project, you can do any of the following:

* Use [Assign Volunteers](#assign-volunteers) as a way to *view* the assignments
* Click on **View Volunteer Roster** (from the **Manage Projects** screen) to see a similar view
* Use a [report](/reporting) to gain even more control over what data is displayed.


