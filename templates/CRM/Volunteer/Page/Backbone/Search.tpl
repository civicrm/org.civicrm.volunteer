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
{* Contains js templates for backbone-based volunteer search sub-application *}

<script type="text/template" id="crm-vol-search-layout-tpl">
  <form class="crm-block crm-form-block crm-event-manage-volunteer-search-form-block">
    <div class="crm-accordion-wrapper">
      <div class="crm-accordion-header">{ts domain='org.civicrm.volunteer'}Edit Search Criteria{/ts}</div>
      <div class="crm-accordion-body" id="crm-vol-search-form-region">
        <div class="crm-loading-element">{ts domain='org.civicrm.volunteer'}Loading&hellip;{/ts}</div>
      </div>
    </div>
  </form>
  <form class="crm-block crm-form-block crm-event-manage-volunteer-results-form-block">
    <div class="crm-content-block">
      <div class="crm-results-block">
        <div id="crm-vol-search-pager"></div>
        <div class="crm-search-results" id="crm-vol-search-results-region">
          <div class="crm-loading-element">{ts domain='org.civicrm.volunteer'}Loading&hellip;{/ts}</div>
        </div>
      </div>
    </div>
  </form>
</script>

<script type="text/template" id="crm-vol-search-result-tpl">
  <table summary="{ts domain='org.civicrm.volunteer'}Search results listings.{/ts}" class="selector row-highlight">
    <thead class="sticky">
      <tr>
        <th scope="col"><input type="checkbox" name="select_all_contacts" title="{ts domain='org.civicrm.volunteer'}Select All Rows{/ts}" /></th>
        <th scope="col">{ts domain='org.civicrm.volunteer'}Name{/ts}</th>
        <th scope="col">{ts domain='org.civicrm.volunteer'}City{/ts}</th>
        <th scope="col">{ts domain='org.civicrm.volunteer'}State{/ts}</th>
        <th scope="col">{ts domain='org.civicrm.volunteer'}Email{/ts}</th>
        <th scope="col">{ts domain='org.civicrm.volunteer'}Phone{/ts}</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</script>

<script type="text/template" id="crm-vol-search-field-checkRadio-tpl">
  <% var elementType = selectMultiple ? 'checkbox' : 'radio'; %>
  <div class="label">
    <label><%= label %>:</label>
  </div>
  <div class="content">
    {literal}
      <% options.forEach(function(item) { %>
        <% var elementClass = 'crm-form-' + elementType; %>
        <% var elementID = elementName + '_' + item.value; %>
        <input class="<%= elementClass %>" id="<%= elementID %>" name="<%= elementName %>" type="<%= elementType %>" value="<%= item.value %>"/>
        <label for="<%= elementID %>"><%= item.label %></label>
      <% }); %>
    {/literal}
  </div>
</script>

<script type="text/template" id="crm-vol-search-field-select-tpl">
  <% var multipleAttr = (selectMultiple ? 'multiple' : ''); %>
  <div class="label">
    <label for="<%= elementName %>"><%= label %>:</label>
  </div>
  <div class="content">
    <select class="big crm-form-select" id="<%= elementName %>" <%= multipleAttr %> name="<%= elementName %>">
    {literal}
      <% options.forEach(function(item) { %>
        <option value="<%= item.value %>"><%= item.label %></option>
      <% }); %>
    {/literal}
    </select>
  </div>
</script>

<script type="text/template" id="crm-vol-search-field-text-tpl">
  <div class="label">
    <label for="<%= elementName %>"><%= label %>:</label>
  </div>
  <div class="content">
    <input type="text" class="big crm-form-text" id="<%= elementName %>" name="<%= elementName %>"/>
  </div>
</script>

<script type="text/template" id="crm-vol-search-contact-tpl">
  <td><input class="select-row crm-form-checkbox" type="checkbox" name="selected_contacts" value="<%= contact_id %>" /></td>
  <td><%= sort_name %></td>
  <td><%= city %></td>
  <td><%= state_province %></td>
  <td><%= email %></td>
  <td><%= phone %></td>
</script>

<script type="text/template" id="crm-vol-search-pager-tpl">
  <div class="crm-submit-buttons">
    <% if (start > 1) {literal}{{/literal} %>
      <button class="crm-button crm-button-type-back">{ts domain='org.civicrm.volunteer'}Previous{/ts}</button>
    <% {literal}}{/literal} %>
    <% if (total > end) {literal}{{/literal} %>
      <button class="crm-button crm-button-type-next">{ts domain='org.civicrm.volunteer'}Next{/ts}</button>
    <% {literal}}{/literal} %>
    <span>Showing contacts <%= start %> - <%= end %> of <%= total %></span>
  </div>
</script>
{/strip}