<div class="crm-block crm-volunteer-signup-form-block">

  {foreach from=$customProfiles key=ufID item=ufFields }
    {include file="CRM/UF/Form/Block.tpl" fields=$ufFields}
  {/foreach}

  You have signed up to the following roles. Please check and click "submit" to confirm.
  <br/>
  <table>
    <tr>
      <th>Project Title</th>
      <th>Project Beneficiaries</th>
      <th>Role</th>
      <th>Date and Time</th>
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