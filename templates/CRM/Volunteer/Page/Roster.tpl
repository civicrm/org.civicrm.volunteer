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
            {assign var='emailParams' value="action=add&reset=1&atype=3&cid=`$assignment.contact_id`"}
            <a class="button" href="{crmURL p='civicrm/activity/email/add' q=$emailParams}"
               title="{ts escape='htmlattribute' 1=$assignment.name domain='org.civicrm.volunteer'}Send %1 an email.{/ts}">
               <i class="crm-i fa-envelope-o"></i>
               {ts}Email{/ts}</a>
          {/if}
          {if $assignment.phone}
            <a class="button" href="tel:{$assignment.phone}"
               title="{ts escape='htmlattribute' 1=$assignment.name domain='org.civicrm.volunteer'}Telephone %1.{/ts}">
            <i class="crm-i fa-phone"></i>
            {ts}Call{/ts}</a>
            <a class="button" href="sms:{$assignment.phone}"
               title="{ts escape='htmlattribute' 1=$assignment.name domain='org.civicrm.volunteer'}Send %1 an SMS message.{/ts}">
            <i class="crm-i fa-mobile"></i>
            {ts}SMS{/ts}</a>
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
