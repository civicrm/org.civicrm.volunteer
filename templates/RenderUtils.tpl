{*
 * A template for building select lists.
 *
 * Params apiEntity, apiField, and objectEditPath are needed only if:
 * - the options in the select menu represent optionValues in an optionGroup
 * - the user should be provided a widget/modal for inline editing of these
 *
 * FIXME: Support missing apiEntity, apiField, and objectEditPath params, since
 * these are optional.
 *
 * @param string name
 *   Name to give the form field.
 * @param string objectEditPath
 *   A partial URL such as 'civicrm/admin/options/volunteer_role' for editing the option
 * @param object options
 *   Options for the select list (e.g., {value: label}).
 * @param string selected
 *   The value of the OptionValue which should be pre-selected.
 *
 * FIXME: escape "value" attribute
 *
 * Has been somewhat modified from the original: https://github.com/civicrm/civihr/blob/master/hrjob/templates/CRM/HRJob/Underscore/renderutil-select.tpl
 *}
{literal}
<script id="renderutil-select-template" type="text/template">
  <div class="crm-vol-renderutil-select">
    <select data-option-edit-path="<%= optionEditPath %>"
      class="crm-select2 crm-form-select" name="<%= name %>"
      data-api-entity="<%= apiEntity %>" data-api-field="<%= apiField %>">
      <% _.each(options, function(optionLabel, optionValue) { %>
        <option value="<%- optionValue %>" <%= selected == optionValue ? 'selected' : '' %>><%- optionLabel %></option>
      <% }); %>
    </select>
    <% if (!_.isEmpty(optionEditPath)) { %>
      <a href="<%= CRM.url(optionEditPath, {reset: 1}) %>"
        class="crm-option-edit-link medium-popup crm-hover-button" target="_blank"
        title="Edit Options" data-option-edit-path="optionEditPath">
        <span class="icon ui-icon-wrench"></span>
      </a>
    <% } %>
  </div>
</script>
{/literal}
