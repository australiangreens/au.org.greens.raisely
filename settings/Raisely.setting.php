<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2017                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */
/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2017
 * $Id$
 *
 */
/*
 * Settings metadata file
 */
$financialTypes = array_flip(CRM_Contribute_BAO_Contribution::buildOptions('financial_type_id', 'get', array()));
return array(
  'raisely_default_financial_type' => array(
    'group_name' => 'raisely',
    'group' => 'raisely',
    'name' => 'raisely_default_financial_type',
    'filter' => 'raisely',
    'type' => 'Integer',
    'add' => '1.0',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => ts('Set the default Financial Type to be used by Raisely Webhooks'),
    'title' => ts('Default Financial Type for Raisely'),
    'default' => $financialTypes['Donation'],
    'html_type' => 'Select',
    'html_attributes' => array(),
    'pseudoconstant' => array(
      'callback' => 'CRM_Financial_BAO_FinancialType::getAvailableFinancialTypes',
    ),
    'quick_form_type' => 'Element',
  ),
);
