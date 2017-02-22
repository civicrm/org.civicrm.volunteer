# New Volunteer Project

## New project

**Volunteer > New Volunteer Project**

![](/images/manual_html_4907831511c60fa9.png)

On this screen, a new project can be created, complete with:

**Project Title (required identifier)**

**Project description**

**Campagne:** option used to link to [CiviCRM
Campagne](http://book.civicrm.org/user/campaign/what-is-civicampaign)

**Locatie:** Select from a list, or choose: *Create a new location* for
this project. If selected from a list, there is the option to "Edit
Location" ,which will be permanently saved after clicking **Save and
Done.** The location is useful if potential volunteers want to know which
projects are in their vicinity.


There is also the ability to enable **is this Project Active?** The
option is enabled (checked) by default. Disabling (unchecking) this
option will make the project invisible to potential volunteers searching
for volunteer opportunities.

This is potentially useful for projects that are still in the planning
phase, which should not allow potential volunteers to sign up, just yet.

2nd part of New project: Relationships:

![](/images/manual_html_94f732733ef9026d.png)

Owner, Manager, Beneficiary

These relationships are specific to CiviVolunteer, and are
self-documented on this screen, as to their intended usage. If
necessary: learn how [CiviCRM handles
Relationships](https://docs.civicrm.org/user/en/stable/organising-your-data/relationships/).

Also: add the right permissions to the right users (e.g. esp. Owner.)

![](/images/manual_html_3260e73e28a2f222.png)


## Volunteer Registration

![](/images/manual_html_8538cfda8a3c4d5c.png)

**The first selection menu** is a list of [CiviCRM
Profiles](https://docs.civicrm.org/user/en/stable/organising-your-data/profiles/)
and few special profiles that have been added specifically for
CiviVolunteer (such as Volunteer Sign Up) . The magnifying glass (with
"voorbeeld") icon to the right allows a quick preview of the resulting
form from that selected Profile.

**Use for:** Individual Registration, Group Registration or Both.

*Individual Registration* will use the selected Profile to collect
information when registering a single Individual.

*Group Registration* will use the selected Profile to collect
information when registering multiple Individuals on the same page.

*Both* will us the selected Profile for all volunteer sign-ups, whether
through Individual or Group Registration.

**(+) add another Profile**

CiviVolunteer will allow as many Profiles as desired for a single
Volunteer Project, configured individually via the 'Use For: options.
Profiles can be added or removed from this page.

Clicking **Save and Done** will save the Project and take the user to
the **Manage Volunteer Projects** screen.



Clicking **Continue** will save the project and take the user to the
**Needs** screen, which can also be accessed from **Manage Volunteer
Projects > Define Needs**

## Manage Volunteer Projects

From the **Volunteers** menu, clicking on **Manage Volunteer
Projects** yields:

![](/images/manual_html_360916e08e6cfe23.png)

Any existing projects will be listed on this screen with the
information:

* Volunteer Project Name and ID
* Associated Entity
* Beneficiaries
* Location
* Active (Yes/No)
* List of actions for the project

A new project can be created by clicking on the **+Add Project** button.
This will display the New Volunteer Project Screen, the same as when
choosing **Volunteer > New Volunteer Project.**

Using the checkboxes to the left of each project (of the checkbox at the
top header), any number of existing projects may be selected for **Bulk
Actions:**

* Delete
* Enable
* Disable

Using the search box at the top right, existing projects in the list can
be filtered by their **Title** of by their associated **Campaign.** The
**Campaign** dropdown ly displays existing Campaigns.

For each individual project in the list, there are the following
additional actions that may be taken:

* Edit
* Define Needs
* Assign Volunteers
* View Volunteer Roster
* Log Hours

### Edit

Edit takes the user to the same screen as **New Volunteer Project,** but
with the current project information filled in and available for
modification.

### Define Volunteer Opportunities

Define Opportunities takes the user to a new screen to define the
volunteers needed for the project.

![](/images/manual_html_a4582ccd0050fd45.png)

Use this form to define the number of volunteers needed for each role
and time slot. For each slot, you will select a role, set the number of
volunteers needed, set a start date and time, and indicate the duration
in minutes.

If you want to include a 'Sign Up' button on the event information page,
check the 'Public' column for at least one of the slots, or check the
box to allow users to sign up without specifying a shift. 'Public' slots
will be visible on the public signup form. You will also need to ensure
that the 'CiviVolunteer: register to volunteer' permission has been
enabled.

If this is a new type of volunteer project, you may need to create
additional volunteer roles first from **Administer > Administration
Console > Volunteer Roles** OR **Volunteers > Configure Roles**

Many volunteer programs do not work on fixed dates. For instance, a
teacher may have a window of several months to schedule a guest lecturer
for the fall semester. For these applications, the use of fuzzy dates is
helpful.

The **End Date/Time** field can be used to specify fuzzy dates, or a
window during which the volunteer is sought. This field can be used in
conjunction with the Start Date/Time and Duration fields to declare a
need for three hours of volunteering sometime in December, for instance.

### **Assign Volunteers**

Assign Volunteers takes the user to a new screen to view roles currently
assigned volunteers, copy volunteers between roles, or remove volunteers
from roles.

![](/images/manual_html_ddf7bddbb46845f9.png)

This screen can be used to view which Volunteers are assigned to their
respective Volunteer Events, and which Role(s) they intend to fulfil.
Basic contact information, such as E-mail and Phone Number are displayed
next to each Volunteer Entry. Clicking on a Volunteer's name will take
the user to the associated Contact, where more complete information may
be displayed.

This screen can also be used to move a Volunteer to a different
Role/Event by doing one of the following:

* Drag and drop the Volunteer via the crosshairs to the left of their
  name (to an available empty slot)
* Click the Action Arrow (dropdown menu) to the right of the
  Volunteer's name and choose **Move to** > Desired Event Role

In a similar fashion, using the Action Arrow (dropdown menu), a
Volunteer can be remove from a Role/Event or copied to another
Role/Event by choosing: **Delete** OR **Copy to** Desired Role/Event
respectively.

Note that you can search for volunteers by clicking on the magnifying
glass on the right hand corner per volunteer need. The search shows the
custom fields of the Volunteer data and of specific groups.

Note that there is no checking on whether a person has already signed up
for 2 or more shifts at the same time. Some shifts you can easily do at
the same time. E.g: be a Ticket checker and also be a back up for when
they need First Aid.


### View Volunteer Roster

View Volunteer Roster takes the user to a new screen to view roles
currently assigned volunteers, and provides one-click options for
contacting those volunteers, via E-mail, Call or SMS, based upon the
Contact information available.

![](/images/manual_html_7abd44053d1d3c55.png)

Note that any volunteer assignments that '*end before the current
date'*will not be shown on this screen. In this manner, only current and
future assignments are shown.

Contact your volunteers: use standard CiviCRM functionality

If you want to contact volunteers of one project or one shift or of one
role of one day, use sdvanced search: Contacts – Volunteers – activity
volunteer – scheduled – CiviVolunteer Role – name of project. Send them
either an e-mail or a mass mailing.

Can we expand the CiviVolunteer Tab in activities as far as searching is
concerned. You can now only search for the role, not for the other
things like date or shift. Might be useful to certain organisations.
Once you have done that, you can send mailings to that group of people.

### Log hours

Log hours takes the user to a new screen to record the actual time spent
by each Volunteer in a specific Role.

By Clicking on **+ Add Volunteer** button on the lower left, a new entry
can be recorded with the following fields

* **Star Icon (Commendation):** 'gold star 'to give merit to a
    volunteer' (the user will be asked to write text to explain the
    merit) . The activity 'Volunteer Commendation' is added on the
    contact card of the individual.

* **Contact:** any CiviCRM Contact entered using a searchable dropdown
    interface or the option to create a new Contact

* **Role:** one of the defined Roles for the current Volunteer
    Project, selectable in the dropdown

* **Start date:** the time at which the Volunteer began their
    volunteer work

* **Scheduled duration:** the amount of time (in minutes) reserved for
    volunteering

* **Actual duration:** the actual amount of time (in minutes) spent
    volunteering

* **Status:** a variety of options, from Completed to No Show to
    Available

This screen is designed to be flexible and meet the needs of various
organisations when it comes to tracking their volunteers' time. The only
required fields are **Contact** and **Actual Duration**, and the rest
are optional. Scheduled vs Actual Duration of volunteering can be
compared. Status can be reported upon, and the popularity of particular
Roles can be reported upon as well. These are just a few options made
possible by having the data from the screen.

Known Bug? As soon as I open the log hours page I will always have to
add a number, even when the shift is not begun yet. When I fill in 0
when a shift is cancelled, it won't accept that either.

![](/images/manual_html_a16602a0b0e708b1.png)
