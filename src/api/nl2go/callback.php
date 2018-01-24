<?php

chdir('../../');
require('includes/application_top.php');

$authKey = isset($_POST['auth_key']) ? xtc_db_prepare_input($_POST['auth_key']) : 0;
$accessToken = isset($_POST['access_token']) ? xtc_db_prepare_input($_POST['access_token']) : 0;
$refreshToken = isset($_POST['refresh_token']) ? xtc_db_prepare_input($_POST['refresh_token']) : 0;
$companyId = isset($_POST['company_id']) ? xtc_db_prepare_input($_POST['company_id']) : 0;

if (!empty($authKey)) {
    saveConfig('MODULE_NEWSLETTER2GO_AUTHKEY', $authKey);
}

if (!empty($accessToken)) {
    saveConfig('MODULE_NEWSLETTER2GO_ACCESSTOKEN', $accessToken);
}

if (!empty($refreshToken)) {
    saveConfig('MODULE_NEWSLETTER2GO_REFRESHTOKEN', $refreshToken);
}

if (!empty($companyId)) {
    saveConfig('MODULE_NEWSLETTER2GO_COMPANYID', $companyId);
}

echo json_encode(array('success' => true));

function saveConfig($name, $value)
{
    $query = xtc_db_query("SELECT * FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '$name'");
    if (xtc_db_num_rows($query) > 0) {
        xtc_db_query("UPDATE " . TABLE_CONFIGURATION
            . " SET configuration_value = '$value' 
                WHERE configuration_key = '$name'");
    } else {
        xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION
            . " (configuration_key, configuration_value,  configuration_group_id, sort_order, set_function, date_added) 
                VALUES ('$name', '$value',  '6', '1', '', now())");
    }
}