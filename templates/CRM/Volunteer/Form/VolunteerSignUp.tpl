<div class="crm-block crm-volunteer-signup-form-block">

  <div class="help">
    <p>
      {ts domain='org.civicrm.volunteer'}Thank you for being a volunteer! You are registering for the following volunteer commitments:{/ts}
    </p>
  </div>

  <p class="description">
    {ts domain='org.civicrm.volunteer'}For additional project or role detail, click the corresponding detail icon in the table below.{/ts}
    <span class="icon ui-icon-comment"></span>
  </p>

  <div class="crm-volunteer-signup-summary">
    <table>
      <tr>
        <th>{ts domain='org.civicrm.volunteer'}Project Title{/ts}</th>
        <th>{ts domain='org.civicrm.volunteer'}Project Beneficiaries{/ts}</th>
        <th>{ts domain='org.civicrm.volunteer'}Role{/ts}</th>
        <th>{ts domain='org.civicrm.volunteer'}Date and Time{/ts}</th>
      </tr>
      {foreach from=$volunteerNeeds key=key item=volunteerNeed}
        <tr>
          <td>
            {$volunteerNeed.project.title}
            {if $volunteerNeed.project.description}
              <span class="icon ui-icon-comment crm-vol-description">
                <div class="vol-project-description-wrapper">{$volunteerNeed.project.description}</div>
              </span>
            {/if}
          </td>
          <td>{$volunteerNeed.project.beneficiaries}</td>
          <td>
            {$volunteerNeed.role_label}
            {if $volunteerNeed.role_description}
              <span class="icon ui-icon-comment crm-vol-description">{$volunteerNeed.role_description}</span>
            {/if}
          </td>
          <td>{$volunteerNeed.display_time}</td>
        </tr>
      {/foreach}
    </table>
  </div>

  <div class="help">
    <p>
      {ts domain='org.civicrm.volunteer'}Please provide the following information and submit the form to complete your registration.{/ts}
    </p>
  </div>

  <div class="crm-volunteer-signup-profiles">
    {foreach from=$customProfiles key=ufID item=ufFields }
      {include file="CRM/UF/Form/Block.tpl" fields=$ufFields}
    {/foreach}
  </div>

  {if $allowAdditionalVolunteers}

    <fieldset class="crm-volunteer-additional-volunteers-section">
      <legend>{ts domain='org.civicrm.volunteer'}Additional Volunteers{/ts}</legend>
      <div class="crm-section">
        <div class="label">{$form.additionalVolunteerQuantity.label}</div>
        <div class="content">{$form.additionalVolunteerQuantity.html}</div>
        <div class="clear"></div>
      </div>

      <div class="crm-volunteer-additional-volunteers" id="additionalVolunteers">
        {if $additionalVolunteerProfiles}
          {foreach from=$additionalVolunteerProfiles item=additionalVolunteer }
            <div class='additional-volunteer-profile'>
              {foreach from=$additionalVolunteer.profiles key=ufID item=ufFields }
                {include file="CRM/UF/Form/Block.tpl" fields=$ufFields prefix=$additionalVolunteer.prefix}
              {/foreach}
              <div class="clear"></div>
            </div>
          {/foreach}
        {/if}
      </div>
    </fieldset>
  {/if}

  <div>
    {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>

{if $allowAdditionalVolunteers}
</form>
<form>
  <div class="crm-volunteer-additional-volunteers-template">
    <div class='additional-volunteer-profile'>
      {foreach from=$additionalVolunteersTemplate key=ufID item=ufFields }
        {include file="CRM/UF/Form/Block.tpl" fields=$ufFields prefix='additionalVolunteersTemplate'}
      {/foreach}
      <div class="clear"></div>
    </div>
  </div>
{/if}

{include file="CRM/common/notifications.tpl" location="bottom"}
