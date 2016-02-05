Dear {contact.display_name},

You are confirmed to volunteer for the following project(s):


{foreach from=$volunteer_projects item=volunteer_project}
Project: {$volunteer_project.title}

{if !empty($volunteer_project.description)}
Description: {$volunteer_project.description}
{/if}

{if !empty($volunteer_project.location)}
Location:
    {$volunteer_project.location.address.street_address}
    {$volunteer_project.location.address.city}

    {$volunteer_project.location.email}
    {$volunteer_project.location.phone}
{/if}

Your Contact:
    {foreach from=$volunteer_project.contacts item=person}
    {$person.display_name}, {$person.email}, {$person.phone}
    {/foreach}

Shifts:
    Role | Description | Start Time | Duration
    {foreach from=$volunteer_project.opportunities item=opportunity}
    {$opportunity.role} | {$opportunity.description} | {$opportunity.display_time} | {$opportunity.duration}
    {/foreach}

{/foreach}

Thank you for your participation!