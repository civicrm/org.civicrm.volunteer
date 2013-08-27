

<script type="text/template" id="crm-vol-define-layout-tpl">
  <h2>{ts}Manage Volunteer Needs{/ts}</h2>
  <form><table>
    <thead><tr>
      <th>{ts}Needed{/ts}</th><th>{ts}Filled{/ts}</th><th>{ts}Role{/ts}</th><th>{ts}Start Time{/ts}</th><th>{ts}Scheduled Durration{/ts}</th><th>{ts}Is Flexible?{/ts}</th><th>{ts}Visibility{/ts}</th><th></th>
    </tr></thead>
     <tbody id="crm-vol-define-needs-region"></tbody>
  </table>
  <a class="button" id="addMoreVolunteer" href="#"><span><div class="icon add-icon"></div>Add a Need</span></a>
  </form>
</script>

<script type="text/template" id="crm-vol-define-new-need-tpl">
    <td><input type="text" value="<%= num_needed %>" size="4"></td>
    <td><input type="text" value="<%= filled %>" size="4" disabled></td>
    <td><input type="text" value="<%= role_id %>" size="4"></td>
    <td><input type="text" value="<%= start_time %>" size="4"></td>
    <td><input type="text" value="<%= duration %>" size="12"></td>
    <td><input type="checkbox" value="<%= is_flexible %>"></td>
    <td><input type="text" value="<%= visibility %>" size="4"></td>
    <td><%= links %></td>
  </script>