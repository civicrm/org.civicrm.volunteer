# Volunteer Interest Form

As of version 1.4, CiviVolunteer provides a public-facing Volunteer
Interest Form. The purpose of this form is to allow potential volunteers
to express interest in volunteering for your organisation without
signing up for a specific volunteer opportunity. This is useful if your
organisation doesn't have any volunteer opportunities listed at the time
of the potential volunteer's visit, or if none of them are appropriate
for the individual in question.

This form allows you to collect whatever information you choose about
potential volunteers from basic contact information to self-assessment
of ability in various skill areas.

To see the form, choose **Volunteer > Volunteer Interest Form** from
the menu. To encourage use of the form, you should link to it from you
website, as potential volunteers won't have access to CiviCRM's
administrative menu. Without additional configuration, the interest form
will look something like this:

![](/images/manual_html_5120dcea40743284.png)

## Configuring the Volunteer Interest Form

As its core, the Volunteer Interest Form is nothing more than a CiviCRM
profile with a fancy URL (Since Drupal doesn't provide site
administrators an easy way to display a Profile at a custom URL,
CiviVolunteer supplies the
[http://example.org/civicrm/volunteer/join](http://example.org/civicrm/volunteer/join)
URL for this; WordPress users may find this feature unnecessary).

You can customize the fields in the Volunteer Interest Form by selecting
**Administer > Customize Data and Screens > Profiles** from the menu
and editing the **Notify Me of Volunteer Opportunities** Profile.

NB: you can also add your own Profile by adding a new one and then
copying the URL to a link on your website
(www.example.com/civicrm/vol/\#/volunteer/opportunities?project=3)

NB: You can't change the title of 'Notify me of Voluntary Opportunities'
since the Profile is reserved and the Title is hardcoded. If you want to
change that go to your control panel and ...... ??

## Collecting Skills or Interest on a Continuum

As of version 1.4, CiviVolunteer provides a slider widget for multi
select fields where the option list represents a continuum. The options
in the multi-select list are converted to stops on a slider. Items with
a lower weight in the option group will appear on the left side of the
slider, while heavily weighted items will appear on the right.

![](/images/manual_html_b82c7e5e28680dd1.png)

![](/images/manual_html_e02ddfb648893b55.png)

![](/images/manual_html_fcf8bceccb0d1e1d.png)

The Skill Level option group that ships with CiviVolunteer demonstrates
the benefit of this feature. When a user drags the slider, the current
option (e.g. Gemiddeld = Average) as well as all options with a lower
weight are selected. As a result, a search for volunteers qualified to
Gemiddeld-level, will include Volunteers with 'Ervaren' of 'Expert'
level skills.

To enable the widget for a Custom Field, select **Alphanumeric
Multi-Select** as the **Data and Input Field Type** for the field. When
these selections are made, a checkbox labelled **Use Slider Selector**
will appear. Check it and save the field and your select list will be
rendered as a slider widget.
