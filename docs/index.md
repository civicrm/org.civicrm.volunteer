# CiviVolunteer

## Overview

The CiviVolunteer extension provides tools for signing up, managing, and tracking volunteers.

### Features

* Define multiple [volunteer projects](/projects).
    * (Optionally) [associate a project with a CiviCRM event](/projects#events).
    * Define specific [volunteer opportunities](/opportunities) for each project with distinct roles and shifts.
* Allow volunteers to:
    * [sign up for specific opportunities](/sign-up-form) themselves.
    * [express interest](/interest-form) generally in volunteering, without signing up for anything specifically.
* Manually [assign volunteers to shifts](/assignments)
* [Log](/logging-hours) and [report](/reporting) on volunteer hours

This documentation book provides assistance to users, administrators, and developers of CiviVoluneer.


## Other resources

* [GitHub repository](https://github.com/civicrm/org.civicrm.volunteer)
* [Release downloads](https://civicrm.org/extensions/civivolunteer) (within CiviCRM.org's extensions directory)
* [Issue tracking](https://issues.civicrm.org/jira/browse/VOL) (in a Jira project)
* [Q&A on StackExchange](http://civicrm.stackexchange.com/questions/tagged/civivolunteer) (with the `civivolunteer` tag)

## Requirements

* CiviCRM 4.4 or higher
* The [Angular Profiles](https://civicrm.org/extensions/angular-profile-utilities) extension must also be installed and enabled

## Known Issues

* The installer will not create the "register to volunteer" permission automatically for Joomla installations.

## Future plans

* Going forward there are some great ideas on the drawing board for phase 2 including: specification of qualifications and skill-matching, linking volunteer opportunities directly to an organization or individual, recurring volunteer opportunities, public recognition / rewards, and self-service logging of volunteer hours.
* If your organization has a invested in a CiviCRM installation, and you foresee a need for CiviVolunteer, consider donating to the ongoingdevelopment of CiviVolunteer through the [Make it Happen program](https://civicrm.org/make-it-happen/civivolunteer-improvements).
* Developers within organizations that would like to use CiviVolunteer are more than welcome to participate in the development and testing effort. Contact us via the project's [GitHub repository](https://github.com/civicrm/civivolunteer), email us at inquire@ginkgostreet.com or call us at 1-888-223-6609.
