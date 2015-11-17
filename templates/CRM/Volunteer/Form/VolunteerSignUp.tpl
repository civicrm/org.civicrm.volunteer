<div class="crm-block crm-volunteer-signup-form-block">

  {foreach from=$customProfiles key=ufID item=ufFields }
    {include file="CRM/UF/Form/Block.tpl" fields=$ufFields}
  {/foreach}

  {ts domain='org.civicrm.volunteer'}You have signed up to the following roles. Please check and click "submit" to confirm.{/ts}
  <br/>
  <table>
    <tr>
      <th>{ts domain='org.civicrm.volunteer'}Project Title{/ts}</th>
      <th>{ts domain='org.civicrm.volunteer'}Project Beneficiaries{/ts}</th>
      <th>{ts domain='org.civicrm.volunteer'}Role{/ts}</th>
      <th>{ts domain='org.civicrm.volunteer'}Date and Time{/ts}</th>
    </tr>
    {foreach from=$volunteerNeeds key=key item=volunteerNeed}
      <tr>
        <td>{$volunteerNeed.project_title}</td>
        <td>{$volunteerNeed.project_beneficiaries}</td>
        <td>{$volunteerNeed.role_label}</td>
        <td>{$volunteerNeed.display_time}</td>
      </tr>
    {/foreach}
  </table>
  <div>
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>