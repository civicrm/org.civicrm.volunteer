<div id="crm-volunteer-event-action-items" class="crm-section">
    <a href="#" class="button crm-volunteer-popup" data-vid="{$vid}" data-tab="Define"><span><div class="icon ui-icon-pencil"></div>{ts domain='org.civicrm.volunteer'}Define Volunteer Needs{/ts}</span></a>
    <a href="#" class="button crm-volunteer-popup" data-vid="{$vid}" data-tab="Assign"><span><div class="icon ui-icon-pencil"></div>{ts domain='org.civicrm.volunteer'}Assign Volunteers{/ts}</span></a>
    <a href="{$volunteerLogURL}" class="button" data-popup-settings='{literal}{"dialog":{"width":"85%", "height":"80%"}}{/literal}'><span><div class="icon ui-icon-pencil"></div>{ts domain='org.civicrm.volunteer'}Log Volunteer Hours{/ts}</span></a>
    <div class="clear"></div>
</div>
{include file="CRM/Volunteer/Page/Angular.tpl" location="bottom"}