# CiviVolunteer

## Overview

The [CiviVolunteer extension](https://github.com/civicrm/org.civicrm.volunteer.git "CiviVolunteer extension") provides tools for signing up, managing, and tracking volunteers.

### Features

* Define multiple [volunteer projects](./projects.md), each edited through a five-step workflow — Details, Shifts & roles, Assign volunteers, Roster, Hours.
    * (Optionally) [associate a project with a CiviCRM event](./projects.md#events).
    * Define specific [volunteer opportunities](./opportunities.md) for each project with distinct roles and shifts.
* Manage projects from a searchable list or an at-a-glance [dashboard](./projects.md#managing-existing-projects).
* Allow volunteers to:
    * [sign up for specific opportunities](./sign-up-form.md) themselves, through a public, mobile-friendly page with quick filters (weekends, evenings, no fixed time).
    * [express interest](./interest-form.md) generally in volunteering, without signing up for anything specifically.
* Manually [assign volunteers to shifts](./assignments.md) with drag-and-drop, and see who is coming on the [roster](./assignments.md#roster).
* [Log](./logging-hours.md) and [report](./reporting.md) on volunteer hours.

This documentation book provides assistance to users, administrators, and developers of CiviVolunteer.


## Other resources

* [GitHub repository](https://github.com/civicrm/org.civicrm.volunteer)
* [Release downloads](https://civicrm.org/extensions/civivolunteer) (within CiviCRM.org's extensions directory)
* [Q&A on StackExchange](http://civicrm.stackexchange.com/questions/tagged/civivolunteer) (with the `civivolunteer` tag)

## Requirements

* CiviCRM 6.16 or higher
* PHP 8.1 - 8.4
* Smarty 5

No other extensions are required. CiviVolunteer 2.5 removed the former
dependency on the Angular Profiles extension -- see the
[2.5 release notes](release/2.5.md).

## Future plans

* Developers within organizations that would like to use CiviVolunteer are welcome to participate in the development and testing effort. Contact us via the project's [GitHub repository](https://github.com/civicrm/org.civicrm.volunteer).
