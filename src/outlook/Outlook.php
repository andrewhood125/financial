<?php namespace Financial\Outlook;

use Symfony\Component\Yaml\Yaml;

class Outlook {

  public $yaml;
  public $months;

  public function __construct($months) {
    $this->yaml = Yaml::parse(file_get_contents(__DIR__ .'/../../Financial.yaml'));
    $this->month = $months;
  }

  public function __toString() {
    return print_r($this->yaml, true);
  }
}
