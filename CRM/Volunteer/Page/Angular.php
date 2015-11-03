<?php

require_once 'Civi/Angular/Page/Main.php';
require_once 'CRM/Volunteer/Angular/Manager.php';

class CRM_Volunteer_Page_Angular extends Civi\Angular\Page\Main {

  public function __construct($title = NULL, $mode = NULL, $res = NULL) {
    parent::__construct($title, $mode);
    $this->angular = new Civi\Angular\VolunteerManager(\CRM_Core_Resources::singleton());
  }
}
