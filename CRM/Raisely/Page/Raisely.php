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
      }
      $donor[$key] = $data[$key];
    }
    $donor['contact_type'] = 'Individual';
    return $donor;
  }

  function _parseContribution($json) {
    // Raisely JSON data contains all information about a contribution
    // Parse that data and return an associate array with the relevant
    // details for a CiviCRM contribution record
    
    $data = $json['data']['result'];
    $contrib_keys = [
      'id', // Stripe transaction ID
      'status', 
      'description', // Simple description of contribution
      'amount', // no decimal point
      'created', // Unix epoch time
      'currency',
    ];

    foreach ($contrib_keys as $key) {
      if (!array_key_exists($key, $data)) {
        return NULL;
      }
      $contribution[$key] = $data[$key];
    }
    return $contribution;
  }

  public function _lookupStateId($state) {
    try {
      $result = civicrm_api3('Address', 'getoptions', array(
        'field' => 'state_province_id',
        'context' => 'abbreviate',
      ));
    }
    catch (CiviCRM_API3_Exception $e) {
      // Handle error here.
      $errorMessage = $e->getMessage();
      $errorCode = $e->getErrorCode();
      $errorData = $e->getExtraParams();
    }
    $result = array_flip($result['values']);
    if (array_key_exists($state, $result)) {
      return $result[$state];
    }
    else {
      return NULL;
    }
  }

  public function _lookupCountryId($country) {
    try {
      $result = civicrm_api3('Address', 'getoptions', array(
        'field' => 'country_id',
        'context' => 'abbreviate',
      ));
    }
    catch (CiviCRM_API3_Exception $e) {
      // Handle error here.
      $errorMessage = $e->getMessage();
      $errorCode = $e->getErrorCode();
      $errorData = $e->getExtraParams();
    }
    $result = array_flip($result['values']);
    if (array_key_exists($country,$result)) {
      return $result[$country];
    }
    else {
      return NULL;
    }

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
    $result = civicrm_api3('Contact', 'get', array(
      'first_name' => $donor['first_name'],
      'last_name' => $donor['last_name'],
      'email' => $donor['email'],
    ));

    if ($result['count'] == 0) {
      // Create new contact
      $stateId = self::_lookupStateId($donor['private.state']);
      $countryId = self::_lookupCountryId($donor['private.country']);
      $params = array(
        'first_name' => $donor['first_name'],
        'last_name' => $donor['last_name'],
        'contact_type' => 'Individual',
        'api.email.create' => array(
          'email' => $donor['email'],
          'is_primary' => 1,
          'location_type_id' => 5, // 'Billing'
        ),
        'api.address.create' => array(
          'location_type_id' => 5,
          'is_primary' => 1,
          'is_billing' => 1,
          'street_address' => $donor['private.street_address'],
          'postal_code' => $donor['private.postcode'],
          'city' => $donor['private.suburb'],
          'state_province_id' => $stateId,
          'country_id' => $countryId,
        ),
     ); 
      try {
        $result = civicrm_api3('Contact', 'create', $params);
        print_r($result);
      }
      catch (CiviCRM_API3_Exception $e) {
        // Handle error
        $errorMessage = $e->getMessage();
        $errorCode = $e->getErrorCode();
        $errorData = $e->getExtraParams();
        echo "Uh oh!\n" . $errorMessage . "\n";
        CRM_Utils_System::civiExit();
      }

    }
    elseif ($result['count'] == 1) {
      // Update existing contact
      reset($result['values']);
      $contactId = key($result['values']);
      $stateId = self::_lookupStateId($donor['private.state']);
      $countryId = self::_lookupCountryId($donor['private.country']);
      $params = array(
        'first_name' => $donor['first_name'],
        'last_name' => $donor['last_name'],
        'contact_type' => 'Individual',
        'contact_id' => $contactId,
        'api.email.create' => array(
          'email' => $donor['email'],
          'is_primary' => 1,
          'location_type_id' => 5, // 'Billing'
        ),
        'api.address.create' => array(
          'location_type_id' => 5,
          'is_primary' => 1,
          'is_billing' => 1,
          'street_address' => $donor['private.street_address'],
          'postal_code' => $donor['private.postcode'],
          'city' => $donor['private.suburb'],
          'state_province_id' => $stateId,
          'country_id' => $countryId,
        ),
      );
      try {
        // This is problematic
        // It will replace an existing address of matching type without 
        // preserving it.
        // TODO refactor to safeguard existing address/email info
        $result = civicrm_api3('Contact', 'replace', $params);
      }
      catch (CiviCRM_API3_Exception $e) {
        // Handle error
        $errorMessage = $e->getMessage();
        $errorCode = $e->getErrorCode();
        $errorData = $e->getExtraParams();
        echo "Uh oh!\n" . $errorMessage . "\n";
        CRM_Utils_System::civiExit();
      }
    }
    else {
      // Need to check addresses
      echo "MORE THAN ONE MATCHING CONTACT";
    }

    
    // TODO Wrap up neatly

    // Exit to stop further rendering/processing of page
    CRM_Utils_System::civiExit();

  }

}
