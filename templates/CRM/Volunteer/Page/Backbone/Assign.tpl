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
{* Contains js templates for backbone-based volunteer assignment sub-application *}

<script type="text/template" id="crm-vol-assign-layout-tpl">
  <div id="crm-vol-assign-flexible-region"><div class="crm-loading-element">{ts domain='org.civicrm.volunteer'}Loading{/ts}...</div></div>
  <div id="crm-vol-assign-scheduled-region"></div>
</script>

<script type="text/template" id="crm-vol-scheduled-assignment-tpl">
  <td class="crm-vol-name">
    <span class="icon crm-vol-drag"></span>
    <a target="_blank" href="<%= contactUrl(contact_id) %>"><%= display_name %></a>
    {literal}<%if (details){%><a href="#" class="icon crm-vol-info"> </a><%}%>{/literal}
    <div class="crm-vol-menu"><a class="crm-vol-menu-button" href="#" title="{ts domain='org.civicrm.volunteer'}Actions{/ts}"><span></span></a></div>
  </td>
  <td><%= email %></td>
  <td><%= phone %></td>
</script>

<script type="text/template" id="crm-vol-flexible-assignment-tpl">
  <td class="crm-vol-name">
    <span class="icon crm-vol-drag"></span>
    <a target="_blank" href="<%= contactUrl(contact_id) %>"><%= display_name %></a>
    {literal}<%if (details){%><a href="#" class="icon crm-vol-info"> </a><%}%>{/literal}
    <div class="crm-vol-menu"><a class="crm-vol-menu-button" href="#" title="{ts domain='org.civicrm.volunteer'}Actions{/ts}"><span></span></a></div>
  </td>
</script>

<script type="text/template" id="crm-vol-scheduled-tpl">
  <div class="crm-vol-need-ctrls">
    {* This functionality is (temporarily?) slipped.
    <div class="crm-vol-action crm-vol-filter">
      <span class="crm-vol-icon-label">{ts domain='org.civicrm.volunteer'}Filter{/ts}</span>
      <div class="crm-vol-circle"><div class="icon"></div></div>
    </div>
    *}
    <div class="crm-vol-action crm-vol-search">
      <span class="crm-vol-icon-label">{ts domain='org.civicrm.volunteer'}Search{/ts}</span>
      <div class="crm-vol-circle"><div class="icon"></div></div>
    </div>
  </div>
  <h3><%= pseudoConstant.volunteer_role[role_id] %> (<%= quantity || '{ts domain='org.civicrm.volunteer' escape='js'}Any{/ts}' %>): <%= display_time %></h3>
  <table class="row-highlight">
    <thead><tr>
      <th>{ts domain='org.civicrm.volunteer'}Name{/ts}</th>
      <th>{ts domain='org.civicrm.volunteer'}Email{/ts}</th>
      <th>{ts domain='org.civicrm.volunteer'}Phone{/ts}</th>
    </tr></thead>
    <tbody class="crm-vol-assignment-list"></tbody>
  </table>
</script>

<script type="text/template" id="crm-vol-flexible-tpl">
  <h3>{ts domain='org.civicrm.volunteer'}Available Volunteers{/ts}</h3>
  <table class="row-highlight">
    <thead><tr>
      <th>{ts domain='org.civicrm.volunteer'}Name{/ts}</th>
    </tr></thead>
    <tbody class="crm-vol-assignment-list"></tbody>
  </table>
  <hr style="margin: 1em 1px;"/>
  <input name="add-volunteer" class="crm-action-menu action-icon-plus" placeholder="{ts domain='org.civicrm.volunteer' escape='js'}Add Volunteer{/ts}..." style="width: 100%; max-width: 30em;" />
</script>

<script type="text/template" id="crm-vol-menu-tpl">
  <div class="crm-vol-menu-items">
    <ul class="crm-vol-menu-list">
      <li>
        <a href="#" class="crm-vol-menu-parent">{ts domain='org.civicrm.volunteer'}Move To:{/ts}</a>
        <ul class="crm-vol-menu-move-to"></ul>
      </li>
      <li>
        <a href="#" class="crm-vol-menu-parent">{ts domain='org.civicrm.volunteer'}Copy To:{/ts}</a>
        <ul class="crm-vol-menu-copy-to"></ul>
      </li>
      <li><a class="crm-vol-del" href="#">{ts domain='org.civicrm.volunteer'}Delete{/ts}</a></li>
    </ul>
  </div>
</script>

<script type="text/template" id="crm-vol-menu-item-tpl">
  <li class="crm-vol-menu-item"><a href="#<%= cid %>"><strong><%= title %></strong> <%= time %></a></li>
</script>
{/strip}
