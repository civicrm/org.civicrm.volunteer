<div class="crm-block crm-volunteer-signup-form-block">

  {foreach from=$customProfiles key=ufID item=ufFields }
    {include file="CRM/UF/Form/Block.tpl" fields=$ufFields}
  {/foreach}

  <div class="crm-section volunteer_details-section">
    <div class="label">{$form.details.label}</div>
    <div class="content">{$form.details.html}</div>
  </div>

  <table>
      <tr><th>Role</th><th>Date and Time</th></tr>
    {foreach from=$volunteerNeeds key=key item=volunteerNeed}
    <tr>
        <td>{$volunteerNeed.role_label}</td>
        <td>{$volunteerNeed.display_time}</td>
    </tr>
    {/foreach}
  </table>
  <div>
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>