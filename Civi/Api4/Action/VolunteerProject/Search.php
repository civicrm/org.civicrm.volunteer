<?php

namespace Civi\Api4\Action\VolunteerProject;

use Civi\Api4\Generic\AbstractAction;
use Civi\Api4\Generic\Result;

/**
 * Project search across the filters the DAO-backed get cannot express.
 *
 * CRM_Volunteer_BAO_Project::retrieve() understands aggregate filters --
 * project relationships, proximity, beneficiaries -- which have no SQL-clause
 * equivalent in `VolunteerProject.get`. This action exposes them and is the
 * implementation behind the deprecated `VolunteerProject.get` APIv3 action.
 */
class Search extends AbstractAction {

  /**
   * retrieve() filters, e.g. id, is_active, project_contacts, proximity.
   *
   * @var array
   */
  protected $filters = [];

  /**
   * 'edit' restricts the result to projects the caller may edit and returns
   * the full record; anything else is a public read.
   *
   * @var string|null
   */
  protected $context;

  public function _run(Result $result) {
    $filters = $this->filters;
    if ($this->context !== NULL) {
      $filters['context'] = $this->context;
    }
    foreach (\CRM_Volunteer_BAO_Project::searchProjects($filters, $this->getCheckPermissions()) as $row) {
      $result[] = $row;
    }
  }

}
