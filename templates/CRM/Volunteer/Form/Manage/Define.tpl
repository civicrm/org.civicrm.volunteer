

<script type="text/template" id="crm-vol-define-layout-tpl">
  <h2>{ts}Manage Volunteer Needs{/ts}</h2>
  <form><div class="dataTables_wrapper"><table id="crm-vol-define-needs-dialog" class="display">
    <thead><tr>
      <th class="sorting" id="num_needed">{ts}Needed{/ts}</th><th class="sorting" id="filled">{ts}Filled{/ts}</th><th class="sorting" id="role_id">{ts}Role{/ts}</th><th class="sorting" id="start_date">{ts}Start Time{/ts}</th><th class="sorting" id="duration">{ts}Scheduled Duration{/ts}</th><th class="sorting" id="visibility">{ts}Visibility{/ts}</th><th>&nbsp;</th>
    </tr></thead>
     <tbody id="crm-vol-define-needs-region"></tbody>
  </table></div>
  <a class="button" id="addNewNeed" href="#"><span><div class="icon add-icon"></div>Add a Need</span></a>
  </form>
</script>

<script type="text/template" id="crm-vol-define-new-need-tpl">
    <td><input type="text" name="num_needed" value="<%= num_needed %>" size="4"></td>
    <td><input type="text" name="filled" value="<%= filled %>" size="4" disabled></td>
    <td>
      {literal}
        <%= RenderUtil.select({
                id: 'crm-vol-role-select',
                name: 'role_id',
                options: _.extend(pseudoConstant.volunteer_role, {0:''}),
                selected: role_id
        }) %>
      {/literal}</td>
    <td>
      <input type="text" name="display_start_date" value="<%= display_start_date %>" size="20">
      <input type="text" name="display_start_time" value="<%= display_start_time %>" size="10">
    </td>
    <td><input type="text" name="duration" value="<%= duration %>" size="6"></td>
    <td><input type="checkbox" name="visibility_id" data-stored="<%= visibility_id %>"></td>
    <td><input type="checkbox" name="is_active" value="1" data-stored="<%= is_active %>"></td>
  </script>
