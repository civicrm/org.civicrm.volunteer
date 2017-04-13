# Custom data

The [sign-up form](/sign-up-form) and the [volunteer interest form](/interest-form) both use [profiles](https://docs.civicrm.org/user/en/stable/organising-your-data/profiles/) to collect information from volunteers. A profile is a set of questions to be asked on a form, and each question must correspond to one database field in CiviCRM. If you want to include a question on your form which does not correspond to a field that already exists in CiviCRM, you'll need to *add a custom field* first, before you can add this question to the profile used for your form.

!!! tip
    Learn more about [custom data](https://docs.civicrm.org/user/en/stable/organising-your-data/creating-custom-fields/) in the User Guide.

For example, let's say you want the [volunteer interest form](/interest-form) to ask "*Are you CPR certified?*". CiviCRM doesn't come with a field for this data, so we'll need to add a custom data field for it.

## The "Volunteer Information" *set* of custom fields {:#volunteer-information}

Custom data fields are stored in "sets". Sets can be attached to different CiviCRM entities, like "Contact" or "Contribution". When CiviVolunteer is installed it creates a set called "Volunteer Information" which is attached to *contacts*. This set of custom fields provides a good place to store information about the skills and interests of your volunteers, and the fields within it are exposed when searching for volunteers within the [Assign Volunteers](/assignments#searching) interface.

!!! note "Contact *sub-type* must be **Volunteer**"
    For the Volunteer Information set to become visible, a contact will need to have **Volunteer** selected as a sub-type. To do this for one individual choose **Edit** and find the **Contact Type** field.

By default there is one field in this set called "Camera Skill Level", mostly as an example for you to follow when creating your own custom fields.

## Adding a new custom field {:#new-field}

1. Go to **Administer > Custom Data and Screens > Custom Fields**.
1. Find the **Volunteer Infomation** set.
1. Click **View and Edit Custom Fields**.
1. Click **Add Custom Field**.
1. Select settings as necessary.

    !!! tip "Tip: use a short field label"
	    Use something short but descriptive for **Field Label** (e.g. "CPR Certification" for our example above). It will only be visible to staff &mdash; the general public will see a *different* label which you set in the *profile*.

    !!! tip "Tip: make it searchable"
        New fields are not searchable by default. You must check the box for **Is this Field Searchable**. If the field is not searchable, you won't be able to use it within the [Assign Volunteers](/assignments#searching) interface.

## Field type {:#type}

When adding a new custom field it's important to consider the field *type* carefully. To return to our example above, if you want to ask volunteers "*Are you CPR certified?*", you might decide to use "yes or no" for the field type. But because CPR certification *expires* it might be better to ask "When were you last CPR certified?" and store CPR certification as a *date* instead.

### Slider widget {:#slider}

CiviVolunteer adds the option to use a slider when choosing the value for a field, which can be helpful when gathering data about the skills or interests of volunteers.

![Slider demonstration](/images/slider.gif)

To use the slider:

1. Set **Data and Input Field Type** to **Alphanumeric** and **Multi-Select**.
1. Check the **Use Slider Selector** box (futher down the form).
1. Ensure that the **Multiple Choice Options** for the field are *in the correct order*. (Items listed first in the list will appear to the left, while items further down the list will appear to the right.)

The options in the multi-select list are converted to stops on a slider. When data is saved through this slider interface, the field will take the value of the option at the slider's position, which means you can search for it like any other set of options.

## Placing your custom field in a profile {:#profile}

If you're trying to ask a custom question to volunteers when they sign up or express interest, then you're not done yet once you've added the custom field! You need to put this custom field into a profile.

* The sign-up form profiles are descrbied in: [project settings](/projects#profiles)
* Interest form profiles are described in: [interest form](/interest-form#customizing)


