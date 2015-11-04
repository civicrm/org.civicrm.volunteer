<?php

require_once 'CRM/Volunteer/Angular/Manager.php';

class CRM_Volunteer_Page_Angular_Modules extends Civi\Angular\Page\Modules {

  public function run()
  {
    /**
     * @var \Civi\Angular\Manager $angular
     */
    //Use our manager instead of the one provided by core
    $angular = new Civi\Angular\VolunteerManager(\CRM_Core_Resources::singleton());
    $moduleNames = $this->parseModuleNames(\CRM_Utils_Request::retrieve('modules', 'String'), $angular);

    switch (\CRM_Utils_Request::retrieve('format', 'String')) {
      case 'json':
      case '':
        $this->send(
          'application/javascript',
          json_encode($this->getMetadata($moduleNames, $angular))
        );
        break;

      case 'js':
        $digest = $this->digestJs($angular->getResources($moduleNames, 'js', 'path'));
        //Tell crmResource to use our ajax end-point
        $digest = str_replace("ajax/angular-modules", "ajax/volunteer-angular-modules", $digest);
        $this->send(
          'application/javascript',
          $digest
        );
        break;

      case 'css':
        $this->send(
          'text/css',
          \CRM_Utils_File::concat($angular->getResources($moduleNames, 'css', 'path'), "\n")
        );
        break;

      default:
        \CRM_Core_Error::fatal("Unrecognized format");
    }

    \CRM_Utils_System::civiExit();
  }
}