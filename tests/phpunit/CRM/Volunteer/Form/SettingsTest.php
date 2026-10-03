<?php

require_once __DIR__ . '/../../../VolunteerTestAbstract.php';

/**
 * Tests CRM_Volunteer_Form_Settings, the CiviVolunteer administration form.
 *
 * @group headless
 */
class CRM_Volunteer_Form_SettingsTest extends VolunteerTestAbstract {

  /**
   * A constructed form wired to a controller replaying the given submission.
   *
   * @param array $submittedValues
   * @return CRM_Volunteer_Form_Settings
   */
  private function buildForm(array $submittedValues = array()): CRM_Volunteer_Form_Settings {
    // The form reads these keys unconditionally from its export; a real
    // submission always carries them because buildQuickForm() renders the
    // elements. QuickForm's exportValues() only reports values for
    // registered elements, so the form is built for real and the submission
    // injected at the QuickForm level.
    $baseline = array(
      'volunteer_general_campaign_filter_type' => 'blacklist',
      'volunteer_general_campaign_filter_list' => array(),
      'volunteer_project_default_is_active' => 0,
      'volunteer_use_backend_theme' => 1,
    );
    foreach (array('volunteer_owner', 'volunteer_manager', 'volunteer_beneficiary') as $relationship) {
      $baseline["volunteer_project_default_contacts_mode_$relationship"] = 'acting_contact';
    }

    $form = new CRM_Volunteer_Form_Settings();
    $form->preProcess();
    $form->buildQuickForm();
    $submitValues = new ReflectionProperty('HTML_QuickForm', '_submitValues');
    $submitValues->setAccessible(TRUE);
    $submitValues->setValue($form, $submittedValues + $baseline);
    return $form;
  }

  /**
   * Choosing "Specific Contact(s)" or "Related Contact(s)" without naming
   * anyone is a validation error; "Acting Contact" needs nothing else.
   */
  public function testValidateRequiresAContactForNonActingModes(): void {
    $form = $this->buildForm(array(
      'volunteer_project_default_contacts_mode_volunteer_owner' => 'acting_contact',
      'volunteer_project_default_contacts_mode_volunteer_manager' => 'contact',
      // No volunteer_project_default_contacts_contact_volunteer_manager.
    ));
    $form->validate();
    $errors = $form->getVar('_errors');
    $this->assertArrayHasKey(
      'volunteer_project_default_contacts_contact_volunteer_manager',
      $errors,
      'A specific-contact mode without a contact must fail validation.'
    );

    $form = $this->buildForm(array(
      'volunteer_project_default_contacts_mode_volunteer_manager' => 'contact',
      'volunteer_project_default_contacts_contact_volunteer_manager' => array('5'),
    ));
    $form->validate();
    $this->assertSame(array(), $form->getVar('_errors'), 'Naming the contact satisfies the rule.');
  }

