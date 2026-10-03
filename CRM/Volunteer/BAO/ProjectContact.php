<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.6                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2015                                |
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
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2015
 * $Id$
 *
 */

class CRM_Volunteer_BAO_ProjectContact extends CRM_Volunteer_DAO_ProjectContact {

  const RELATIONSHIP_OPTION_GROUP = 'volunteer_project_relationship';

  /**
   * Create or update a volunteer project contact.
   *
   * The authorization lives here rather than in the APIv3 wrapper because every
   * write path converges on this method: _civicrm_api3_basic_create() dispatches
   * to a BAO `create()` when one exists, and so does API4's
   * DAOActionTrait::write(). Without it, API4's generic save/replace fell
   * through to CRM_Core_DAO::writeRecords() and wrote rows with no project
   * check at all.
   *
   * @param array $params
   *
   * @return CRM_Volunteer_BAO_ProjectContact
   *
   * @throws CRM_Core_Exception
   */
  public static function create(array $params) {
    if (empty($params['id'])) {
      foreach (array('project_id', 'contact_id', 'relationship_type_id') as $requiredField) {
        if (empty($params[$requiredField])) {
          throw new CRM_Core_Exception(ts('%1 is required to create a volunteer project contact.', array(
            1 => $requiredField,
            'domain' => 'org.civicrm.volunteer',
          )));
        }
      }
    }

    $existingProjectId = NULL;
    if (!empty($params['id'])) {
      $existing = new self();
      $existing->id = (int) $params['id'];
      if (!$existing->find(TRUE)) {
        throw new CRM_Core_Exception(ts('Volunteer project contact %1 does not exist.', array(
          1 => (int) $params['id'],
          'domain' => 'org.civicrm.volunteer',
        )));
      }
      $existingProjectId = (int) $existing->project_id;
      // Carry forward the fields this write does not mention. A partial update
      // would otherwise return a partial record, since the DAO only holds what
      // was assigned to it.
      foreach (array('project_id', 'contact_id', 'relationship_type_id') as $fieldName) {
        if (!array_key_exists($fieldName, $params)) {
          $params[$fieldName] = $existing->$fieldName;
        }
      }
    }

    if (CRM_Volunteer_Permission::shouldCheckPermissions($params)) {
      CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::UPDATE, $params['project_id']);
      // Moving a row between projects needs authority over both sides.
      if ($existingProjectId && $existingProjectId !== (int) $params['project_id']) {
        CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::UPDATE, $existingProjectId);
      }
      if (!CRM_Volunteer_Permission::check('edit volunteer project relationships')) {
        throw new CRM_Core_Exception(
          ts('You do not have permission to modify contacts for this project.', array('domain' => 'org.civicrm.volunteer')),
          CRM_Core_Exception::UNAUTHORIZED
        );
      }
    }

    $projectContact = new self();
    $projectContact->copyValues($params);
    $projectContact->save();

    // Permission decisions memoise a project's contacts for the request.
    CRM_Volunteer_Permission::flushProjectContactCache($params['project_id'] ?? NULL);
    if ($existingProjectId && $existingProjectId !== (int) $params['project_id']) {
      CRM_Volunteer_Permission::flushProjectContactCache($existingProjectId);
    }

    return $projectContact;
  }

  /**
   * Delete a project-contact row through the shared authorization boundary.
   */
  public static function deleteProjectContact($id, $checkPermissions = TRUE) {
    $id = (int) $id;
    $projectId = (int) CRM_Core_DAO::getFieldValue(self::class, $id, 'project_id');
    if (!$projectId) {
      throw new CRM_Core_Exception(ts('The volunteer project contact does not exist.', array('domain' => 'org.civicrm.volunteer')));
    }
    if ($checkPermissions) {
      CRM_Volunteer_Permission::assertProjectPerms(CRM_Core_Action::UPDATE, $projectId);
      if (!CRM_Volunteer_Permission::check('edit volunteer project relationships')) {
        throw new CRM_Core_Exception(ts('You do not have permission to modify contacts for this project.', array('domain' => 'org.civicrm.volunteer')), CRM_Core_Exception::UNAUTHORIZED);
      }
    }
    $row = new self();
    $row->id = $id;
    if (!$row->find(TRUE)) {
      return FALSE;
    }
    $row->delete();
    CRM_Volunteer_Permission::flushProjectContactCache($projectId);
    return TRUE;
  }

}
