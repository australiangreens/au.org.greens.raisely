<?php

class CRM_Raisely_Page_Raisely extends CRM_Core_Page {

  function _parseDonor($json) {
    // Raisely JSON data contains all information about a donor inside a
    // certain path of their JSON tree. It is currently
    // JSON->data->result->metadata
    $data = $json['data']['result']['metadata'];

    // The following field names are not guaranteed - depends on 
    // configuration of specific Raisely campaigns.
    // TODO permit definition of field names through admin interface
    // the set would be passed as an array to this function

    $donor_keys = [
      'first_name',
      'last_name',
      'email',
      'private.street_address',
      'private.postcode',
      'private.suburb',
      'private.state',
      'private.country',
    ];

    // Loop through donor keys, check none are missing
    // and assign to $donor variable for return
    foreach ($donor_keys as $key) {
      if (!array_key_exists($key, $data)) {
        return NULL;
      };
      $donor[$key] = $data[$key];
    };
    $donor['contact_type'] => 'Individual',
    return $donor;
  }

  public function run() {

    // Test method - fail gracefully on non-POST methods
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo "Oh dear...this is unexpected.";
      CRM_Utils_System::civiExit();
    }

    // Get POST data and test that it's JSON
    $data = $_REQUEST;
    if (!($data = $_POST)) {
      $data = json_decode(file_get_contents("php://input"), TRUE);
    }

    if (is_null($data)) {
      echo "You didn't give me POST JSON data!";
      CRM_Utils_System::civiExit();
    }

    // TODO Check that the action is "donation"
    if ( $data['action'] != 'donation') {
      echo "This isn't a donation";
      CRM_Utils_System::civiExit();
    }

    $donor = self::_parseDonor($data);
    if (is_null($donor)) {
      echo "Bad donor data - cannot process request";
      CRM_Utils_System::civiExit();
    }
    
    // TODO Parse contribution data 

    $contribution = self::_parseContribution($data);
    if (is_null($contribution)) {
      echo "Bad contribution data - cannot process request";
      CRM_Utils_System::civiExit();
    }

    // TODO Check for existing contacts
    
    // TODO Wrap up neatly

    // Exit to stop further rendering/processing of page
    CRM_Utils_System::civiExit();

  }

}
