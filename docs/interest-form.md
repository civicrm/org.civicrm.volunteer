# Volunteer Interest Form

CiviVolunteer provides a public-facing Volunteer Interest Form to allow potential volunteers to express interest in volunteering for your organisation without signing up for a specific volunteer opportunity. This is useful if your organisation doesn't have any volunteer opportunities listed at the time of the potential volunteer's visit, or if none of them are appropriate for the individual in question.

This form allows you to collect whatever information you choose about potential volunteers from basic contact information to self-assessment of ability in various skill areas.

## Accessing

To see the form, choose **Volunteer > Volunteer Interest Form** from the menu. The URL for the form looks like this:

`http://example.org/civicrm/volunteer/join`

To encourage use of the form, link to it from you website, as potential volunteers won't have access to CiviCRM's administrative menu. Without additional configuration, the interest form will look something like this:

## Customizing

As its core, the Volunteer Interest Form is a CiviCRM [profile](https://docs.civicrm.org/user/en/stable/organising-your-data/profiles/) with a fancy URL.


To modify this profile:

1. Go to **Administer > Customize Data and Screens > Profiles**
1. Choose the **Reserved Profiles** tab
1. Find the **Notify Me of Volunteer Opportunities** Profile.
1. Click **Fields**

!!! tip
    If you want to add questions within these this profile that do not correspond to any fields already in CiviCRM, then you'll first need to add [custom data fields](/custom-data).

!!! caution
    Don't change the title of "Notify Me of Volunteer Opportunities" since the profile is reserved and the title is hardcoded.




