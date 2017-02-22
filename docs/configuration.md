# Configuration

## Volunteer Roles

With the initial installation comes a couple of standard roles. You may
need to create additional volunteer roles first.

**Administer > Administration Console > Volunteer Roles** OR
**Volunteers > Configure Roles**

![](/images/manual_html_47d8ad6cfe81e7e7.png)

The Roles configured from this screen are used directly when defining
volunteer Project Needs for a specific project in **Volunteer > Manage
Volunteer Projects > Define Needs**

Most CiviCRM users will find this screen to be intuitive:

**Label:** can be edited by clicking on it directly.

**Value:** should only be edited by advanced users, as it may affect
relationships to other data in CiviVolunteer and CiviCRM

**Description:** can be edited by clicking on it directly. The role
description is currently used in two primary places. On your volunteer
opportunities page, you'll notice a small quote bubble next to each role
that has a description defined. Clicking the bubble will bring up a
popup listing the description. The other place the description is used
is in the confirmation email sent to the volunteer after signup.

**Order:** the buttons can be used in a familiar way to order to the
top, bottom, or move up or down one in the list

**Reserved:** determines if the Role can be deleted or disabled, not
editable

**Enabled:** determines whether or not this Role will show up in
dropdowns for selection in CiviVolunteer, not editable directly

There are also links to the right for each Role:

**Edit:** brings up a dialog to edit the Label, Value and Order manually
(specifying a number), as well as allow for a rich text description

**Disable:** brings up a confirmation dialog to ensure this is the
intended action, and if confirmed will disable the Role

**Delete:** brings up a dialog to ensure this is the intended action and
if confirmed will delete the Role from CiviVolunteer completely.

**New Roles:** can be created by clicking on **+ Add Volunteer Role**
button at the bottom left of the dialog. This will bring up a screen
identical to the **Edit** dialog for an existing Role, with some
information pre-populated.

## Volunteer Project Relationship Roles

![](/images/manual_html_f24ebb4256385745.png)

The Project Relationships configured from this screen are used directly
when creating volunteer projects in: **Volunteer > New Volunteer
Project.**

Default there are three roles defined: *Owner*, *Manager* and
*Beneficiary*.

**Owner:** can edit and delete project, assign people, change roles etc.

When you have made somebody an owner, you will also have to give him the
right permissions in Drupal (CiviVolunteer: edit own volunteer projects)

**Manager:** same as Owner but cannot delete projects. Manager also
receives a copy of the volunteer registration mail. This is not
configurable.

**Beneficiary:** mainly for reporting purposes. Gives information on e.g
'how many hours did people spend for Organisation X.

**Extra Relationships:** add them if you need more information in your
reports. These relationships are just roles on the voluntary project,
they have nothing to do with 'ordinary' relationships' used elsewhere in
CiviCRM.

Most CiviCRM users will find this screen to be intuitive to add new
Project Relationships.

**Label:** can be edited by clicking on it directly.

**Value:** should only be edited by advanced users, as it may affect
relationships to other data in CiviVolunteer and CiviCRM

**Description:** can be edited by clicking on it directly

**Order:** the buttons can be used in a familiar way to order to the
top, bottom, or move up or down one in the list

**Reserved:** determines if the Role can be deleted or disabled, not
editable

**Enabled:** determines whether or not this Role will show up in
dropdowns for selection in CiviVolunteer, not editable directly

There are also links to the right for each Role:

**Edit:** brings up a dialog to edit the Label, Value and Order manually
(specifying a number), as well as allow for a rich text description

**Disable:** brings up a confirmation dialog to ensure this is the
intended action, and if confirmed will disable the Role

**Delete:** brings up a dialog to ensure this is the intended action and
if confirmed will delete the Role from CiviVolunteer completely.

**New Relationships:** can be created by clicking on the **+ Add
Volunteer Project Relationship** button at the bottom left of the
dialog. This will bring up a screen identical to the **Edit** dialog for
an existing Relationship, with some information pre-populated. Note that
there are three reserved Volunteer Project Relationships: Owner, Manager
and Beneficiary. These relationships cannot be disabled or deleted, but
can be edited as needed to suit your organisation.

## Volunteer Settings

Default project settings are used so that every new project will open
with these settings already selected.

**Profiles, Campaigns and Location**

![](/images/manual_html_2a41854d06b790f3.png)

Profiles are used to collect data from the volunteers. Depending on
whether you need individual or group information you can select
different type of information.

NB: If you want to publish voluntary opportunities for a specific
project, you will have to use a URL to refer to it on your website.

e.g:
[https://www.hungervolunteer.org/civicrm/vol/\#/volunteer/opportunities?project=250](https://www.hungervolunteer.org/civicrm/vol/#/volunteer/opportunities?project=250)

with the project id at the end of the URL.

If you use "sign up for Volunteer Opportunities" then it will put all
the info of the different profiles together in 1 sign up profile.

**Campaigns** can be used to link your voluntary projects to the overall
Project (a Campaign). A Campaign in CiviCRM is basically a project with
a start and end date and an overall goal. A goal can be to get 25% more
member over the next 3 months or raise 20.000 euro this year to fund a
new car for your organisation etc.

Specifically interesting for reporting issues, so that all volunteer
projects, activities, mailings, cases etc are all to be traced back to
the same campaign.

The **location** in a project is useful when potential volunteers use
the Search for Volunteer Opportunities Form. They can search for
voluntary possibilities in their area.

NB: there is a bug: you can't add a new location.

**Global Settings**

The second part of the Volunteer Setting contains of the Global
Settings.

![](/images/manual_html_8a6da19065792c27.png)

When you whitelist the campaigns the volunteers can see which campaign
the project belongs too in the little bubble.

