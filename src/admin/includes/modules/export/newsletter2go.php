<?php

defined('_VALID_XTC') or die('Direct Access to this location is not allowed.');

define('MODULE_NEWSLETTER2GO_TEXT_TITLE', 'Newsletter2Go API extension.');
define('MODULE_NEWSLETTER2GO_TEXT_DESC', 'Newsletter2Go API extension.');
define('MODULE_NEWSLETTER2GO_STATUS_DESC', 'Api extension.');
define('MODULE_NEWSLETTER2GO_STATUS_TITLE', 'Status');
define('MODULE_NEWSLETTER2GO_USERNAME_DESC', 'Username for newsletter2go api access.');
define('MODULE_NEWSLETTER2GO_USERNAME_TITLE', 'Username');
define('MODULE_NEWSLETTER2GO_APIKEY_DESC', 'API key for newsletter2go api access.');
define('MODULE_NEWSLETTER2GO_APIKEY_TITLE', 'API key');
define('MODULE_NEWSLETTER2GO_BUTTON_CONNECT', 'Connect to Newsletter2Go');

class Newsletter2Go
{

    const MODULE_NEWSLETTER2GO_VERSION = '4.0.00';
    const MODULE_NEWSLETTER2GO_INTEGRATION_URL = 'https://ui.newsletter2go.com/integrations/connect/MOD/';

    public $code;
    public $title;
    public $description;
    public $version;
    public $enabled;

    public function Newsletter2Go()
    {
        $this->code = 'newsletter2go';
        $this->title = MODULE_NEWSLETTER2GO_TEXT_TITLE;
        $this->description = MODULE_NEWSLETTER2GO_TEXT_DESC;
        $this->sort_order = MODULE_NEWSLETTER2GO_SORT_ORDER;
        $this->enabled = ((MODULE_NEWSLETTER2GO_STATUS == 'True') ? true : false);
        $this->CAT = array();
        $this->PARENT = array();
    }

    public function check()
    {
        if (!isset($this->_check)) {
            $check_query = xtc_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_NEWSLETTER2GO_STATUS'");
            $this->_check = xtc_db_num_rows($check_query);
        }
        return $this->_check;
    }

    public function process($file)
    {
        $username = (isset($_POST['configuration']['MODULE_NEWSLETTER2GO_USERNAME']) ? $_POST['configuration']['MODULE_NEWSLETTER2GO_USERNAME'] : null);
        $apikey = (isset($_POST['configuration']['MODULE_NEWSLETTER2GO_APIKEY']) ? $_POST['configuration']['MODULE_NEWSLETTER2GO_APIKEY'] : null);
        if ($username) {
            xtc_db_query("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '$username' WHERE configuration_key = 'MODULE_NEWSLETTER2GO_USERNAME'");
        }
        
        if ($apikey) {
            xtc_db_query("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '$apikey' WHERE configuration_key = 'MODULE_NEWSLETTER2GO_APIKEY'");
        }
        
    }
    
    public function display()
    {
        $queryParams['version'] = $this->getVersion();
        $queryParams['username'] = $this->getUsername();
        $queryParams['apiKey'] = $this->getApiKey();
        $queryParams['language'] = $this->getDefaultLanguage();
        $queryParams['url'] = HTTP_CATALOG_SERVER;

        $connectUrl = self::MODULE_NEWSLETTER2GO_INTEGRATION_URL . '?' . http_build_query($queryParams);

        return array('text' => '<br />' .
            xtc_button_link(MODULE_NEWSLETTER2GO_BUTTON_CONNECT, $connectUrl, 'target="_blank"') . ' ' .
            xtc_button(BUTTON_SAVE) . ' ' .
            xtc_button_link(BUTTON_CANCEL, xtc_href_link(FILENAME_MODULE_EXPORT, 'set=' . $_GET['set'] . '&module=newsletter2go'))
        );
    }

    public function install()
    {
        $apiKey = md5(base64_encode(microtime()));
        xtc_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value,  configuration_group_id, sort_order, set_function, date_added) values ('MODULE_NEWSLETTER2GO_STATUS', 'True',  '6', '1', 'xtc_cfg_select_option(array(\'True\', \'False\'), ', now())");
        xtc_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value,  configuration_group_id, sort_order, set_function, date_added) values ('MODULE_NEWSLETTER2GO_VERSION', '" . self::MODULE_NEWSLETTER2GO_VERSION . "',  '6', '1', '', now())");
        xtc_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value,  configuration_group_id, sort_order, set_function, date_added) values ('MODULE_NEWSLETTER2GO_USERNAME', 'newsletter2go',  '6', '1', '', now())");
        xtc_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value,  configuration_group_id, sort_order, set_function, date_added) values ('MODULE_NEWSLETTER2GO_APIKEY', '$apiKey',  '6', '1', '', now())");
    }

    public function remove()
    {
        xtc_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "', 'MODULE_NEWSLETTER2GO_STATUS', 'MODULE_NEWSLETTER2GO_VERSION')");
    }

    public function keys()
    {
        return array('MODULE_NEWSLETTER2GO_USERNAME', 'MODULE_NEWSLETTER2GO_APIKEY');
    }

    private function getVersion(){

        $version_query = xtc_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_NEWSLETTER2GO_VERSION'");
        $version = xtc_db_fetch_array($version_query);
        return str_replace('.', '', $version['configuration_value']);
    }

    private function getUsername(){

        $username_query = xtc_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_NEWSLETTER2GO_USERNAME'");
        $username = xtc_db_fetch_array($username_query);
        return $username['configuration_value'];
    }

    private function getApiKey(){

        $apiKey_query = xtc_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_NEWSLETTER2GO_APIKEY'");
        $apiKey = xtc_db_fetch_array($apiKey_query);
        return $apiKey['configuration_value'];
    }

    private function getDefaultLanguage(){

        $language_query = xtc_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'DEFAULT_LANGUAGE'");
        $language = xtc_db_fetch_array($language_query);
        return $language['configuration_value'];
    }


}