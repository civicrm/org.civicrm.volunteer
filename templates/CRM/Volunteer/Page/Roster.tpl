{foreach from=$sortedResults key=display_date item=assignments}
  <h3>{$display_date}</h3>
  <table>
    <tr>
      <th>{ts domain='org.civicrm.volunteer'}Volunteer Name{/ts}</th>
      <th>{ts domain='org.civicrm.volunteer'}Role{/ts}</th>
      <th>{ts domain='org.civicrm.volunteer'}Contact{/ts}</th>
    </tr>
    {foreach from=$assignments.values item=assignment}
      <tr>
        <td>
          {$assignment.name}
        </td>
        <td>{$assignment.role_label}</td>
        <td>
          {if $assignment.email}
            <a href="mailto:{$assignment.email}" title="{ts 1=$assignment.name domain='org.civicrm.volunteer'}Send %1 an email.{/ts}">
              <input type='button' value="{ts domain='org.civicrm.volunteer'}Email{/ts}" />
            </a>
            {/if}
            {if $assignment.email && $assignment.phone}
            |
          {/if}
          {if $assignment.phone}
            <a href="tel:{$assignment.phone}" title="{ts 1=$assignment.name domain='org.civicrm.volunteer'}Telephone %1.{/ts}">
              <input type='button' value='{ts domain='org.civicrm.volunteer'}Call{/ts}'/>
            </a>  |
            <a href="sms:{$assignment.phone}" title="{ts 1=$assignment.name domain='org.civicrm.volunteer'}Send %1 an SMS message.{/ts}">
              <input type='button' value='{ts domain='org.civicrm.volunteer'}SMS{/ts}'/>
            </a>
            {/if}
        </td>
      </tr>
    {/foreach}
  </table>
{/foreach}
<br/>
<div class='dateBlock'>
  <p>{ts 1=$endDate|crmDate domain='org.civicrm.volunteer'}Assignments that end before %1 are not shown.{/ts}</p>
</div>

<a href='{crmURL p='civicrm/vol/#/volunteer/manage'}'>
  <input type='button' class="crm-vol-modal-closer" value='{ts domain='org.civicrm.volunteer'}Back to Manage Volunteer Projects.{/ts}'/>
</a>
