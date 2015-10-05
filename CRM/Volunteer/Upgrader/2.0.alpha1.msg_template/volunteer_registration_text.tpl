Dear {contact.display_name},

You are confirmed to volunteer for the following project(s):

{foreach from=volunteer_projects item=volunteer_project}
Project: {$volunteer_project.title}
Location: <address_block>
Your Contact:
{foreach}
<name>, <email>, <phone>
{/foreach}

Shifts:
Role | Description | Start Time | Duration
{foreach}

{/foreach}

{/foreach}

Thank you for your participation!