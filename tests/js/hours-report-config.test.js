'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const extensionRoot = path.resolve(__dirname, '..', '..');
const read = relativePath => fs.readFileSync(path.join(extensionRoot, relativePath), 'utf8');

const meta = JSON.parse(read('ang/afsearchVolunteerHoursReport.aff.json'));
assert.strictEqual(meta.server_route, 'civicrm/volunteer/hours-report');
assert.deepStrictEqual(meta.permission, ['edit all volunteer projects', 'view all contacts']);
assert.strictEqual(meta.permission_operator, 'AND');
assert.deepStrictEqual(meta.requires, ['volunteerHoursReport']);

const layout = read('ang/afsearchVolunteerHoursReport.aff.html');
[
  'shift_start',
  'volunteer_contact_id',
  'project_id',
  'role_id',
  'status_id',
  'hours_entry_state',
].forEach(field => assert.match(layout, new RegExp(`name="${field}"`)));
assert.match(layout, /afform_default: 'this\.year'/);
assert.match(layout, /Volunteer_Hours_Headline/);
assert.match(layout, /Volunteer_Hours_Summary/);
assert.match(layout, /Volunteer_Hours_Detail/);
assert.match(layout, /crm-ui-tab/);
assert.match(layout, /ng-if="!options\.project_id"/);
assert.strictEqual((layout.match(/filters="\{project_id: options\.project_id\}"/g) || []).length, 3);

const managed = read('managed/VolunteerHoursReport.mgd.php');
assert.strictEqual((managed.match(/'entity' => 'SavedSearch'/g) || []).length, 3);
assert.strictEqual((managed.match(/'entity' => 'SearchDisplay'/g) || []).length, 3);
assert.match(managed, /COUNT\(DISTINCT volunteer_contact_id\)/);
assert.match(managed, /SUM\(logged_hours_total\)/);
assert.match(managed, /'actions' => \['download'\]/);
assert.match(managed, /'limit' => 1,\s*\/\/ This aggregate search always returns exactly one headline row\.\s*'pager' => FALSE,/);
assert.strictEqual((managed.match(/'actions_display_mode' => 'buttons'/g) || []).length, 2);
assert.doesNotMatch(managed, /'actions_display_mode' => 'menu'/);
assert.match(managed, /'update' => 'unmodified'/);

const reportStyles = read('ang/volunteerHoursReport.css');
assert.match(reportStyles, /crm-search-result-select[\s\S]*dropdown-toggle/);
assert.match(reportStyles, /crm-search-result-select[\s\S]*dropdown-menu/);

const info = read('info.xml');
assert.match(info, /<ext>org\.civicrm\.afform<\/ext>/);
assert.match(info, /<ext>org\.civicrm\.search_kit<\/ext>/);

const hook = read('volunteer.php');
assert.match(hook, /'name' => 'volunteer_hours_report'/);
assert.match(hook, /'permission' => 'edit all volunteer projects,view all contacts'/);
assert.match(hook, /'operator' => 'AND'/);

const projectPage = read('ang/volunteer/Projects.html');
const projectController = read('ang/volunteer/Projects.js');
const projectEntity = read('schema/VolunteerProject.entityType.php');
const angularLoader = read('CRM/Volunteer/Angular.php');
const angularModule = read('ang/volunteer.ang.php');
const workflow = read('ang/volunteer/Workflow.js');
const workflowReport = read('ang/volunteer/HoursReport.html');
assert.match(projectPage, /ng-if="canViewHoursReport"/);
assert.match(projectController, /CRM\.checkPerm\('edit all volunteer projects'\)[\s\S]*CRM\.checkPerm\('view all contacts'\)/);
assert.match(projectEntity, /'label_field' => 'title'/);
assert.match(projectEntity, /'search_fields' => \['title'\]/);
assert.match(angularLoader, /str_starts_with\(\$defaultRoute, '\/volunteer\/manage'\)/);
assert.match(angularLoader, /\$modules\[\] = 'afsearchVolunteerHoursReport'/);
assert.match(angularModule, /\['view all contacts'\]/);
assert.match(workflow, /\/volunteer\/manage\/:projectId\/report/);
assert.match(workflow, /volHoursReportAccess/);
assert.match(workflow, /reportOptions = \{project_id: projectId\}/);
assert.match(workflowReport, /<afsearch-volunteer-hours-report options="reportOptions">/);

console.log('Volunteer hours report configuration checks passed.');
