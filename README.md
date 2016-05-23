CiviVolunteer
=============

The CiviVolunteer extension provides tools for signing up, managing, and tracking volunteers.

What Volunteers are Needed and When
===================================
After installing the extension using the automated installer from Manage Extensions,
you'll see a new Volunteers tab for each event (Manage Events > Configure). Check the
'Enable Volunteer Management' box to get started.

You can then build a list of volunteer shifts by clicking 'Define Volunteer Opportunities.'
Shifts consist of a volunteer role (e.g. Usher, Box Office, etc.) plus a date and time
period. For each shift you can specify the number of volunteers required.

Self-service Volunteer Signup
=============================
Once you've defined your volunteer needs, you can decide to include a volunteer signup
form as part of your public event. A "Volunteer Now" button will appear on your event
info page (next to the "Register" button). Volunteers can select a shift or let you
know that they are available for any shift.

You will need to enable the "CiviVolunteer: register to volunteer" and
"CiviCRM: access AJAX API" permissions if you
want to provide self-service signup for anonymous and/or authenticated users.
(Joomla users should see [Known Issues](#known-issues) for more information about
permissions.)

Manage Volunteer Assignments
=============================
The 'Manage Assignments' widget allows you to assign flexible volunteers to shifts,
add new volunteers and change shift assignments - all in an easy to use drag-and-drop panel.

Log and Report on Volunteer Hours
=================================
Finally, staff can log actual hours worked by each volunteer on a regular basis if needed for
funder reports (click "Log Volunteer Hours" from the event's Volunteer tab). Then you can
access the new Volunteer Activity Report (Contacts > Contact Reports) to get
statistics on volunteering. You can even add that report as a dashlet on your dashboard if
you want to keep track of your organization's cumulative volunteering totals.

Dependencies
============
- CiviCRM version 4.6.x
- [Angular Profiles](https://github.com/ginkgostreet/org.civicrm.angularprofiles)

Known Issues
============
For versions of CiviCRM prior to and including 4.6, the installer will not
create extension-defined permissions for Joomla installations (see
[CRM-12059](https://issues.civicrm.org/jira/browse/CRM-12059)). As a result,
the "register to volunteer" permission does not appear in Joomla's access control
interface. As a workaround (see [VOL-71](https://issues.civicrm.org/jira/browse/VOL-71)),
CiviVolunteer will not create or enforce the "register to volunteer" permission
in Joomla installations; Joomla admins won't enjoy the same level of permissions
granularity as installations on other frameworks, and the public sign-up form will
be accessible to everyone.

What's Next
===========
Going forward there are some great ideas on the drawing board for phase 2, including specification
of qualifications and skill-matching, public recognition/reward, and self-service logging of
volunteer hours.

If your organization has invested in a CiviCRM installation, and you foresee a need for CiviVolunteer,
consider donating to the ongoing development of CiviVolunteer through the Make it Happen program.

>> https://civicrm.org/make-it-happen/civivolunteer

Developers within organizations that would like to use CiviVolunteer are more than welcome to
participate in the development and testing effort. Contact us via the project's GitHub
repository.

>> https://github.com/civicrm/org.civicrm.volunteer