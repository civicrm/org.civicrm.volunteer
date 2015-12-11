    {foreach from=$sortedResults key=display_date item=assignments}
    <h3>{$display_date}</h3>
    <table>
        <tr>
            <th>Volunteer Name</th>
            <th>Role</th>
            <th>Contact</th>
        </tr>
        {foreach from=$assignments.values item=assignment}
        <tr>
            <td>
                {$assignment.name}
            </td>
            <td>{$assignment.role_label}</td>
            <td>
                {if $assignment.email}
                    <a href="mailto:{$assignment.email}" title="Send {$assignment.name} an email."><input type='button' value='Send email'/></a>
                {/if}
                {if $assignment.email && $assignment.phone}
                    |
                {/if}
                {if $assignment.phone}
                    <a href="tel:{$assignment.phone}" title="Telephone {$assignment.name}."><input type='button' value='Make phone call'/></a>  |
                    <a href="sms:{$assignment.phone}" title="Send {$assignment.name} an SMS message."><input type='button' value='Send text message'/></a>
                {/if}
                </td>
        </tr>
        {/foreach}
    </table>
    {/foreach}
    <br/>
    <div class='dateBlock'>
        Assignments that end before {$endDate|crmDate} are not shown.
    </div>
    <br/>
    <br/>

<a href='{crmURL p='civicrm/vol/#/volunteer/manage'}'><input type='button' class="backButton" value='Back to Manage Volunteer Projects.'/></a>
