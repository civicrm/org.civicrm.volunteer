<div class="crm-block crm-volunteer-signup-form-block crm-vol-signup-page">

  <header class="crm-vol-signup-hero">
    <ol class="crm-vol-signup-steps" aria-label="{ts domain='org.civicrm.volunteer'}Volunteer signup progress{/ts}">
      <li class="is-complete"><a href="{$backToShiftsUrl}"><span>1</span>{ts domain='org.civicrm.volunteer'}Pick shifts{/ts}</a></li>
      <li class="is-current" aria-current="step"><span>2</span>{ts domain='org.civicrm.volunteer'}Your details{/ts}</li>
    </ol>
    <h1>{ts domain='org.civicrm.volunteer'}Almost done — tell us who you are{/ts}</h1>
  </header>

  <div class="crm-vol-public-content">
    <div class="crm-vol-signup-layout">

      <div class="crm-vol-signup-main">
        <section class="panel panel-default crm-volunteer-signup-profiles{if $customProfiles|@count eq 1} has-single-profile{/if}" aria-labelledby="crm-vol-signup-profile-heading">
          <div class="panel-heading">
            <h2 class="panel-title" id="crm-vol-signup-profile-heading">{ts domain='org.civicrm.volunteer'}Your details{/ts}</h2>
            <p class="crm-vol-profile-help">{ts domain='org.civicrm.volunteer'}We use these to confirm your shift and get in touch if plans change.{/ts}</p>
          </div>
          <div class="panel-body">
            {foreach from=$customProfiles key=ufID item=ufFields }
              {include file="CRM/UF/Form/Block.tpl" fields=$ufFields}
            {/foreach}
          </div>
        </section>

        {if $allowAdditionalVolunteers}
          <fieldset class="panel panel-default crm-volunteer-additional-volunteers-section">
            <legend class="crm-vol-visually-hidden">{ts domain='org.civicrm.volunteer'}Additional volunteers{/ts}</legend>
            <div class="panel-body">
              <label class="crm-vol-bringing-toggle">
                {$form.bringingAdditionalVolunteers.html}
                <span class="crm-vol-bringing-copy">
                  {* Reuses the element's own label so the string is translated once. *}
                  <strong>{$form.bringingAdditionalVolunteers.label}</strong>
                  <small>{ts domain='org.civicrm.volunteer'}Add their names and we will count them against the same shift.{/ts}</small>
                </span>
              </label>

              <div class="crm-vol-additional-people" id="{$additionalPeopleId}"{if !$bringingAdditionalVolunteers} hidden{/if}>
                <p class="description">{ts domain='org.civicrm.volunteer'}Tell us how many people are joining you, then provide information for each person.{/ts}</p>
                <div class="crm-section crm-vol-additional-quantity">
                  <div class="label">{$form.additionalVolunteerQuantity.label}</div>
                  <div class="content">{$form.additionalVolunteerQuantity.html}</div>
                  <div class="clear"></div>
                </div>

                <div class="crm-volunteer-additional-volunteers" id="additionalVolunteers">
                  {if $additionalVolunteerProfiles}
                    {foreach from=$additionalVolunteerProfiles item=additionalVolunteer }
                      <div class="additional-volunteer-profile">
                        {foreach from=$additionalVolunteer.profiles key=ufID item=ufFields }
                          {include file="CRM/UF/Form/Block.tpl" fields=$ufFields prefix=$additionalVolunteer.prefix}
                        {/foreach}
                        <div class="clear"></div>
                      </div>
                    {/foreach}
                  {/if}
                </div>
              </div>
            </div>
          </fieldset>
        {/if}

        <div class="crm-vol-signup-actions">
          <div class="crm-vol-signup-primary-action">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
          <a href="{$backToShiftsUrl}" class="crm-vol-public-secondary crm-vol-back-to-shifts">{ts domain='org.civicrm.volunteer'}Back to shifts{/ts}</a>
        </div>
        <p class="crm-vol-signup-confirmation-help">{ts domain='org.civicrm.volunteer'}You will get a confirmation email with the date, time and location.{/ts}</p>
      </div>

      <aside class="crm-vol-signup-commitments" aria-labelledby="crm-vol-signup-commitments-heading">
        <header>
          <h2 id="crm-vol-signup-commitments-heading">{ts domain='org.civicrm.volunteer'}You are signing up for{/ts}</h2>
          <span>{ts domain='org.civicrm.volunteer' 1=$volunteerNeeds|@count}%1 picked{/ts}</span>
        </header>

        <div class="crm-vol-commitment-groups">
          {foreach from=$commitmentGroups item=group}
            <section class="crm-vol-commitment-project" data-project-id="{$group.project_id}">
              <ul class="crm-vol-commitment-list">
                {foreach from=$group.commitments item=commitment}
                  <li class="crm-vol-commitment crm-vol-commitment--{$commitment.schedule_type}">
                    <div class="crm-vol-commitment-role">
                      <strong>{$commitment.role_label}</strong>
                      {if $commitment.role_description}
                        <button type="button" class="crm-hover-button crm-vol-icon-button crm-vol-description" title="{ts domain='org.civicrm.volunteer'}View role description{/ts}" aria-label="{ts domain='org.civicrm.volunteer'}View role description{/ts}">
                          <i class="crm-i fa-comment" role="img" aria-hidden="true"></i>
                          <span class="vol-role-description-wrapper">{$commitment.role_description}</span>
                        </button>
                      {/if}
                    </div>
                    {if $commitment.schedule_type eq 'flexible'}
                      <p class="crm-vol-flexible-summary">{ts domain='org.civicrm.volunteer'}No specific shift — the coordinator will be in touch about times that suit you.{/ts}</p>
                      <a class="crm-vol-pick-shift-instead" href="{$backToShiftsUrl}">{ts domain='org.civicrm.volunteer'}Pick a shift instead{/ts}</a>
                    {else}
                      <time>{$commitment.schedule_summary}</time>
                    {/if}
                  </li>
                {/foreach}
              </ul>
              <footer class="crm-vol-commitment-meta">
                {if $group.organizers}{ts domain='org.civicrm.volunteer' 1=$group.organizers}Organized by %1{/ts} &middot; {/if}{$group.title}
                {if $group.description}
                  <button type="button" class="crm-hover-button crm-vol-icon-button crm-vol-description" title="{ts domain='org.civicrm.volunteer'}View project description{/ts}" aria-label="{ts domain='org.civicrm.volunteer'}View project description{/ts}">
                    <i class="crm-i fa-comment" role="img" aria-hidden="true"></i>
                    <span class="vol-project-description-wrapper">{$group.description}</span>
                  </button>
                {/if}
              </footer>
            </section>
          {/foreach}
        </div>
      </aside>

    </div>
  </div>
</div>

{if $allowAdditionalVolunteers}
</form>
<form>
  <div class="crm-volunteer-additional-volunteers-template">
    <div class="additional-volunteer-profile">
      {foreach from=$additionalVolunteersTemplate key=ufID item=ufFields }
        {include file="CRM/UF/Form/Block.tpl" fields=$ufFields prefix='additionalVolunteersTemplate'}
      {/foreach}
      <div class="clear"></div>
    </div>
  </div>
{/if}

{include file="CRM/common/notifications.tpl" location="bottom"}
