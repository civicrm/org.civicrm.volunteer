(function(ts) {
  CRM.$(function($) {
    // When the mode radio button changes, show only the appropriate follow-up field
    $('[name^=volunteer_project_default_contacts_mode_]').change(function () {
      var elName = this.name;
      var projectRelationshipType = elName.replace('volunteer_project_default_contacts_mode_', '');

      var selectedMode = this.value;
      var showField = 'volunteer_project_default_contacts_' + selectedMode + '_' + projectRelationshipType;

      CRM.$('[name^=volunteer_project_default_contacts_][name$=' + projectRelationshipType + ']:not([name=' + elName + '])').each(function() {
        $(this).closest('.crm-section').hide();
      });
      CRM.$('[name=' + showField + ']').closest('.crm-section').show();
    });

    // Initialize mode follow-up fields
    $('[name^=volunteer_project_default_contacts_mode_]:checked').trigger('change');
  });
}(CRM.ts('org.civicrm.volunteer')));
