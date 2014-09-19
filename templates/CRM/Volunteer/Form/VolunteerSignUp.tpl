<div class="crm-project-id-{$vid} crm-block crm-volunteer-signup-form-block">

  {foreach from=$customProfiles key=ufID item=ufFields }
    {include file="CRM/UF/Form/Block.tpl" fields=$ufFields}
  {/foreach}

  <div>
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>