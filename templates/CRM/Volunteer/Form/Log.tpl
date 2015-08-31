{*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.4                                                |
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
    {ts domain='org.civicrm.volunteer'}Available and scheduled volunteers are listed below. Enter the time spent by each volunteer in minutes under Actual Duration and change status to Completed. Click 'Add Volunteer to record hours for volunteers not already listed below. Click Save to save your entries.{/ts}
  </div>

  <div class="crm-copy-fields crm-grid-table" id="crm-log-entry-table" data-vid="{$vid}">
    <div class="crm-grid-header">
      <div class="crm-grid-cell"></div>
      <div class="crm-grid-cell">
        {ts domain='org.civicrm.volunteer'}Contact{/ts}
        <span class="crm-marker" title="{ts domain='org.civicrm.volunteer'}This field is required.{/ts}">*</span>
      </div>
      <div class="crm-grid-cell">{ts domain='org.civicrm.volunteer'}Role{/ts}</div>
      <div class="crm-grid-cell">{ts domain='org.civicrm.volunteer'}Start Date{/ts}</div>
      <div class="crm-grid-cell">{ts domain='org.civicrm.volunteer'}Scheduled Duration{/ts}</div>
      <div class="crm-grid-cell">
        <img src="{$config->resourceBase}i/copy.png"
             alt="{ts domain='org.civicrm.volunteer'}Click to copy Actual Duration from row one to all rows.{/ts}"
             fname="actual_duration" class="action-icon"
             title="{ts domain='org.civicrm.volunteer'}Click here to copy the Actual Duration value in row one to ALL rows.{/ts}" />
        {ts}Actual Duration{/ts}
        <span class="crm-marker" title="{ts domain='org.civicrm.volunteer'}This field is required.{/ts}">*</span>
      </div>
      <div class="crm-grid-cell"><img src="{$config->resourceBase}i/copy.png"
                                      alt="{ts domain='org.civicrm.volunteer'}Click to copy Volunteer Status from row one to all rows.{/ts}"
                                      fname="volunteer_status" class="action-icon"
                                      title="{ts domain='org.civicrm.volunteer'}Click here to copy the Volunteer Status value in row one to ALL rows.{/ts}"/>{ts}Status{/ts}
      </div>

    </div>

    {section name='i' start=1 loop=$rowCount}
      {assign var='rowNumber' value=$smarty.section.i.index}
      <div
        class="{cycle values="odd-row,even-row"} selector-rows {if $rowNumber > $showVolunteerRow && $rowNumber != 1} hiddenElement {else} crm-grid-row {/if}"
        entity_id="{$rowNumber}">
        <div class="compressed crm-grid-cell volunteer-commendation"><span></span></div>
        <div class="compressed crm-grid-cell">
          {$form.field.$rowNumber.contact_id.html}
        </div>
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
  <a href="#" id="addMoreVolunteer" class="button">
    <span><div class="icon ui-icon-plus"></div>{ts domain='org.civicrm.volunteer'}Add Volunteer{/ts}</span>
  </a>

  <div class="crm-submit-buttons">{if $fields}{$form._qf_Batch_refresh.html}{/if} &nbsp; {$form.buttons.html}</div>
</div>

{*include batch copy js js file*}
{include file="CRM/common/batchCopy.tpl"}

{literal}
<script type="text/javascript">
  CRM.$(function($) {
    $('#addMoreVolunteer').click(function(e){
      $('div.hiddenElement:first').show().removeClass('hiddenElement').addClass('crm-grid-row').css('display', 'table-row');
      e.preventDefault();
    });
  });
</script>
{/literal}

<!-- Commendation libraries -->
<link rel="stylesheet" type="text/css" href="{$extResourceURL}/css/commendation.css" />
<script type="text/javascript" src="{$extResourceURL}/js/commendation.js"></script>