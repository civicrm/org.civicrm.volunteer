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

  <div class="crm-copy-fields crm-grid-table" id="crm-log-entry-table">
    <div class="crm-grid-header">
      <div class="crm-grid-cell">&nbsp;</div>
      <div class="crm-grid-cell">{ts}Contact{/ts}</div>
      <div class="crm-grid-cell">{ts}Role{/ts}</div>
      <div class="crm-grid-cell">{ts}Start Time{/ts}</div>
      <div class="crm-grid-cell">{ts}Scheduled Duration{/ts}</div>
      <div class="crm-grid-cell"><img src="{$config->resourceBase}i/copy.png"
                                      alt="{ts}Click to copy Actual Duration from row one to all rows.{/ts}"
                                      fname="actual_duration" class="action-icon"
                                      title="{ts}Click here to copy the Actual Duration value in row one to ALL rows.{/ts}"/>{ts}Actual Duration{/ts}
      </div>
      <div class="crm-grid-cell"><img src="{$config->resourceBase}i/copy.png"
                                      alt="{ts}Click to copy Volunteer Status from row one to all rows.{/ts}"
                                      fname="volunteer_status" class="action-icon"
                                      title="{ts}Click here to copy the Volunteer Status value in row one to ALL rows.{/ts}"/>{ts}Status{/ts}
      </div>

    </div>

    {section name='i' start=1 loop=$rowCount}
      {assign var='rowNumber' value=$smarty.section.i.index}
      <div
        class="{cycle values="odd-row,even-row"} selector-rows {if $rowNumber > $showVolunteerRow} hiddenElement {else} crm-grid-row {/if}"
        entity_id="{$rowNumber}">
        <div class="compressed crm-grid-cell"><span class="log-edit"></span></div>
        {if $rowNumber > $showVolunteerRow}
        {* contact select/create option*}
          <div class="compressed crm-grid-cell">
            {include file="CRM/Contact/Form/NewContact.tpl" blockNo = $rowNumber noLabel=true prefix="primary_" newContactCallback="updateContactInfo($rowNumber, 'primary_')"}
          </div>
        {else}
          <div class="compressed crm-grid-cell">
            {$form.primary_contact.$rowNumber.html}
          </div>
        {/if}
        <div class="compressed crm-grid-cell">
          {$form.field.$rowNumber.volunteer_role.html}
        </div>

        {if $rowNumber > $showVolunteerRow}
          <div class="compressed crm-grid-cell">
          <span
            class="crm-log-start_date-{$rowNumber}">{include file="CRM/common/jcalendar.tpl" elementName=start_date  elementIndex=$rowNumber batchUpdate=1}</span>
          </div>
        {else}
          <div class="compressed crm-grid-cell">
            {$form.field.$rowNumber.start_date.html}
          </div>
        {/if}
        <div class="compressed crm-grid-cell">
          {$form.field.$rowNumber.scheduled_duration.html}
        </div>
        <div class="compressed crm-grid-cell">
          {$form.field.$rowNumber.actual_duration.html}
        </div>
        <div class="compressed crm-grid-cell">
          {$form.field.$rowNumber.volunteer_status.html}
        </div>

      </div>
    {/section}
  </div>
  <div class="crm-submit-buttons">
    <a href="#" id="addMoreVolunteer" class="button"><span><div
          class="icon add-icon"></div>{ts}Add Volunteer{/ts}</span></a>
  </div>

  <div class="crm-submit-buttons">{if $fields}{$form._qf_Batch_refresh.html}{/if} &nbsp; {$form.buttons.html}</div>
</div>

{*include batch copy js js file*}
{include file="CRM/common/batchCopy.tpl"}
