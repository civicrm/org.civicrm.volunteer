{literal}
    <div ng-app="crmApp" id="crm_volunteer_angular_frame">
        <div ng-view></div>
    </div>
{/literal}

{if $includeNotificationTemplate}
    {include file="CRM/common/notifications.tpl" location="bottom"}
{/if}
