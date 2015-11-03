<?php


namespace Civi\Angular;

class VolunteerManager extends Manager {

  public function __construct($res) {
    $this->res = $res;
    $this->REQUIRED_MODULES = array(
      "crmApp",
      "crmAttchment",
      "crmAutosave",
      "crmCxn",
      "crmResource",
      "crmUi",
      "crmUtil",
      "dialogService",
      "ngRoute",
      "ngSanitize",
      "ui.utils",
      "ui.sortable",
      "unsavedChanges",
      "volunteer",
      "crmProfileUtils"
    );
  }

  public function getModules() {
    parent::getModules();

    //Filter out the ones we don't want.
    if (!$this->modules) {
      foreach ($this->modules as $name => $module) {
        if (!in_array($name, $this->REQUIRED_MODULES)) {
          unset($this->modules[$name]);
        }
      }
    }

    return $this->modules;
  }

}