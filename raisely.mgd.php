<?php
$entities = array();
try {
  $res = civicrm_api3('FinancialType', 'getsingle', array("name" => "Donation", 'return' => array('id')));
}
catch (CiviCRM_API3_Exception $e) {
  $res = NULL;
}
if (!$res) {
  $entities[] = array(
    'name' => 'au.org.greens.raisely',
    'module' => 'au.org.greens.raisely',
    'entity' => 'FinancialType',
    'params' => array(
      'version' => 3,
      'name' => 'Donation',
      'description' => 'Donation',
      'is_reserved' => 1,
      'is_active' => 1,
    ),
  );
}
try {
  $res = civicrm_api3('LocationType', 'getsingle', array('name' => 'Previous', 'return' => array('id')));
}
catch (CiviCRM_API3_Exception $e) {
  $res = NULL;
}
if (!$res) {
  $entities[] = array(
    'name' => 'au.org.greens.raisely',
    'module' => 'au.org.greens.raisely',
    'entity' => 'LocationType',
    'params' => array(
      'version' => 3,
      'name' => 'Previous',
      'description' => 'For old addresses no longer valid',
      'is_active' => 1,
      'is_reserved' => 1,
      'is_default' => 0,
    ),
  );
}
return $entities;
