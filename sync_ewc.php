<?php

/*****************************************************************************************************
 * This script will get all (S)EWC's from the remote database, and will create/update corresponding
 * option values in CiviCRM.
 *
 * If the option group ewc_sewc_list does not exist, it will be created.
 *
 * This script can be executed manually, or scheduled via cron.
 *****************************************************************************************************/

// include the db settings of the EWC/SEWC database
require_once 'dbsettings.php';

// bootstrap civicrm
require_once '../../civicrm/civicrm.config.php';
require_once 'CRM/Core/Config.php';
$config = CRM_Core_Config::singleton();

// check if the option group exists
$sql = "select id from civicrm_option_group where name = 'ewc_sewc_list'";
$optionGroupID = CRM_Core_DAO::singleValueQuery($sql);
if (!$optionGroupID) {
  // does not exist, create it
  $params = [
    'sequential' => 1,
    'name' => 'ewc_sewc_list',
    'title' => 'EWC/SEWC list',
    'data_type' => 'String',
  ];
  $optionGroup = civicrm_api3('OptionGroup', 'create', $params);
  $optionGroupID = $optionGroup['id'];
}

// get the remote data
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8', ETUI_WEBFORM_DB_HOST, ETUI_WEBFORM_DB_DB);
$pdo = new PDO($dsn, ETUI_WEBFORM_DB_USER, ETUI_WEBFORM_DB_PWD, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$sqlRemote = "
    select
      body_id
      , concat(body_name, ' (', body_group, ')') bodyname
    from
      active_bodies
    order by
      body_name
";
$stmt = $pdo->query($sqlRemote);

// disable all option values
$sqlDisable = "update civicrm_option_value set is_active = 0 where option_group_id = $optionGroupID";
CRM_Core_DAO::executeQuery($sqlDisable);

// loop over the remote data
while ($row = $stmt->fetch(PDO::FETCH_OBJ)) {
  // prepare the option value item
  $params = [
    'sequential' => 1,
    'option_group_id' => $optionGroupID,
    'value' => $row->body_id,
    'name' => $row->bodyname,
    'is_active' => 1,
  ];

  // see if we have the corresponding option value
  $sqlOptionList = "select * from civicrm_option_value where option_group_id = $optionGroupID and value = '" . $row->body_id . "'";
  $dao = CRM_Core_DAO::executeQuery($sqlOptionList);

  if ($dao->fetch()) {
    // yes, update
    $params['id'] = $dao->id;
  }
  civicrm_api3('OptionValue', 'create', $params);
}
