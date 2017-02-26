# Self-service volunteer sign-up form

CiviVolunteer allows volunteers to sign themselves up for [opportunities](/opportunities) that you've defined. Each time somone signs up, CiviVolunteer creates an [assignment](assignment). It also makes sure to offer only the opportunites which have not yet reached the desired number of assignments (so that you don't overbook anything).

## Configuration

To get your sign up form working, make sure to do the following. 

* Configure permissions

    !!! caution "Permisions required"
        To provide self-service sign-up for anonymous and/or authenticated users, you will need to enable the following permissions:

        *  CiviVolunteer: register to volunteer
        *  CiviCRM: access AJAX API

* Set up profiles &mdash; the questions on the sign up form are controlled within the [profiles set for the project](/projects#profiles), and these profiles also control whether the form will allow groups to sign up together.


## Accessing the sign-up form

### Sign-up form for all projects

The main sign up form will offer all opportunities to volunteers, even if they are defined within separate projects. To find the link to your main sign up form, go to **Volunteers > Search for Volunteer Opportunities**. The URL will look like this:

`http://example.org/civicrm/vol/#/volunteer/opportunities`

### Sign-up form for a specific project

It's also possible to access a project-specific sign up form that only offers visitors opportunities defined for a specific project.

If the project is associated with an event, the event's info page will show a "Volunteer Now" button which takes the user to a project-specific sign-up form.

If the project is *not* associated with an event, you can find it's project-specific sign up form as follows:

1. Find your project within **Manage Volunteer Projects**
1. In the first column, find the ID number (displayed after the title.)
1. Use a URL like the one below, but with your ID number instead of `3` which is used in this example:

    `https://example.org/civicrm/vol/#/volunteer/opportunities?project=3`
