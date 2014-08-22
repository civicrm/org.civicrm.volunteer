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
{strip}
{* Contains js templates for backbone-based volunteer needs sub-application *}

<script type="text/template" id="crm-vol-define-layout-tpl">
  <div id="help">
    {ts domain='org.civicrm.volunteer'}Use this form to specify the number of volunteers needed for each role
    and time slot. If no needs are specified, volunteers will be considered to
    be generally available.{/ts}
    {help id="volunteer-define" file="CRM/Volunteer/Form/Manage/Define.hlp" isJoomlaPermsHackNeeded=`$isJoomlaPermsHackNeeded`}
  </div>
  <form class="crm-block crm-form-block crm-event-manage-volunteer-form-block">
    <div id="crm-vol-define-scheduled-needs-region">
      <div class="crm-loading-element">{ts domain='org.civicrm.volunteer'}Loading{/ts}...</div>
    </div>
    <div id="crm-vol-define-flexible-needs-region"></div>
    <div class="crm-submit-buttons">
      <a class="button" id="crm-vol-define-done" href="#"><span>{ts domain='org.civicrm.volunteer'}Done{/ts}</span></a>
    </div>
  </form>
</script>

<script type="text/template" id="crm-vol-define-table-tpl">
  <table id="crm-vol-define-needs-table">
    <thead><tr>
        <th id="role_id">{ts domain='org.civicrm.volunteer'}Role{/ts}</th>
        <th id="quantity">{ts domain='org.civicrm.volunteer'}Volunteers Needed{/ts}</th>
        <th id="start_date">{ts domain='org.civicrm.volunteer'}Start Date/Time{/ts}</th>
        <th id="duration">{ts domain='org.civicrm.volunteer'}Minutes{/ts}</th>
        <th id="visibility">{ts domain='org.civicrm.volunteer'}Public?{/ts}</th>
        <th>Enabled?</th>
        <th></th>
      </tr></thead>
    <tbody></tbody>
  </table>
</script>

<script type="text/template" id="crm-vol-define-scheduled-need-tpl">
  <td>
    {literal}
      <%= RenderUtil.select({
      name: 'role_id',
      options: pseudoConstant.volunteer_role,
      selected: role_id
      }) %>
    {/literal}
  </td>
  <td><input type="text" class="crm-form-text" name="quantity" value="<%= quantity %>" size="4"></td>
  <td>
    <input type="text" class="crm-form-text dateplugin" name="display_start_date"  value="<%= display_start_date %>" size="20">
    <input type="text" class="crm-form-text" name="display_start_time" size="10">
  </td>
  <td><input type="text" class="crm-form-text" name="duration" value="<%= duration %>" size="6"></td>
  <td><input type="checkbox" name="visibility_id" value="<%= visibilityValue %>"></td>
  <td><input type="checkbox" name="is_active" value="1"></td>
  <td><a href="#" class="crm-vol-del" title="{ts domain='org.civicrm.volunteer'}Delete{/ts}"><img src="{$config->resourceBase}i/close.png" alt="{ts}Delete{/ts}"/></a></td>
</script>

<script type="text/template" id="crm-vol-define-flexible-need-tpl">
  <input type="checkbox" name="visibility_id" id="crm-vol-visibility-id" value="<%= visibilityValue %>">
  <label for="crm-vol-visibility-id">Allow users to sign up without specifying a shift.</label>
</script>

<script type="text/template" id="crm-vol-define-add-row-tpl">
  <tr id="crm-vol-define-add-row">
    <td colspan="7">
      <select class="crm-form-select crm-action-menu action-icon-plus" id="crm-vol-define-add-need" style="width: 20em;">
        <option value="">{ts domain='org.civicrm.volunteer'}Create new{/ts}</option>
        {crmAPI var='result' entity='VolunteerNeed' action='getoptions' field='role_id' sequential=0}
        {foreach from=$result.values item=VolunteerNeed key=id}
          <option value="{$id}">{$VolunteerNeed}</option>
        {/foreach}
      </select>
    </td>
  </tr>
</script>
{/strip}
