{crmStyle ext=org.civicrm.volunteer file=css/listings.css}

<h1>Volunteer Listings</h1>

{foreach from=$sortedResults key=display_date item=assignments}
<h2>{$display_date}</h2>
<table>
    <tr>
        <th>Volunteer Name</th>
        <th>Role</th>
        <th>Contact</th>
    </tr>
    {foreach from=$assignments item=assignment}
    <tr>
        <td>
            <a href='{crmURL p='civicrm/contact/view' q='cid='}{$assignment.contact_id}'>{$assignment.name}</a>
        </td>
        <td>{$assignment.role}</td>
        <td>
            {if $assignment.email}
                <a href="mailto:{$assignment.email}" title="Send {$assignment.name} an email.">Send email</a> 
            {/if}
            {if $assignment.email && $assignment.phone}
                |
            {/if}
            {if $assignment.phone}
                <a href="tel:{$assignment.phone}" title="Telephone {$assignment.name}.">Make phone call</a>  | 
                <a href="sms:{$assignment.phone}" title="Send {$assignment.name} an SMS message.">Send text message</a> 
            {/if}
            </td>
    </tr>
    {/foreach}    
</table>
{/foreach}

<a href='{crmURL p='civicrm/vol/#/volunteer/manage'}'}><input type='button' value='Back to Volunteer Project Management.'/></a>