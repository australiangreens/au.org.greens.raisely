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
      'currency', // 3 letter currency code
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

   public static function _parseAddressforContact($donor, $contactId) {
      $stateId = self::_lookupStateId($donor['private.state']);
      $countryId = self::_lookupCountryId($donor['private.country']);
      $primaryAddress = civicrm_api3('Address', 'getSingle', array('contact_id' => $contactId, 'is_primary' => 1));
      $params = array(
        'state_province_id' => $stateId,
        'country_id' => $countryId,
        'contact_id' => $contactId,
      );
      $keys = array(
        'street_address' => 'private.street_address',
        'postal_code' => 'private.postcode',
        'city' => 'private.suburb'
      );
      foreach ($keys as $civiField => $raisleyField) {
        $params[$civiField] = $donor[$raisleyField];
      }
      $addressIsMatched = TRUE;
      foreach ($params as $key => $value) {
        if ($addressIsMatched) { $addressIsMatched = ($value == $primaryAddress[$key]);}
      }
      if (!$addressIsMatched) {
        try {
          $previousAddress = civicrm_api3('Address', 'getSingle', array('contact_id' => $contactId, 'location_type_id' => 'Previous'));
        }
        catch (CiviCRM_API3_Exception $e) {
          $previousAddress = array();
        }
        if (!empty($previousAddress)) {
          $note = "Raisely Extension deleted the following previous address of \n {$previousAddress['street_address']} {$previousAddress['city']} {$previousAddress['state_province_id']} {$previousAddress['postal_code']} {$previousAddress['country_id']}";
          civicrm_api3('Note', 'create', array(
            'contact_id' => $contact_id,
            'entity_id' => $entity_id,
            'entity_table' => 'civicrm_contact',
            'subject' => 'Previous Address deleted by Raisely Extension',
            'note' => $note,
          ));
          civicrm_api3('Address', 'Delete', array('id' => $previousAddress['id']));
        }
        civicrm_api3('Address', 'create', array('id' => $primaryAddress['id'], 'is_primary' => 0, 'location_type_id' => 'Previous'));
        civicrm_api3('Address', 'create', $params);
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

    // Check that the action is "donation"
    if ( $data['action'] != 'donation') {
      echo "This isn't a donation";
      CRM_Utils_System::civiExit();
    }

    $donor = self::_parseDonor($data);
    if (is_null($donor)) {
      echo "Bad donor data - cannot process request";
      CRM_Utils_System::civiExit();
    }
    
    // Parse contribution data 

    $contribution = self::_parseContribution($data);
    if (is_null($contribution)) {
      echo "Bad contribution data - cannot process request";
      CRM_Utils_System::civiExit();
    }

    // Check for existing contacts
    $result = civicrm_api3('Contact', 'get', array(
      'first_name' => $donor['first_name'],
      'last_name' => $donor['last_name'],
      'email' => $donor['email'],
      'options' => array('sort' => 'created_date'),
      'sequential' => 1,
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
          'location_type_id' => 'Billing',
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
        $contactId = $result['id'];
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
      $contactId = $result['id'];
      self::_parseAddressforContact($donor, $contactId);
    }
    else {
      $contactId = $result['values'][0]['id'];
      self::_parseAddressforContact($donor, $contactId);
    }
 
    // TODO Record contribution

    
    // TODO Wrap up neatly

    // Exit to stop further rendering/processing of page
    CRM_Utils_System::civiExit();
    }
}
