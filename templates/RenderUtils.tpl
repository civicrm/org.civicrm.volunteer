{*
@param string id
@param string name
@param string selected
@param array options

FIXME: escape "value" attribute
*}
{* orginal: https://github.com/civicrm/civihr/blob/master/hrjob/templates/CRM/HRJob/Underscore/renderutil-select.tpl *}
{literal}
<script id="renderutil-select-template" type="text/template">
<select class="crm-form-select" name="<%= name %>">
<% _.each(options, function(optionLabel, optionValue) { %>
    <option value="<%- optionValue %>" <%= selected == optionValue ? 'selected' : '' %>><%- optionLabel %></option>
    <% }); %>
</select>
</script>
{/literal}
