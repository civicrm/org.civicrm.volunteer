# Volunteer Projects

CiviVolunteer uses "projects" to compartmentalize different kinds of volunteering. All volunteering information must be associated with a specific project.

To see all the active projects, go to **Volunteers > Manage Volunteer Projects**. If you're just starting out, you probably won't see any projects here, so below we'll create one.

## Characteristics of a project {:#characteristics}

In addition to simple settings such as "Title" and "Description", each project has the following characteristics:

* **Multiple [volunteering opportunities](#opportunities):** Within each project, you can define many different opportunities and assign contacts to those opportunities. In turn, each opportunity can be filled by multiple [assignments](assignments) to separate volunteers.

* **Ongoing, or event-based:** Projects, by themselves, do not have dates &mdash; so a simple project may be considered "ongoing". If you wish to specify a time frame for a project, you can [associate it with an event](#events).

* **Active, or not:** Projects can be marked as active or inactive. Only *active* projects will be displayed to potential volunteers. You may wish to keep a project inactive while it is still in the planning stages, or after it is completed.

* **Location:** Specifies the physical location (as an address) where the volunteering will take place. The location is useful if potential volunteers want to know which projects are in their vicinity.

* **Campaign:** Projects can be associated with [campaigns](https://docs.civicrm.org/user/en/stable/campaign/what-is-civicampaign/)

    !!! tip
        You can restrict the campaigns available for association with volunteering projects (by campaign type) by choosing **Volunteers > Configure Volunteer Settings** and looking in the **Global Settings** section.

* **Multiple [relationships](#relationships) to contacts:** In order to control editing access and email notifications for each project, we must add relationships from the project to specific CiviCRM contacts.

* **Multiple [registration profiles](#profiles):** Specify which questions to ask volunteers when they [sign up](sign-up-form).


## Creating a new project {:#new}

### New stand-alone project {:#stand-alone}

A "stand-alone" project is not associated with an event, and thus can be ongoing. Create one as follows:

1. Choose **Volunteer > New Volunteer Project**.
2. Fill out the settings and click **Save**.

Next, you can define [volunteer opportunities](/opportunities) by finding the project under **Volunteer > Manage Projects**.

### New event-based project {:#events}

To associate a project with an event, the project must be created from within the event's configuration.

1. First create the event.
2. Configure the event, and choose the **Volunteers** tab.
3. Adjust project settings as necessary.
3. Click **Save** at the bottom of the Volunteers tab to create the project and associate it with the event.

Next, you can define [volunteer opportunities](/opportunities) by finding the project under **Volunteer > Manage Projects**.

!!! note
    When an event has an associated volunteer project, the event's info page will show a "Volunteer Now" button which takes users to a [sign-up form](/sign-up-form) showing all the available [volunteer opportunities](/opportunities) defined for this project.


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

Contacts listed as "Manager" will be BCC'd on all confirmation emails sent by CiviCRM to volunteers who fill out the [sign up form](/sign-up-form).

### Beneficiary

When activities are created for volunteering assignments, all contacts listed as "Beneficiary" of the volunteering project will be attached to these activities in the "With Contact" field.

The beneficiary relationship can also be used to report the total number of hours volunteered for specific beneficiaries.

### Other project relationships

Other types of project relationships can be added for any specific [reporting](/reporting) needs of your organization. To add a new type of project relationship choose **Volunteers > Configure Project Relationships**.


## Profiles for volunteer registration {:#profiles}

When people sign up to volunter, CiviVolunteer uses profiles to control the questions in the [sign-up form](/sign-up-form).

!!! tip
    Read more about [CiviCRM profiles](https://docs.civicrm.org/user/en/stable/organising-your-data/profiles/) (in the User Guide) to learn how to edit the fields within a profile and add new profiles.

These profiles are set per-project, and multiple profiles can be used in sequence.

Edit the project to select the profile(s) you would like to use.

To edit the fields within the profiles go to **Administer > Customize Data and Screens**. Also read about [custom data](/custom-data) if you want to add fields within these profiles that do not correspond to any fields already in CiviCRM.

### Group registration

When volunteers sign up (i.e. register), you can also offer them the option of  registering *other* people, too. We call this "group registration", and when it's enabled, the registration form will first ask the user for *their* information and then ask them for the "Number of Additional Volunteers". Subsequetnly, CiviVolunteer will ask questions about each of the additional volunteers and these questions that CiviVolunteer presents to the *additional* volunteers can even be *different* from questions presented at first to the person signing everyone up.

To enable group registration, select at least one profile to be used for "Group Registration" or "Both".

Now let's say you want to ask different questions of the additional volunteers. *(Perhaps you want to collect a phone number for the "primary" volunteer who is signing everyone up, but don't feel this is necessary to collect for all the other volunteers signed up by this person)*. Then choose different profiles to be used for "Individual Registration" vs "Group Registration". The fields in the "Individual Registration" profile will be presented first. Then the fields in the "Group Registration" profile will be presented after the "Additional Volunteers" question.


## Changing the default project settings {:#defaults}

To change the default settings when creating a new project, choose **Volunteers > Configure Volunteer Settings**.
