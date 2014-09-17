
<div id="multiple_signup_profiles_{$profileCount}_wrapper" class='crm-profile-selector-container'>
  <div> {$form.custom_signup_profiles.$profileItem.html}
    &nbsp;<span class='profile_bottom_link_remove'>
      <a href="#" class="crm-hover-button crm-button-rem-profile">
      <span class="icon ui-icon-trash"></span>{ts}remove profile{/ts}</a></span>
    {if !isset($profileLast) || $profileLast }
      &nbsp;&nbsp;
      <span class='profile_bottom_link'><a href="#" class="crm-hover-button crm-button-add-profile"><span
            class="icon ui-icon-plus"></span>{ts}add another profile{/ts}</a></span>
    {/if}
    <br/><span class="profile-links"></span>
  </div>
</div>