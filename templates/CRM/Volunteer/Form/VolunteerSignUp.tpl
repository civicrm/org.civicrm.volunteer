<div class="crm-block crm-volunteer-signup-form-block">

  <div class="help">
    <p>
      {ts domain='org.civicrm.volunteer'}Thank you for being a volunteer! You are registering for the following volunteer commitments:{/ts}
    </p>
  </div>

  <div class="crm-volunteer-signup-summary">
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
  </div>

  <div class="help">
    <p>
      {ts domain='org.civicrm.volunteer'}Please provide the following information and submit the form to complete your registration.{/ts}
    </p>
  </div>

  <div class="crm-volunteer-signup-profiles">
    {foreach from=$customProfiles key=ufID item=ufFields }
      {include file="CRM/UF/Form/Block.tpl" fields=$ufFields}
    {/foreach}
  </div>

  <div>
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>