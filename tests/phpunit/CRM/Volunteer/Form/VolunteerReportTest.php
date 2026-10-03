<?php

require_once __DIR__ . '/../../../VolunteerTestAbstract.php';

/**
 * Tests CRM_Volunteer_Form_VolunteerReport's migration of legacy contact
 * filters: saved sort-name strings are resolved to the contact-ID CSV the
 * autocomplete filters now store.
 *
 * The report's full postProcess (parent CRM_Report_Form) needs the whole
 * report form lifecycle; this class pins the extension-owned behaviour on
 * top of it. Column availability per component is already covered by
 * CampaignIntegrationTest.
 *
 * @group headless
 */
class CRM_Volunteer_Form_VolunteerReportTest extends VolunteerTestAbstract {

  /**
   * @var CRM_Volunteer_Form_VolunteerReport
   */
  private $form;

  public function setUp(): void {
    parent::setUp();
    // CRM_Volunteer_Form_VolunteerReportTest runs the real constructor so the
    // column definitions (which handleLegacyContactParams reads for its
    // message titles) exist.
    $this->form = new CRM_Volunteer_Form_VolunteerReport();
  }

  private function setFormValue(string $key, $value): void {
    $property = new ReflectionProperty(CRM_Report_Form::class, '_formValues');
    $property->setAccessible(TRUE);
    $values = $property->getValue($this->form) ?: array();
    $values[$key] = $value;
    $property->setValue($this->form, $values);
  }

  private function getFormValues(): array {
    $property = new ReflectionProperty(CRM_Report_Form::class, '_formValues');
    $property->setAccessible(TRUE);
    return $property->getValue($this->form) ?: array();
  }

  private function runLegacyMigration(): void {
    $method = new ReflectionMethod(CRM_Volunteer_Form_VolunteerReport::class, 'handleLegacyContactParams');
    $method->setAccessible(TRUE);
    $method->invoke($this->form);
  }

  /**
   * A stored sort-name string is resolved to matching (non-deleted) contact
   * IDs and announces the migration to the report operator.
   */
  public function testLegacySortNameFiltersAreMigratedToContactIds(): void {
    $matching = $this->individualCreate(array(
      'first_name' => 'Wanda',
      'last_name' => 'Whitfield',
    ));
    $deleted = $this->individualCreate(array(
      'first_name' => 'Wanda',
      'last_name' => 'Whitfield',
    ));
    \Civi\Api4\Contact::update(FALSE)
      ->addWhere('id', '=', $deleted)
      ->addValue('is_deleted', TRUE)
      ->execute();
    $this->setFormValue('contact_assignee_value', 'Whitfield');

    // Drain statuses left by earlier tests in this process.
    CRM_Core_Session::singleton()->getStatus(TRUE);
    $this->runLegacyMigration();

    $values = $this->getFormValues();
    $this->assertSame(
      (string) $matching,
      $values['contact_assignee_value'],
      'A legacy sort-name filter must become the CSV of matching, non-deleted contact IDs.'
    );
    $this->assertSame('in', $values['contact_assignee_op']);
    $this->assertNotEmpty(
      CRM_Core_Session::singleton()->getStatus(TRUE),
      'A migrated filter must announce itself so the operator re-saves the report.'
    );
  }

  /**
   * Values already in the ID-CSV storage format -- and empty values -- pass
   * through untouched.
   */
  public function testModernFiltersAreLeftAlone(): void {
    $this->setFormValue('contact_source_value', '3,17');
    $this->setFormValue('contact_target_value', '');

    CRM_Core_Session::singleton()->getStatus(TRUE);
    $this->runLegacyMigration();

    $values = $this->getFormValues();
    $this->assertSame('3,17', $values['contact_source_value']);
    $this->assertSame('', $values['contact_target_value']);
    $this->assertArrayNotHasKey('contact_source_op', $values);
    $this->assertSame(array(), CRM_Core_Session::singleton()->getStatus(TRUE));
  }
}
