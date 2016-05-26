{* HEADER *}

<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="top"}
</div>


{foreach from=$elementGroups item=elementNames key=groupName}
    <fieldset>
        <legend>{$groupName}</legend>
        {if $helpText.$groupName}
            <div class="help">{$helpText.$groupName}</div>
        {/if}
        {foreach from=$elementNames item=elementName}
            <div class="crm-section">
                <div class="label">
                    {$form.$elementName.label}
                </div>
                <div class="content">
                    {$form.$elementName.html}
                    {if $fieldDescriptions.$elementName}
                        <div class="description">{$fieldDescriptions.$elementName}</div>
                    {/if}
                </div>
                <div class="clear"></div>
            </div>
        {/foreach}
    </fieldset>
{/foreach}


<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>