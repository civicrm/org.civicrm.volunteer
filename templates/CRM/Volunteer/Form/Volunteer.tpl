<div id="crm-volunteer-event-action-items" class="crm-section">

    <span class="crm-button crm-icon-button crm-span-button" data-vid="{$vid}" data-tab="Define" id="crm-volunteer-event-define">
        <span class="crm-button-icon ui-icon-pencil"> </span>
        <span class="crm-span-button-text">
            {ts domain='org.civicrm.volunteer'}Define Volunteer Needs{/ts}
        </span>
    </span>

    <span class="crm-button crm-icon-button crm-span-button" data-vid="{$vid}" data-tab="Assign" id="crm-volunteer-event-assign">
        <span class="crm-button-icon ui-icon-pencil"> </span>
        <span class="crm-span-button-text">
            {ts domain='org.civicrm.volunteer'}Assign Volunteers{/ts}
        </span>
    </span>

    <a href="{$volunteerLogURL}" class="button" data-popup-settings='{literal}{"dialog":{"width":"85%", "height":"80%"}}{/literal}'><span><div class="icon ui-icon-pencil"></div>{ts domain='org.civicrm.volunteer'}Log Volunteer Hours{/ts}</span></a>

    <span class="crm-button crm-icon-button crm-span-button" id="crm-volunteer-event-edit">
        <span class="crm-button-icon ui-icon-pencil"> </span>
        <span class="crm-span-button-text">
            {ts domain='org.civicrm.volunteer'}Edit Settings{/ts}
        </span>
    </span>

    <div class="clear"></div>
</div>
{include file="CRM/Volunteer/Page/Angular.tpl" location="bottom"}