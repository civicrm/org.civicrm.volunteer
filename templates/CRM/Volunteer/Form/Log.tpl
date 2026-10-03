{*
+--------------------------------------------------------------------+
| Copyright CiviCRM LLC. Licensed under AGPLv3.                      |
+--------------------------------------------------------------------+
*}
<div class="volunteer-log form-item crm-vol-log-hours">
  <div class="crm-vol-log-context panel panel-default">
    <div>
      <span class="crm-vol-eyebrow">{ts domain='org.civicrm.volunteer'}Volunteer project{/ts}</span>
      <h2>{$projectTitle|escape}</h2>
      {if $projectDateRange}
        <p><i aria-hidden="true" class="crm-i fa-calendar"></i> {$projectDateRange|escape}</p>
      {/if}
    </div>
    <div class="crm-vol-log-row-summary" aria-live="polite">
      <strong class="crm-vol-log-visible-count">0</strong>
      <span>{ts domain='org.civicrm.volunteer'}entries shown{/ts}</span>
    </div>
  </div>

  <div class="crm-vol-dialog-intro crm-vol-log-intro">
    <i aria-hidden="true" class="crm-i fa-clock-o"></i>
    <div>
      <strong>{ts domain='org.civicrm.volunteer'}Record completed volunteer time{/ts}</strong>
      <span>{ts domain='org.civicrm.volunteer'}Enter each volunteer's actual duration in minutes and confirm their status. Use “Add another volunteer” for someone not already listed.{/ts}</span>
    </div>
  </div>

  <div class="crm-vol-log-toolbar" aria-label="{ts escape='htmlattribute' domain='org.civicrm.volunteer'}Batch entry tools{/ts}">
    <span>{ts domain='org.civicrm.volunteer'}Apply the first row to all entries:{/ts}</span>
    <button type="button" fname="actual_duration" class="action-icon crm-hover-button crm-vol-batch-action">
      <i aria-hidden="true" class="crm-i fa-copy"></i>
      {ts domain='org.civicrm.volunteer'}Copy actual duration{/ts}
    </button>
    <button type="button" fname="volunteer_status" class="action-icon crm-hover-button crm-vol-batch-action">
      <i aria-hidden="true" class="crm-i fa-copy"></i>
      {ts domain='org.civicrm.volunteer'}Copy status{/ts}
    </button>
  </div>

  <div class="crm-copy-fields crm-grid-table crm-vol-log-table" id="crm-log-entry-table" data-vid="{$vid}" role="table" aria-label="{ts escape='htmlattribute' domain='org.civicrm.volunteer'}Volunteer hour entries{/ts}">
    <div class="crm-grid-header crm-vol-log-header" role="row">
      <div class="crm-grid-cell" role="columnheader"><span class="sr-only">{ts domain='org.civicrm.volunteer'}Commendation{/ts}</span></div>
      <div class="crm-grid-cell" role="columnheader">
        {ts domain='org.civicrm.volunteer'}Contact{/ts}
        <span class="crm-marker" title="{ts escape='htmlattribute' domain='org.civicrm.volunteer'}This field is required.{/ts}">*</span>
      </div>
      <div class="crm-grid-cell" role="columnheader">{ts domain='org.civicrm.volunteer'}Role{/ts}</div>
      <div class="crm-grid-cell" role="columnheader">{ts domain='org.civicrm.volunteer'}Start date{/ts}</div>
      <div class="crm-grid-cell" role="columnheader">{ts domain='org.civicrm.volunteer'}Scheduled{/ts} <small>({ts domain='org.civicrm.volunteer'}min{/ts})</small></div>
      <div class="crm-grid-cell" role="columnheader">
        {ts domain='org.civicrm.volunteer'}Actual{/ts} <small>({ts domain='org.civicrm.volunteer'}min{/ts})</small>
        <span class="crm-marker" title="{ts escape='htmlattribute' domain='org.civicrm.volunteer'}This field is required.{/ts}">*</span>
      </div>
      <div class="crm-grid-cell" role="columnheader">{ts domain='org.civicrm.volunteer'}Status{/ts}</div>
      <div class="crm-grid-cell" role="columnheader"><span class="sr-only">{ts domain='org.civicrm.volunteer'}Actions{/ts}</span></div>
    </div>

    {section name='i' start=1 loop=$rowCount}
      {assign var='rowNumber' value=$smarty.section.i.index}
      <div
        class="{cycle values="odd-row,even-row"} selector-rows crm-vol-log-entry {if $rowNumber > $showVolunteerRow && $rowNumber != 1}hiddenElement{else}crm-grid-row{/if}"
        entity_id="{$rowNumber}"
        data-row-number="{$rowNumber}"
        role="row">
        <div class="compressed crm-grid-cell crm-vol-log-commendation" data-label="{ts escape='htmlattribute' domain='org.civicrm.volunteer'}Commendation{/ts}" role="cell">
          <button type="button" class="volunteer-commendation" aria-pressed="false" title="{ts escape='htmlattribute' domain='org.civicrm.volunteer'}Add or edit a commendation{/ts}">
            <span aria-hidden="true"></span>
            <span class="sr-only">{ts domain='org.civicrm.volunteer'}Commend volunteer{/ts}</span>
          </button>
        </div>
        <div class="compressed crm-grid-cell crm-vol-log-contact" data-label="{ts escape='htmlattribute' domain='org.civicrm.volunteer'}Contact{/ts}" role="cell">
          <span class="sr-only">{ts 1=$rowNumber domain='org.civicrm.volunteer'}Contact for entry %1{/ts}</span>
          {$form.field.$rowNumber.contact_id.html}
        </div>
        <div class="compressed crm-grid-cell" data-label="{ts escape='htmlattribute' domain='org.civicrm.volunteer'}Role{/ts}" role="cell">
          <span class="sr-only">{ts 1=$rowNumber domain='org.civicrm.volunteer'}Role for entry %1{/ts}</span>
          {$form.field.$rowNumber.volunteer_role.html}
        </div>
        <div class="compressed crm-grid-cell" data-label="{ts escape='htmlattribute' domain='org.civicrm.volunteer'}Start date{/ts}" role="cell">
          <span class="sr-only">{ts 1=$rowNumber domain='org.civicrm.volunteer'}Start date for entry %1{/ts}</span>
          {$form.field.$rowNumber.start_date.html}
        </div>
        <div class="compressed crm-grid-cell crm-vol-log-scheduled" data-label="{ts escape='htmlattribute' domain='org.civicrm.volunteer'}Scheduled minutes{/ts}" role="cell">
          <span class="sr-only">{ts 1=$rowNumber domain='org.civicrm.volunteer'}Scheduled minutes for entry %1{/ts}</span>
          <span class="crm-vol-input-suffix">
            {$form.field.$rowNumber.scheduled_duration.html}
            <span>{ts domain='org.civicrm.volunteer'}min{/ts}</span>
          </span>
        </div>
        <div class="compressed crm-grid-cell crm-vol-log-actual" data-label="{ts escape='htmlattribute' domain='org.civicrm.volunteer'}Actual minutes{/ts}" role="cell">
          <span class="sr-only">{ts 1=$rowNumber domain='org.civicrm.volunteer'}Actual minutes for entry %1{/ts}</span>
          <span class="crm-vol-input-suffix">
            {$form.field.$rowNumber.actual_duration.html}
            <span>{ts domain='org.civicrm.volunteer'}min{/ts}</span>
          </span>
        </div>
        <div class="compressed crm-grid-cell" data-label="{ts escape='htmlattribute' domain='org.civicrm.volunteer'}Status{/ts}" role="cell">
          <span class="sr-only">{ts 1=$rowNumber domain='org.civicrm.volunteer'}Status for entry %1{/ts}</span>
          {$form.field.$rowNumber.volunteer_status.html}
        </div>
        <div class="crm-grid-cell crm-vol-log-entry-actions" data-label="{ts escape='htmlattribute' domain='org.civicrm.volunteer'}Actions{/ts}" role="cell">
          <button type="button" class="crm-hover-button crm-vol-remove-row">
            <i aria-hidden="true" class="crm-i fa-times"></i>
            {ts domain='org.civicrm.volunteer'}Remove{/ts}
          </button>
        </div>
      </div>
    {/section}
  </div>

  <button type="button" id="addMoreVolunteer" class="crm-hover-button crm-vol-add-row">
    <i aria-hidden="true" class="crm-i fa-plus"></i>
    {ts domain='org.civicrm.volunteer'}Add another volunteer{/ts}
  </button>

  <div class="crm-vol-log-unsaved" role="status">
    <i aria-hidden="true" class="crm-i fa-pencil"></i>
    {ts domain='org.civicrm.volunteer'}You have unsaved hour entries.{/ts}
  </div>
  <div class="crm-submit-buttons crm-vol-log-submit">{$form.buttons.html}</div>
</div>

{include file="CRM/common/batchCopy.tpl"}
