<?php

namespace Civi\Api4\Action\VolunteerAssignment;

use Civi\Api4\Activity;
use Civi\Api4\Generic\BasicBatchAction;
use Civi\Api4\Generic\Result;

class Delete extends BasicBatchAction {

  /**
   * The batch record must carry project_id: processBatch() authorizes each
   * row against its owning project, and checkProjectPerms() refuses UPDATE
   * without one. The inherited default selects only the primary key, which
   * made every permission-checked delete fail -- for administrators too.
   *
   * @return string[]
   */
  protected function getSelect(): array {
    return ['id', 'project_id'];
  }

  protected function processBatch(Result $result, array $items) {
    foreach ($items as $item) {
      if ($this->getCheckPermissions()) {
        \CRM_Volunteer_Permission::assertProjectPerms(\CRM_Core_Action::UPDATE, $item['project_id']);
      }
      Activity::delete(FALSE)
        ->addWhere('id', '=', $item['id'])
        ->execute();
      $result[] = ['id' => (int) $item['id']];
    }
  }

}