  /**
   * postProcess composes the audience-keyed profile setting and the other
   * defaults into the settings bag.
   */
  public function testPostProcessPersistsComposedSettings(): void {
    $profile = \Civi\Api4\UFGroup::create(FALSE)
      ->addValue('name', uniqid('settings_profile_', FALSE))
      ->addValue('title', 'Settings test profile')
      ->addValue('is_active', TRUE)
      ->execute()
      ->single();
    $audiences = array_column(
      CRM_Volunteer_BAO_Project::getProjectProfileAudienceTypes(),
      'type'
    );
    $this->assertNotEmpty($audiences);

    $form = $this->buildForm(array(
      'volunteer_project_default_profiles_' . $audiences[0] => array((string) $profile['id']),
      'volunteer_general_campaign_filter_type' => 'whitelist',
      'volunteer_general_campaign_filter_list' => array('1', '2'),
      'volunteer_project_default_is_active' => 1,
      'volunteer_general_project_settings_help_text' => '<p>Be helpful</p>',
    ));
    CRM_Core_Session::singleton()->getStatus(TRUE);
    $form->postProcess();

    $settings = \Civi\Api4\Setting::get(FALSE)->execute()->column('value', 'name');
    $composed = $settings['volunteer_project_default_profiles'];
    foreach ($audiences as $audience) {
      $this->assertArrayHasKey($audience, $composed, "The profiles setting must stay keyed by audience ($audience).");
    }
    $this->assertSame(array((int) $profile['id']), array_map('intval', (array) $composed[$audiences[0]]));
    $this->assertSame('whitelist', $settings['volunteer_general_campaign_filter_type']);
    $this->assertSame(array('1', '2'), array_values((array) $settings['volunteer_general_campaign_filter_list']));
    $this->assertSame(1, (int) $settings['volunteer_project_default_is_active']);
    $this->assertSame('<p>Be helpful</p>', $settings['volunteer_general_project_settings_help_text']);

    $statuses = CRM_Core_Session::singleton()->getStatus(TRUE);
    $this->assertContains(
      array(
        'text' => 'Changes Saved',
        'title' => 'Saved',
        'type' => 'success',
        'options' => NULL,
      ),
      $statuses,
      'A successful save must set its own saved status message.'
    );
  }

  /**
   * An unchecked is_active box is stored as 0, not skipped: the settings bag
   * must reflect the form, not merge with stale stored values.
   */
  public function testPostProcessClearsUncheckedDefaults(): void {
    \Civi\Api4\Setting::set(FALSE)
      ->setValues(array('volunteer_project_default_is_active' => 1))
      ->execute();

    $form = $this->buildForm(array());
    $form->postProcess();

    $isActive = \Civi\Api4\Setting::get(FALSE)
      ->addSelect('volunteer_project_default_is_active')
      ->execute()
      ->first()['value'];
    $this->assertSame(0, (int) $isActive, 'An unchecked default must overwrite the stored 1.');
  }

  /**
   * Field descriptions come from the settings metadata's help_text, which
   * core now stores as an array of paragraphs. The template prints a string,
   * so the form must flatten it rather than render "Array".
   */
  public function testFieldDescriptionsAreStrings(): void {
    $form = $this->buildForm();
    $descriptions = $form->get_template_vars('fieldDescriptions');
    $this->assertArrayHasKey('volunteer_use_backend_theme', $descriptions);
    $this->assertArrayHasKey('volunteer_general_campaign_filter_list', $descriptions);
    foreach ($descriptions as $name => $description) {
      $this->assertIsString($description, "$name description must be a string.");
      $this->assertNotSame('Array', $description);
      $this->assertNotSame('', trim($description));
    }
    $this->assertStringContainsString('frontend theme', $descriptions['volunteer_use_backend_theme']);
  }

  /**
   * The public-theme switch round-trips through the form: checked stores 1,
   * an absent (unchecked) box stores 0 rather than leaving the old value.
   */
  public function testPostProcessStoresTheBackendThemeSwitch(): void {
    $read = function() {
      return (int) \Civi\Api4\Setting::get(FALSE)
        ->addSelect('volunteer_use_backend_theme')
        ->execute()
        ->first()['value'];
    };

    $form = $this->buildForm(array('volunteer_use_backend_theme' => 1));
    $form->postProcess();
    $this->assertSame(1, $read());

    $form = $this->buildForm();
    $submitValues = new ReflectionProperty('HTML_QuickForm', '_submitValues');
    $submitValues->setAccessible(TRUE);
    $values = $submitValues->getValue($form);
    unset($values['volunteer_use_backend_theme']);
    $submitValues->setValue($form, $values);
    $form->postProcess();
    $this->assertSame(0, $read(), 'An unchecked box must store 0.');

    $defaults = $this->buildForm()->setDefaultValues();
    $this->assertSame(0, (int) $defaults['volunteer_use_backend_theme'], 'The form reads the stored value back.');
  }

}
