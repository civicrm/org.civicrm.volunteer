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
    <div id="crm-vol-search-form-region">
      <div class="crm-loading-element">{ts domain='org.civicrm.volunteer'}Loading{/ts}...</div>
    </div>
  </form>
  <form class="crm-block crm-form-block crm-event-manage-volunteer-form-block hiddenElement">
    <div id="crm-vol-search-results-region">
      <div class="crm-loading-element">{ts domain='org.civicrm.volunteer'}Loading{/ts}...</div>
    </div>
  </form>
</script>

<script type="text/template" id="crm-vol-search-field-checkRadio-tpl">
  <% var elementType = selectMultiple ? 'checkbox' : 'radio'; %>
  <label><%= label %>: </label>
  {literal}
    <% options.forEach(function(item) { %>
      <% console.dir(item.is_default); %>
      <% var checked = (item.is_default === '1') ? 'checked' : ''; %>
      <% var elementClass = 'crm-form-' + elementType; %>
      <% var elementID = elementName + '_' + item.value; %>
      <label for="<%= elementID %>"><%= item.label %> </label>
      <input <%= checked %> class="<%= elementClass %>" id="<%= elementID %>" name="<%= elementName %>" type="<%= elementType %>" value="<%= item.value %>"/>
    <% }); %>
  {/literal}
</script>

<script type="text/template" id="crm-vol-search-field-select-tpl">
  <% var multipleAttr = (selectMultiple ? 'multiple' : ''); %>
  <label for="<%= elementName %>"><%= label %>: </label>
  <select class="crm-form-select" id="<%= elementName %>" <%= multipleAttr %> name="<%= elementName %>">
  {literal}
    <% options.forEach(function(item) { %>
      <option value="<%= item.value %>"><%= item.label %></option>
    <% }); %>
  {/literal}
  </select>
</script>

<script type="text/template" id="crm-vol-search-field-text-tpl">
  <label for="<%= elementName %>"><%= label %>: </label>
  <input type="text" class="crm-form-text" id="<%= elementName %>" name="<%= elementName %>"/>
</script>

{*
let's narrow the scope a little bit

WE WILL SUPPORT:
- text ::: Text
- select (and autocomplete select) ::: Select, AdvMulti-Select, Autocomplete-Select
- multiselect (and andvanced ms) ::: Multi-Select

WILL PROBABLY SUPPORT:
- radio ::: Radio
- checkbox ::: CheckBox


WE WILL NOT SUPPORT:
- note
- dates
- state/province
- country
- file
- link
- contact reference
*}

{/strip}