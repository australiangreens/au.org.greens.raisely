<?php

class CRM_Raisely_Page_Raisely extends CRM_Core_Page {

  private function _parseDonor($json) {
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

  private function _parseContribution($json) {
    // Raisely JSON data contains all information about a contribution
    // Parse that data and return an associate array with the relevant
    // details for a CiviCRM contribution record

    $data = $json['data']['result'];
    $civicrm_match_keys = [
      'id' => 'trxn_id',
      'description' => 'source',
      'amount' => 'total_amount',
      'created' => 'receive_date',
      'currency' => 'currency',
    ];
    $contrib_keys = [
      'id', // Stripe transaction ID
      'status',
      'description', // Simple description of contribution
      'created', // Unix epoch time
      'currency', // 3 letter currency code
    ];
    foreach ($contrib_keys as $key) {
      if (!array_key_exists($key, $data)) {
        return NULL;
      }
      $value = $data[$key];
      if ($key == 'created') {
        $contribution[$civicrm_match_keys[$key]] = date('Y-m-d H:i:s', $value);
      }
      elseif ($key == 'status') {
        if ($value == 'succeeded') {
          $contributionStatuses = CRM_Core_PseudoConstant::get('CRM_Contribute_DAO_Contribution', 'contribution_status_id', array(
            'labelColumn' => 'name',
            'flip' => 1,
          ));
          $contribution['contribution_status_id'] = $contributionStatuses['Completed'];
        }
      }
      elseif ($key == 'currency') {
        $contribution[$civicrm_match_keys[$key]] = strtoupper($data[$key]);
      }
      else {
        $contribution[$civicrm_match_keys[$key]] = $data[$key];
      }
    }
    if (array_key_exists('amount', $json['data'])) {
      $contribution['total_amount'] = CRM_Utils_Money::format($json['data']['amount'] / 100, 'AUD', NULL, TRUE);
    }
    return $contribution;
  }

  public function _lookupStateId($state) {
    $log = New CRM_Utils_SystemLogger();
    try {
      $result = civicrm_api3('Address', 'getoptions', array(
        'field' => 'state_province_id',
        'context' => 'abbreviate',
      ));
    }
    catch (CiviCRM_API3_Exception $e) {
      $log->error('State Abbreviation lookup failed');
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
    $log = New CRM_Utils_SystemLogger();
    try {
      $result = civicrm_api3('Address', 'getoptions', array(
        'field' => 'country_id',
        'context' => 'abbreviate',
      ));
    }
    catch (CiviCRM_API3_Exception $e) {
      $log->error('Country Abbreviation lookup failed');
    }
    $result = array_flip($result['values']);
    if (array_key_exists($country, $result)) {
      return $result[$country];
    }
    else {
      return NULL;
    }

  }

  public static function _parseAddressforContact($donor, $contactId) {
    $log = New CRM_Utils_SystemLogger();
    $stateId = self::_lookupStateId($donor['private.state']);
    $countryId = self::_lookupCountryId($donor['private.country']);
    try {
      $primaryAddress = civicrm_api3('Address', 'getSingle', array('contact_id' => $contactId, 'is_primary' => 1));
    }
    catch (CiviCRM_API3_Exception $e) {
      $log->error('Getting Previous address failed');
    }
    $params = array(
      'state_province_id' => $stateId,
      'country_id' => $countryId,
      'contact_id' => $contactId,
    );
    $keys = array(
      'street_address' => 'private.street_address',
      'postal_code' => 'private.postcode',
      'city' => 'private.suburb',
    );
    foreach ($keys as $civiField => $raisleyField) {
      $params[$civiField] = $donor[$raisleyField];
    }
    $addressIsMatched = TRUE;
    foreach ($params as $key => $value) {
      if ($addressIsMatched) {
        $addressIsMatched = ($value == $primaryAddress[$key]);
      }
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
        try {
          civicrm_api3('Note', 'create', array(
            'contact_id' => $contactId,
            'entity_id' => $contactId,
            'entity_table' => 'civicrm_contact',
            'subject' => 'Previous Address deleted by Raisely Extension',
            'note' => $note,
          ));
        }
        catch (CiviCRM_API3_Exception $e) {
          $log->error('Error creating note');
          CRM_Utils_Error::debug_log_message('Error creating note');
          CRM_Utils_Error::debug_var('message', $e->getMessage(), TRUE, TRUE);
        }
        try {
          civicrm_api3('Address', 'Delete', array('id' => $previousAddress['id']));
        }
        catch (CiviCRM_API3_Exception $e) {
          CRM_Utils_Error::debug_log_message('Error deleting previous address');
          CRM_Utils_Error::debug_var('message', $e->getMessage(), TRUE, TRUE);
          $log->error('Error deleting previous address');
        }
      }
      try {
        civicrm_api3('Address', 'create', array('id' => $primaryAddress['id'], 'is_primary' => 0, 'location_type_id' => 'Previous'));
      }
      catch (CiviCRM_API3_Exception $e) {
        $log->error('Error creating Previous address');
        CRM_Utils_Error::debug_log_message('Error creating previous address');
        CRM_Utils_Error::debug_var('message', $e->getMessage(), TRUE, TRUE);

      }
      try {
        $default_location_type = civicrm_api3('LocationType', 'getsingle', array('is_default' => 1));
      }
      catch (CiviCRM_API3_Exception $e) {
        $log->error('No default location type');
        CRM_Utils_Error::debug_log_message('No default location type');
        CRM_Utils_Error::debug_var('message', $e->getMessage(), TRUE, TRUE);
      }
      $params['location_type_id'] = $default_location_type['id'];
      try {
        civicrm_api3('Address', 'create', $params);
      }
      catch (CiviCRM_API3_Exception $e) {
        $log->error('Error creating new primray address');
        CRM_Utils_Error::debug_log_message('No default location type');
        CRM_Utils_Error::debug_var('message', $e->getMessage(), TRUE, TRUE);
      }
    }
  }

  public function run() {
    $log = New CRM_Utils_SystemLogger();
    // Test method - fail gracefully on non-POST methods
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      $log->error('non-POST method detected');
      CRM_Utils_System::civiExit();
    }
    echo "getting data";
    // Get POST data and test that it's JSON
    $data = $_REQUEST;
    if (!($data = $_POST)) {
      $data = json_decode(file_get_contents("php://input"), TRUE);
    }

    if (is_null($data)) {
      $log->error('POST data does not contain JSON');
      CRM_Utils_System::civiExit();
    }
    echo "hello world";
    // Check that the action is "donation"
    if ($data['action'] != 'donation') {
      $log->error('Raisely action is not a donation');
      CRM_Utils_System::civiExit();
    }
    echo "Processing donor";
    $donor = self::_parseDonor($data);
    if (is_null($donor)) {
      $log->error('Bad donor data - cannot process request');
      CRM_Core_Error::debug_log_message('Bad donor data - cannot process request');
      CRM_Core_Error::debug_var('raisely data', $data['data']['result']['metadata'], TRUE, TRUE);
      CRM_Utils_System::civiExit();
    }

    // Parse contribution data.
    echo "Processing Contribution";
    $contribution = self::_parseContribution($data);
    if (is_null($contribution)) {
      $log->error('Bad contribution data - cannot process request');
      CRM_Utils_Error::debug_log_message('Bad contribuiton data');
      CRM_Utils_Error::debug_var('raiseley data', $data['data']['result'], TRUE, TRUE);
      CRM_Utils_System::civiExit();
    }
    echo "Checking duplicates";
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
        $log->error("Uh oh!\n" . $errorMessage . "\n");
        CRM_Core_Error::debug_error('Raisely Contact create error');
        CRM_Core_Error::debug_var('params', $params, TRUE, TRUE);
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
    echo "Creating Contributon";
    $contribution['contact_id'] = $contactId;
    $raisely_FT = Civi::Settings()->get('raisely_default_financial_type');
    if (empty($raisely_FT)) {
      $financialTypes = array_flip(CRM_Contribute_BAO_Contribution::buildOptions('financial_type_id', 'get', array()));
      $raisely_FT = $financialTypes['Donation'];
    }
    $contribution['financial_type_id'] = $raisely_FT;
    $contribution['payment_instrument_id'] = 1;
    echo json_encode($contribution);
    try {
      $result = civicrm_api3('Contribution', 'create', $contribution);
    }
    catch (CIVICRM_API3_Exception $e) {
      // Handle error
      $errorMessage = $e->getMessage();
      $errorCode = $e->getErrorCode();
      $errorData = $e->getExtraParams();
      $log->error("Uh oh!\n" . $errorMessage . "\n");
      echo $errorMessage;
      CRM_Core_Error::debug_log_message($errorMessage);
      CRM_Core_Error::debug_log_message('Raisely Contribution create error');
      CRM_Core_Error::debug_var('params', $contribution, TRUE, TRUE);
      CRM_Utils_System::civiExit();
    }
    echo "Done";
    // Exit to stop further rendering/processing of page
    CRM_Utils_System::civiExit();
  }

}
