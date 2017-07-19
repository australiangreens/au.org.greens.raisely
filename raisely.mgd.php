<?php
$entities = array();
try {
  $res = civicrm_api3('FinancialType', 'getSingle', array("name" => "Donation", 'return' => array('id')));
}
catch (CiviCRM_API3_Execption $e) {
  $res = NULL;
}
if (!$res) {
  $entites[] = array(
    'name' => 'au.org.greens.raisely',
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
catch (CiviCRM_API3_Execption $e) {
  $res = NULL;
}
if (!$res) {
  $entites[] = array(
    'name' => 'au.org.greens.raisely',
    'entity' => 'LocationType',
    'params' => array(
      'version' => 3,
      'name' => 'Previous',
      'desciption' => 'For old addresses no longer valid',
      'is_active' => 1,
      'is_default' => 0,
    ),
  );
}
return $entites;
