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

{* Contains js templates for backbone-based volunteer needs sub-application *}

<script type="text/template" id="crm-vol-define-layout-tpl">
	<div id="help">
		{ts}Use this form to define the number of volunteers needed for each role and time slot. The first slot listed is a default 'flexible' slot (for volunteers who are available at any time for any role).{/ts} {help id="volunteer-define" file="CRM/Volunteer/Form/Manage/Define.hlp"}
	</div>
  <form>
	<div class="dataTables_wrapper">
	<table id="crm-vol-define-needs-dialog" class="display">
    <thead><tr>
			<th class="sorting" id="role_id">{ts}Role{/ts}</th>
                        <th class="sorting" id="quantity">{ts}Volunteers<br />Needed{/ts}</th>
			<th class="sorting" id="start_date">{ts}Start Date/Time{/ts}</th>
			<th class="sorting" id="duration">{ts}Scheduled<br />(minutes){/ts}</th>
			<th class="sorting" id="visibility">{ts}Public?{/ts}</th>
			<th>Enabled?</th>
    </tr></thead>
    <tbody id="crm-vol-define-needs-region"><tr><td colspan="6"><div class="crm-loading-element">{ts}Loading{/ts}...</div></td></tr></tbody>
  </table>
	</div>
	<div class="crm-submit-buttons">
  	<a class="button" id="addNewNeed" href="#"><span><div class="icon add-icon"></div>Add a Need</span></a>
	</div>
  </form>
</script>

<script type="text/template" id="crm-vol-define-new-need-tpl">
    <td>
      {literal}
        <%= RenderUtil.select({
                name: 'role_id',
                options: _.extend(pseudoConstant.volunteer_role, {0:''}),
                selected: role_id
        }) %>
      {/literal}
		</td>
    <td><input type="text" name="quantity" value="<%= quantity %>" size="4"></td>
    <td>
      <input type="text" name="display_start_date" value="<%= display_start_date %>" size="20">
      <input type="text" name="display_start_time" value="<%= display_start_time %>" size="10">
    </td>
    <td><input type="text" name="duration" value="<%= duration %>" size="6"></td>
    <td><input type="checkbox" name="visibility_id" data-stored="<%= visibility_id %>"></td>
    <td><input type="checkbox" name="is_active" value="1" data-stored="<%= is_active %>"><a href="#" class="crm-vol-del" title="{ts}Remove{/ts}"><img src="{$config->resourceBase}i/close.png" alt="{ts}Remove{/ts}"/></a></td>
  </script>
