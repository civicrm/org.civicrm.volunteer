{*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2013                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*}
<div class="volunteer-log form-item">
<div id="help">
  {ts}Click Save below when you've logged all the volunteers for the event.{/ts}
</div>

<div class="crm-copy-fields crm-grid-table" id="crm-batch-entry-table">
      <div class="crm-grid-header">
        <div class="crm-grid-cell">&nbsp;</div>
        <div class="crm-grid-cell">{ts}Contact{/ts}</div>
        <div class="crm-grid-cell">{ts}Role{/ts}</div>
	 <div class="crm-grid-cell">{ts}Start Time{/ts}</div>
	 <div class="crm-grid-cell">{ts}Scheduled Duration{/ts}</div>
	 <div class="crm-grid-cell">{ts}Actual Duration{/ts}</div>
	 <div class="crm-grid-cell"><img src="{$config->resourceBase}i/copy.png" alt="{ts 1=$field.title}Click to copy %1 from row one to all rows.{/ts}" fname="volunteer_status" class="action-icon" title="{ts}Click here to copy the value in row one to ALL rows.{/ts}" />{ts}Status{/ts}</div>

      </div>
  
  {section name='i' start=1 loop=$rowCount}
    {assign var='rowNumber' value=$smarty.section.i.index}
    <div class="{cycle values="odd-row,even-row"} selector-rows crm-grid-row" entity_id="{$rowNumber}">
        <div class="compressed crm-grid-cell"><span class="log-edit"></span></div>
        {* contact select/create option*}
        <div class="compressed crm-grid-cell">
            {include file="CRM/Contact/Form/NewContact.tpl" blockNo = $rowNumber noLabel=true prefix="primary_" newContactCallback="updateContactInfo($rowNumber, 'primary_')"}
        </div>
	 <div class="compressed crm-grid-cell">
          {$form.volunteer_role.$rowNumber.html}
	   </div>

	   <div class="compressed crm-grid-cell">
	     <span class="crm-batch-start_time-{$rowNumber}">{include file="CRM/common/jcalendar.tpl" elementName=start_time  elementIndex=$rowNumber batchUpdate=1}</span></div>
	 <div class="compressed crm-grid-cell">
          {$form.scheduled_duration.$rowNumber.html}
        </div>
	 <div class="compressed crm-grid-cell">
          {$form.actual_duration.$rowNumber.html}
        </div>
	 <div class="compressed crm-grid-cell">
	   {$form.volunteer_status.$rowNumber.html}
	   </div>

    </div>
    {/section}
</div>
<div class="crm-submit-buttons">{if $fields}{$form._qf_Batch_refresh.html}{/if} &nbsp; {$form.buttons.html}</div>
</div>

{*include batch copy js js file*}
{include file="CRM/common/batchCopy.tpl"}
