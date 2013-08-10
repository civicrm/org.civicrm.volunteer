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

<script type="text/template" id="crm-vol-define-layout-tpl">
  <h2>{ts}Manage Volunteer Needs{/ts}</h2>
  <table>
    <thead><tr>
      <th>{ts}Needed{/ts}</th><th>{ts}Filled{/ts}</th><th>{ts}Role{/ts}</th><th>{ts}Start Time{/ts}</th><th>{ts}Scheduled Durration{/ts}</th><th>{ts}Is Flexible?{/ts}</th><th>{ts}Visibility{/ts}</th><th></th>
    </tr></thead>
     <tbody id="crm-vol-define-needs-region"></tbody>
  </table>
</script>

<script type="text/template" id="crm-vol-define-newNeed-tpl">
  <tr>
    <td><%= num_needed %></td>
    <td><%= filled %></td>
    <td><%= role %></td>
    <td><%= start_time %></td>
    <td><%= durration %></td>
    <td><%= is_flexible %></td>
    <td><%= visibiliy %></td>
    <td><%= links %></td>
  </tr>
  </script>