{* HEADER *}

<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="top"}
</div>


{foreach from=$elementGroups item=elementNames key=groupName}
    <fieldset>
        <legend>{$groupName}</legend>
    {foreach from=$elementNames item=elementName}
        <div class="crm-section">
            <div class="label">{$form.$elementName.label}</div>
            <div class="content">{$form.$elementName.html}</div>
            <div class="clear"></div>
        </div>
    {/foreach}
    </fieldset>
{/foreach}


<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>