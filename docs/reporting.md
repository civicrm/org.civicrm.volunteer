# Reporting

CiviVolunteer ships two ways to report on volunteer hours.

## Volunteer Hours report

The redesigned **Volunteer Hours** report lives at **Volunteers > Volunteer Hours Report** (and as the **Hours report** tab inside a project, when you have the permissions below). It is a SearchKit report with three views:

* **Headline Totals** — volunteers, assignments, scheduled hours, logged hours, and shifts still awaiting hours.
* **Volunteer Summary** — per-volunteer totals across their assignments.
* **Assignment Detail** — one row per assignment, with the volunteer, project, role, shift, status, scheduled and logged time.

Viewing it requires *edit all volunteer projects* and *view all contacts*.

The report is defined as managed SearchKit searches over the read-only `VolunteerHoursReport` API4 entity, so its searches can also be cloned, customized, and re-saved from CiviCRM's **SearchKit** admin screen like any other SearchKit search.

## Volunteer Report (classic template)

For sites that prefer classic CiviReport, the **Volunteer Report** template is also included. To create a report from this template:

1. Choose **Reports > Contact Reports**
1. Click **New Contact Report** and then **Volunteer Report**
1. Select desired fields and click **Refresh results** to preview.
1. Choose **Actions > Save** and enter a name to create a report from this template.
1. Later, you can view this same report under **Reports > Contact Reports**.

The template offers **Campaign** and **Associated event** dimensions (as fields, filters, order-bys and group-bys) alongside the project, volunteer and time columns, and supports custom fields used on volunteer assignments as columns and filters. Campaign and event columns are only offered while the corresponding CiviCRM component is enabled.

The shipped instance of this report requires *edit all volunteer projects*.

!!! tip
    For more help managing reports and report templates, see the [CiviReport help](https://docs.civicrm.org/user/en/stable/reporting/what-is-civireport/).
