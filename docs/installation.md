# Installation

## Steps

Use the following steps to install CiviVolunteer.

1. **Administer > System Settings > Extensions**
1. **Add New**
1. Sort by name
1. Find, download, and install **CiviVolunteer**.

    !!! note ""
        Versions before 2.5 also required the **Angular Profiles** extension.
        CiviVolunteer 2.5 has no extension dependencies. If you are upgrading and
        no other extension requires Angular Profiles, you can disable and remove
        it after confirming CiviVolunteer works -- see the
        [2.5 release notes](release/2.5.md).

1. If necessary, click Enable after the extension has been downloaded and installed. When the extension is enabled:

    * CiviVolunteer should show up as green-highlighted.
    * The option to **Disable** it will be present.


## Discovering features after installing {:#discovery}

After installing, CiviVolunteer's features can be found in the following places:

* A **Volunteers** menu item, containing:
    * **New Volunteer Project** and **Manage Volunteer Projects** (the five-step project workflow: Details, Shifts & roles, Assign volunteers, Roster, Hours)
    * **Volunteer Hours Report**
    * **Configure Roles**, **Configure Project Relationships** and **Configure Volunteer Settings**
    * **Volunteer Interest Form** and **Search for Volunteer Opportunities** (the public sign-up flow)
* A **Volunteers** tab within the configuration for each event
* A **Volunteer Report** report template, and the SearchKit-based **Volunteer Hours** report


## Permissions {:#permissions}

CiviVolunteer adds the following permissions, which should be configured within your CMS before using:

| Permission | Allows |
| --- | --- |
| register to volunteer | access the public opportunity search and sign-up form |
| log own hours | record one's own volunteer hours |
| create volunteer projects | create new projects |
| edit own volunteer projects | edit projects one owns (via the project Owner relationship) |
| edit all volunteer projects | edit any project; also gates the hours reports |
| delete own volunteer projects | delete projects one owns |
| delete all volunteer projects | delete any project |
| edit volunteer project relationships | change project relationship configuration |
| edit volunteer registration profiles | change which profiles a project's sign-up uses |

The public sign-up form additionally requires the core CiviCRM permission *access AJAX API* for anonymous users.

!!! note
    Project-level authority (owners and managers of a project) refines these global permissions: roster access follows project authority, and deleting a project is refused while any volunteer assignment exists.


## Removing

If you no longer wish to use CiviVolunteer, you may disable it, or uninstall it.

* **Disable** - will turn off CiviVolunteer's features, but preserve any data that you have created with it. If you re-enable CiviVolunteer later, you'll be back where you left off. 
* **Uninstall** - can be done after disabling, and will completely remove all traces of CiviVolunteer, including the data created with it. If you re-install CiviVolunteer later, you'll be back to square one, before you ever installed it.
