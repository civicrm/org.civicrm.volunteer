<div class="error messages">
  <p>
    {ts}The volunteer registration form cannot be displayed due to the following error(s):{/ts}
  </p>
  <ul>
    {foreach from=$errors item=errorMsg}
      <li>{$errorMsg}</li>
    {/foreach}
  </ul>
</div>
<div class="action-link">
  <a href="{crmURL p='civicrm/vol/#/volunteer/opportunities'}" class="button">
    <i class="crm-i fa-search"></i> {ts}Find more volunteer opportunities{/ts}
  </a>
  <a href="{crmURL p=''}" class="button"><i class="crm-i fa-home"></i> {ts}Home{/ts}</a>
</div>