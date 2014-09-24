<div class="crm-project-id-{$vid} crm-block crm-volunteer-signup-form-block">

  {foreach from=$customProfiles key=ufID item=ufFields }
    {include file="CRM/UF/Form/Block.tpl" fields=$ufFields}
  {/foreach}

  <div class="crm-section volunteer_role-section">
    <div class="label">{$form.volunteer_role_id.label}</div>
    <div class="content">{$form.volunteer_role_id.html}</div>
  </div>
  <div class="crm-section volunteer_shift-section">
    <div class="label">{$form.volunteer_need_id.label}</div>
    <div class="content">{$form.volunteer_need_id.html}</div>
  </div>
  <div class="crm-section volunteer_details-section">
    <div class="label">{$form.details.label}</div>
    <div class="content">{$form.details.html}</div>
  </div>

  <div>
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>