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

{* Contains js templates for backbone-based volunteer assignment sub-application *}

<script type="text/template" id="crm-vol-assign-layout-tpl">
  <div id="crm-vol-assign-flexible-region" class="crm-form-block">Loading...</div>
  <div id="crm-vol-assign-scheduled-region" class="crm-form-block">Loading...</div>
</script>

<script type="text/template" id="crm-vol-assignment-tpl">
  <%= display_name %>
</script>

<script type="text/template" id="crm-vol-scheduled-tpl">
  <h3><%= pseudoConstant.volunteer_role[role_id] %>: <%= display_time %></h3>
  <div class="crm-vol-assignment-list"></div>
</script>

<script type="text/template" id="crm-vol-flexible-tpl">
  <h3>{ts}Available Volunteers{/ts}</h3>
  <div class="crm-vol-assignment-list"></div>
  <h4>Add Volunteer</h4>
  <input type="text" name="add-volunteer" class="crm-add-volunteer" placeholder="{ts escape='js'}Select Contact...{/ts}"/>
  <button class="crm-add-vol-contact">{ts}Add{/ts}</button>
  <div>
    OR <select class="crm-vol-create-contact-select">
      <option value="">{ts}- create new contact -{/ts}</option>
      {crmAPI var='UFGroup' entity='UFGroup' action='get' is_active=1 is_reserved=1}
      {foreach from=$UFGroup.values item=profile}
        {if $profile.name eq 'new_individual' or $profile.name eq 'new_organization' or $profile.name eq 'new_household'}
          <option value="{$profile.id}">{$profile.title}</option>
        {/if}
      {/foreach}
    </select>
  </div>
</script>
