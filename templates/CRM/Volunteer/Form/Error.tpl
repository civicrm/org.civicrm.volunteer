<div class="error messages">
  <p>
    {ts domain="org.civicrm.volunteer"}You cannot proceed to register because of the following problem(s):{/ts}
  </p>
  <ul>
    {foreach from=$errors item=errorMsg}
      <li>{$errorMsg}</li>
    {/foreach}
  </ul>
</div>
<div class="action-link">
  <a href="{crmURL p='civicrm/vol/#/volunteer/opportunities'}" class="button">
    <i class="crm-i fa-search" role="img" aria-hidden="true"></i> {ts domain="org.civicrm.volunteer"}Find more volunteer opportunities{/ts}
  </a>
  <a href="{crmURL p=''}" class="button"><i class="crm-i fa-home" role="img" aria-hidden="true"></i> {ts domain="org.civicrm.volunteer"}Home{/ts}</a>
</div>