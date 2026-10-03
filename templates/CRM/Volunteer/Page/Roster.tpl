<div class="crm-vol-roster">
  <div class="crm-vol-roster-print-heading">
    <h1>{ts 1=$projectTitle domain='org.civicrm.volunteer'}Volunteer Roster for %1{/ts}</h1>
    <p>{ts 1=$endDate|crmDate domain='org.civicrm.volunteer'}Current and upcoming assignments as of %1{/ts}</p>
  </div>

  <div class="crm-vol-roster-summary">
    <div class="crm-vol-roster-metrics" aria-label="{ts escape='htmlattribute' domain='org.civicrm.volunteer'}Roster summary{/ts}">
      <span class="crm-vol-roster-metric">
        <i aria-hidden="true" class="crm-i fa-user"></i>
        {ts count=$assignmentCount plural='%count assignments' domain='org.civicrm.volunteer'}One assignment{/ts}
      </span>
      <span class="crm-vol-roster-metric">
        <i aria-hidden="true" class="crm-i fa-calendar"></i>
        {ts count=$shiftCount plural='%count shifts' domain='org.civicrm.volunteer'}One shift{/ts}
      </span>
    </div>
    <div class="crm-vol-roster-screen-actions">
      <button type="button" class="crm-vol-roster-print crm-hover-button crm-vol-roster-action">
        <i aria-hidden="true" class="crm-i fa-print"></i>
        {ts domain='org.civicrm.volunteer'}Print roster{/ts}
      </button>
    </div>
  </div>

  <div class="messages status no-popup crm-vol-roster-notice">
    <i aria-hidden="true" class="crm-i fa-info-circle"></i>
    {ts 1=$endDate|crmDate domain='org.civicrm.volunteer'}Showing current and upcoming assignments. Assignments ending before %1 are hidden.{/ts}
  </div>

  {if $sortedResults}
    <div class="crm-vol-roster-shifts">
      {foreach from=$sortedResults key=display_date item=assignments}
        <section class="panel panel-default crm-vol-roster-shift">
          <div class="panel-heading">
            <h2 class="panel-title">
              <span>{$display_date|escape}</span>
              <span class="crm-vol-roster-count">
                {ts count=$assignments.assignment_count plural='%count volunteers' domain='org.civicrm.volunteer'}One volunteer{/ts}
              </span>
            </h2>
          </div>
          <div class="panel-body">
            <table class="crm-vol-roster-table">
              <thead>
                <tr>
                  <th scope="col">{ts domain='org.civicrm.volunteer'}Volunteer{/ts}</th>
                  <th scope="col">{ts domain='org.civicrm.volunteer'}Role{/ts}</th>
                  <th scope="col">{ts domain='org.civicrm.volunteer'}Contact details{/ts}</th>
                </tr>
              </thead>
              <tbody>
                {foreach from=$assignments.values item=assignment}
                  <tr>
                    <td data-label="{ts escape='htmlattribute' domain='org.civicrm.volunteer'}Volunteer{/ts}">
                      {if $assignment.can_view_contact}
                        <a class="crm-vol-roster-name" href="{crmURL p='civicrm/contact/view' q="reset=1&cid=`$assignment.contact_id`"}" target="_blank" rel="noopener">
                          {$assignment.name|escape}
                          <span class="sr-only">{ts domain='org.civicrm.volunteer'}(opens in a new window){/ts}</span>
                        </a>
                      {else}
                        <span class="crm-vol-roster-name">{$assignment.name|escape}</span>
                      {/if}
                    </td>
                    <td data-label="{ts escape='htmlattribute' domain='org.civicrm.volunteer'}Role{/ts}">{$assignment.role_label|escape}</td>
                    <td data-label="{ts escape='htmlattribute' domain='org.civicrm.volunteer'}Contact details{/ts}">
                      <div class="crm-vol-roster-contact">
                        <div class="crm-vol-roster-contact-values">
                          {if $assignment.email}
                            <span>
                              <i aria-hidden="true" class="crm-i fa-envelope"></i>
                              {$assignment.email|escape}
                            </span>
                          {/if}
                          {if $assignment.phone}
                            <span>
                              <i aria-hidden="true" class="crm-i fa-phone"></i>
                              {$assignment.phone|escape}
                              {if $assignment.phone_ext}
                                <small>{ts 1=$assignment.phone_ext domain='org.civicrm.volunteer'}ext. %1{/ts}</small>
                              {/if}
                            </span>
                          {/if}
                          {if !$assignment.email && !$assignment.phone}
                            <span class="crm-vol-roster-muted">{ts domain='org.civicrm.volunteer'}No contact details available{/ts}</span>
                          {/if}
                        </div>
                        {if $assignment.email || $assignment.phone}
                          <div class="crm-vol-roster-contact-actions crm-vol-roster-screen-actions">
                            {if $assignment.email}
                              {assign var='emailParams' value="action=add&reset=1&atype=3&cid=`$assignment.contact_id`"}
                              <a class="crm-hover-button action-item crm-vol-roster-action" href="{crmURL p='civicrm/activity/email/add' q=$emailParams}"
                                 title="{ts escape='htmlattribute' 1=$assignment.name domain='org.civicrm.volunteer'}Send %1 an email.{/ts}">
                                <i aria-hidden="true" class="crm-i fa-envelope"></i>
                                {ts domain='org.civicrm.volunteer'}Email{/ts}
                              </a>
                            {/if}
                            {if $assignment.phone}
                              <a class="crm-hover-button action-item no-popup crm-vol-roster-action" href="tel:{$assignment.phone|escape}"
                                 title="{ts escape='htmlattribute' 1=$assignment.name domain='org.civicrm.volunteer'}Telephone %1.{/ts}">
                                <i aria-hidden="true" class="crm-i fa-phone"></i>
                                {ts domain='org.civicrm.volunteer'}Call{/ts}
                              </a>
                              <a class="crm-hover-button action-item no-popup crm-vol-roster-action" href="sms:{$assignment.phone|escape}"
                                 title="{ts escape='htmlattribute' 1=$assignment.name domain='org.civicrm.volunteer'}Send %1 an SMS message.{/ts}">
                                <i aria-hidden="true" class="crm-i fa-comment"></i>
                                {ts domain='org.civicrm.volunteer'}SMS{/ts}
                              </a>
                            {/if}
                          </div>
                        {/if}
                      </div>
                    </td>
                  </tr>
                {/foreach}
              </tbody>
            </table>
          </div>
        </section>
      {/foreach}
    </div>
  {else}
    <div class="messages status no-popup crm-vol-roster-empty">
      <i aria-hidden="true" class="crm-i fa-users"></i>
      <div>
        <strong>{ts domain='org.civicrm.volunteer'}No upcoming volunteer assignments{/ts}</strong>
        <p>{ts domain='org.civicrm.volunteer'}No volunteers are currently assigned to this project, or all assignments have ended.{/ts}</p>
      </div>
    </div>
  {/if}

  <div class="crm-vol-roster-footer crm-vol-roster-screen-actions">
    <button type="button" class="crm-vol-modal-closer crm-hover-button crm-vol-roster-action">
      <i aria-hidden="true" class="crm-i fa-times"></i>
      {ts domain='org.civicrm.volunteer'}Close{/ts}
    </button>
  </div>
</div>
