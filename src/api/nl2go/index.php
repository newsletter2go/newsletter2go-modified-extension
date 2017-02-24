<?php

chdir('../../');
require('includes/application_top.php');

class N2GoApi
{

    /**
     * err-number, that should be pulled, whenever credentials are missing
     */
    const ERRNO_PLUGIN_CREDENTIALS_MISSING = 'int-1-404';

    /**
     * err-number, that should be pulled, whenever credentials are wrong
     */
    const ERRNO_PLUGIN_CREDENTIALS_WRONG = 'int-1-403';

    /**
     * err-number for all other (intern) errors. More Details to the failure should be added to error-message
     */
    const ERRNO_PLUGIN_OTHER = 'int-1-600';

    private $apikey;
    private $username;
    private $connected = false;

    /**
     * Associative array with get parameters
     * @var array
     */
    private $getParams;

    /**
     * Associative array with post parameters
     * @var array
     */
    private $postParams;

    public function __construct($action, $username, $apikey, $getParams = array(), $postParams = array())
    {
        try {
            if (xtc_not_null($apikey) && xtc_not_null($action) && xtc_not_null($username)) {
                $this->apikey = $apikey;
                $this->username = $username;
                $this->getParams = $getParams;
                $this->postParams = $postParams;
                $this->connected = $this->checkApiKey();

                if (!$this->connected['success']) {
                    echo $this->ping();
                } else {
                    switch ($action) {
                        case 'getCustomers':
                            echo $this->getCustomers();
                            break;
                        case 'getCustomerFields':
                            $fields = $this->getCustomerFields();
                            echo json_encode(array('success' => true, 'message' => 'OK', 'fields' => $fields));
                            break;
                        case 'getCustomerGroups':
                            echo $this->getCustomerGroups();
                            break;
                        case 'getCustomerCount':
                            echo $this->getCustomerCount();
                            break;
                        case 'changeMailStatus':
                            echo $this->changeMailStatus();
                            break;
                        case 'getProduct':
                            echo $this->getProduct();
                            break;
                        case 'ping':
                            echo $this->ping();
                            break;
                        case 'getPluginVersion':
                            echo $this->getPluginVersion();
                            break;
                        case 'getLanguages':
                            echo $this->getLanguages();
                            break;
                    }
                }
            } else {
                echo json_encode(array('success' => false, 'message' => 'Error: Bad Request!', 'errorcode' => self::ERRNO_PLUGIN_OTHER));
            }
        } catch (Exception $e) {
            echo json_encode(array('success' => false, 'message' => 'Error: Bad Request!', 'errorcode' => self::ERRNO_PLUGIN_OTHER));
        }
    }

    /**
     * Checks if user has connection
     * @return string
     */
    public function ping()
    {
        return json_encode($this->connected);
    }

    /**
     *
     */
    public function getCustomers()
    {
        $hours = (isset($this->postParams['hours']) ? xtc_db_prepare_input($this->postParams['hours']) : '');
        $subscribed = (isset($this->postParams['subscribed']) ? xtc_db_prepare_input($this->postParams['subscribed']) : '');
        $limit = (isset($this->postParams['limit']) ? xtc_db_prepare_input($this->postParams['limit']) : '');
        $offset = (isset($this->postParams['offset']) ? xtc_db_prepare_input($this->postParams['offset']) : '');
        $emails = (isset($this->postParams['emails']) ? xtc_db_prepare_input($this->postParams['emails']) : array());
        $group = (isset($this->postParams['group']) ? xtc_db_prepare_input($this->postParams['group']) : '');
        $fields = (isset($this->postParams['fields']) ? xtc_db_prepare_input($this->postParams['fields']) : array());

        $conditions = array();
        $customers = array();
        $query = $this->buildCustomersQuery($fields);
        $query .= ' FROM ' . TABLE_CUSTOMERS . ' cu
                    LEFT JOIN ' . TABLE_ADDRESS_BOOK . ' ab ON cu.customers_id = ab.customers_id
                    LEFT JOIN ' . TABLE_COUNTRIES . ' co ON ab.entry_country_id = co.countries_id
                    LEFT JOIN ' . TABLE_NEWSLETTER_RECIPIENTS . ' nr ON cu.customers_email_address = nr.customers_email_address';

        if (xtc_not_null($group)) {
            if ($group == 1) {
                return $this->getGuestSubscribers($subscribed, $fields, $limit, $offset, $emails);
            }

            $conditions[] = 'cu.customers_status = ' . $group;
        }

        if (xtc_not_null($hours)) {
            $time = date('Y-m-d H:i:s', time() - 3600 * $hours);
            $conditions[] = "cu.customers_last_modified >= '$time'";
        }

        if (xtc_not_null($subscribed) && $subscribed) {
            $conditions[] = 'nr.mail_status = ' . $subscribed;
        }

        if (!empty($emails)) {
            $conditions[] = "cu.customers_email_address IN ('" . implode("', '", $emails) . "')";
        }

        if (!empty($conditions)) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }

        if (xtc_not_null($limit)) {
            $offset = (xtc_not_null($offset) ? $offset : 0);
            $query .= ' LIMIT ' . $offset . ', ' . $limit;
        }

       // var_dump($fields); die;
        $customersQuery = xtc_db_query($query);
        $n = xtc_db_num_rows($customersQuery);

        for ($i = 0; $i < $n; $i++) {
            $cs = xtc_db_fetch_array($customersQuery);
            foreach ($cs as $key => $value) {
                $cs[$key] = utf8_encode($value);
            }

            $customers[] = $cs;
        }

        $response = array(
            'success' => true,
            'message' => 'OK',
            'customers' => $customers,
        );

        return json_encode($response);
    }

    public function changeMailStatus()
    {
        $email = (isset($this->postParams['email']) ? xtc_db_prepare_input($this->postParams['email']) : '');
        $status = (isset($this->postParams['status']) ? xtc_db_prepare_input($this->postParams['status']) : 0);
        if (xtc_not_null($email) && $email) {
            xtc_db_query("UPDATE newsletter_recipients SET mail_status = $status WHERE customers_email_address = '$email'");
            $response = (mysql_affected_rows() > 0 ? array('success' => true, 'message' => 'Mail status successfully changed') :
                array('success' => false, 'message' => 'There is no customer with given email'));
        } else {
            $response = array('success' => false, 'message' => 'Invalid parameter for email!');
        }

        return json_encode($response);
    }

    public function getProduct()
    {
        $id = isset($this->postParams['id']) ? xtc_db_prepare_input($this->postParams['id']) : '';
        $lang = isset($this->postParams['lang']) ? xtc_db_prepare_input($this->postParams['lang']) : '';

        if (empty($lang)) {
            $langQuery = xtc_db_query('SELECT configuration_value FROM ' . TABLE_CONFIGURATION . ' WHERE configuration_key = "DEFAULT_LANGUAGE"');
            $langResult = xtc_db_fetch_array($langQuery);
            $lang = $langResult['configuration_value'];
        }

        if (!xtc_not_null($id) || !xtc_not_null($lang)) {
            return json_encode(array('success' => false, 'message' => 'Invalid or missing parameters for getProduct request!'));
        }

        $query = 'SELECT 
                pr.products_id as id, 
                pr.products_ean as ean,
                pr.products_image as images,
                pr.products_price as oldPrice, 
                pr.products_price as newPrice, 
                pr.products_price as oldPriceNet, 
                pr.products_price as newPriceNet, 
                pr.products_model as model, 
                mf.manufacturers_name as brand, 
                pd.products_name as name, 
                pd.products_short_description as shortDescription, 
                pd.products_description as description, 
                max(tr.tax_rate) as vat 
                FROM products pr 
                LEFT JOIN tax_rates tr ON pr.products_tax_class_id = tr.tax_class_id 
                LEFT JOIN products_description pd ON pr.products_id = pd.products_id 
                LEFT JOIN manufacturers mf ON mf.manufacturers_id = pr.manufacturers_id 
                LEFT JOIN languages ln ON pd.language_id = ln.languages_id '
            . "WHERE pr.products_id = $id AND ln.code = '$lang' GROUP BY pr.products_id";

        $productsQuery = xtc_db_query($query);
        $product = xtc_db_fetch_array($productsQuery);
        if ($product['id'] === $id) {
            if ($product['vat']) {
                $product['oldPrice'] = $product['newPrice'] = $product['oldPriceNet'] * (1 + $product['vat'] * 0.01);
                $product['vat'] = round($product['vat'] * 0.01, 2);
            }

            $product['oldPrice'] = $product['newPrice'] = round($product['oldPrice'], 2);
            $product['oldPriceNet'] = $product['newPriceNet'] = round($product['oldPriceNet'], 2);
            $product['url'] = xtc_href_link('', '', 'NONSSL', false);
            $product['link'] = FILENAME_PRODUCT_INFO . '?products_id=' . $id;

            $product['images'] = ($product['images'] ? array($product['url'] . DIR_WS_ORIGINAL_IMAGES . $product['images']) : array());
            $query = 'SELECT image_name FROM products_images WHERE products_id = ' . $id;
            $imagesQuery = xtc_db_query($query);
            $n = xtc_db_num_rows($imagesQuery);
            for ($i = 0; $i < $n; $i++) {
                $image = xtc_db_fetch_array($imagesQuery);
                $product['images'][] = $product['url'] . DIR_WS_ORIGINAL_IMAGES . $image['image_name'];
            }
            $response = array(
                'success' => true,
                'message' => 'OK',
                'product' => $product,
            );
        } else {
            $response = array(
                'success' => false,
                'message' => 'Product with given parameters not found.',
                'product' => null,
            );
        }

        return json_encode($response);
    }

    public function getPluginVersion()
    {
        $response = array(
            'success' => false,
            'message' => 'Error retrieving version number.',
            'version' => null,
        );
        $table = TABLE_CONFIGURATION;

        $query = "SELECT * FROM $table WHERE configuration_key = 'MODULE_NEWSLETTER2GO_VERSION'";
        $versionQuery = xtc_db_query($query);
        $version = xtc_db_fetch_array($versionQuery);
        if (!empty($version)) {
            $response['success'] = true;
            $response['message'] = 'OK';
            $response['version'] = str_replace('.', '', $version['configuration_value']);
        }

        return json_encode($response);
    }

    /**
     * Returns json encode array of shop's languages
     * @return string
     */
    public function getLanguages()
    {
        $languages = array();
        $response = array(
            'success' => true,
            'message' => 'OK',
        );
        $table = TABLE_LANGUAGES;

        try {
            $langQuery = xtc_db_query("SELECT * FROM $table");
            $n = xtc_db_num_rows($langQuery);
            for ($i = 0; $i < $n; $i++) {
                $lang = xtc_db_fetch_array($langQuery);
                $languages[$lang['code']] = $lang['name'];
            }

            $response['languages'] = $languages;

        } catch (Exception $exc) {
            $response['success'] = false;
            $response['message'] = 'Failed to retrieve languages';
        }

        return json_encode($response);
    }

    /**
     * Returns json encode customer groups with names in shops default language
     * @return string
     */
    public function getCustomerGroups()
    {
        $groups = array();
        $table = TABLE_CUSTOMERS_STATUS;
        $query = "SELECT customers_status_id as id,
                         customers_status_name as name,
                         '' as description
                  FROM $table
                  WHERE language_id IN (
                       SELECT languages_id
                       FROM configuration c
                            LEFT JOIN languages l ON l.code = c.configuration_value
                       WHERE configuration_key = 'DEFAULT_LANGUAGE'
                   )";

        $groupsQuery = xtc_db_query($query);
        $n = xtc_db_num_rows($groupsQuery);
        for ($i = 0; $i < $n; $i++) {
            $groups[] = xtc_db_fetch_array($groupsQuery);
        }

        return json_encode(array('success' => true, 'message' => 'OK', 'groups' => $groups));
    }

    /**
     * Returns json encode customer count based on group and subscribed parameters
     * @return string
     */
    public function getCustomerCount()
    {
        $group = (isset($this->postParams['group']) ? xtc_db_prepare_input($this->postParams['group']) : '');
        $subscribed = (isset($this->postParams['subscribed']) ? xtc_db_prepare_input($this->postParams['subscribed']) : '');
        $conditions = array();
        $query = 'SELECT COUNT(*) AS total FROM customers c';
        if (xtc_not_null($group)) {
            $conditions[] = 'c.customers_status = ' . $group;
        }

        if (xtc_not_null($subscribed) && $subscribed) {
            $query .= ' LEFT JOIN newsletter_recipients n ON n.customers_email_address = c.customers_email_address ';
            $conditions[] = 'n.mail_status = 1';
        }

        $where = empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions);

        $query = $query . $where;
        $countQuery = xtc_db_query($query);
        $result = xtc_db_fetch_array($countQuery);
        $total = $result['total'];

        // Every guest that subscribes will have customer group id 1
        if (!xtc_not_null($group) || $group == 1) {
            $query = 'SELECT COUNT(*) AS total FROM newsletter_recipients WHERE customers_status = 1 AND customers_id = 0';
            $countQuery = xtc_db_query($query);
            $result = xtc_db_fetch_array($countQuery);
            $total += $result['total'];
        }

        return json_encode(array('success' => true, 'message' => 'OK', 'customers' => $total));
    }

    /**
     * Returns customer fields array
     * @return array
     */
    public function getCustomerFields()
    {
        $fields = array();
        $fields['cu.customers_id'] = $this->createField('cu.customers_id', 'Customer Id.', 'Integer');
        $fields['cu.customers_gender'] = $this->createField('cu.customers_gender', 'Gender');
        $fields['cu.customers_firstname'] = $this->createField('cu.customers_firstname', 'First name');
        $fields['cu.customers_lastname'] = $this->createField('cu.customers_lastname', 'First name');
        $fields['cu.customers_dob'] = $this->createField('cu.customers_dob', 'Date of birth');
        $fields['cu.customers_email_address'] = $this->createField('cu.customers_email_address', 'E-mail address');
        $fields['cu.customers_telephone'] = $this->createField('cu.customers_telephone', 'Phone number');
        $fields['cu.customers_fax'] = $this->createField('cu.customers_fax', 'Fax');
        $fields['cu.customers_date_added'] = $this->createField('cu.customers_date_added', 'Date created');
        $fields['cu.customers_last_modified'] = $this->createField('cu.customers_last_modified', 'Date last modified');
        $fields['cu.customers_warning'] = $this->createField('cu.customers_warning', 'Warning message');
        $fields['cu.customers_status'] = $this->createField('cu.customers_status', 'Customer group Id.');
        $fields['cu.payment_unallowed'] = $this->createField('cu.payment_unallowed', 'Payment unallowed');
        $fields['cu.shipping_unallowed'] = $this->createField('cu.shipping_unallowed', 'Shipping unallowed');
        $fields['nr.mail_status'] = $this->createField('nr.mail_status', 'Subscribed', 'Boolean');
        $fields['ab.entry_company'] = $this->createField('ab.entry_company', 'Company');
        $fields['ab.entry_street_address'] = $this->createField('ab.entry_street_address', 'Street');
        $fields['ab.entry_city'] = $this->createField('ab.entry_city', 'City');
        $fields['co.countries_name'] = $this->createField('co.countries_name', 'Country');

        return $fields;
    }

    /**
     * @param string $hours
     * @param string $subscribed
     * @param array $fields
     * @param string $limit
     * @param string $offset
     * @param array $emails
     * @return string
     */
    public function getGuestSubscribers($subscribed = '', $fields = array(), $limit = '', $offset = '', $emails = array())
    {
        $map = array(
            'nr.mail_status' => 'mail_status',
            'cu.customers_email_address' => 'customers_email_address',
            'cu.customers_date_added' => 'date_added',
        );
        $conditions = array('customers_status = 1');
        $customers = array();
        $query = $this->buildCustomersQuery($fields, $map) . ' FROM ' . TABLE_NEWSLETTER_RECIPIENTS;

        if (xtc_not_null($subscribed) && $subscribed) {
            $conditions[] = 'mail_status = ' . $subscribed;
        }

        if (!empty($emails)) {
            $conditions[] = "customers_email_address IN ('" . implode("', '", $emails) . "')";
        }

        if (!empty($conditions)) {
            $query .= ' WHERE ' . implode(' AND ', $conditions);
        }

        if (xtc_not_null($limit)) {
            $offset = (xtc_not_null($offset) ? $offset : 0);
            $query .= ' LIMIT ' . $offset . ', ' . $limit;
        }

        $customersQuery = xtc_db_query($query);
        $n = xtc_db_num_rows($customersQuery);
        for ($i = 0; $i < $n; $i++) {
            $customers[] = xtc_db_fetch_array($customersQuery);
        }

        return json_encode(array('success' => true, 'message' => 'OK', 'customers' => $customers));
    }

    /**
     * Checks if there is an enabled user with given api key
     * @return array (
     *      'result'    =>   true|false,
     *      'message'   =>   result message,
     * )
     */
    private function checkApiKey()
    {
        $response = array(
            'success' => false,
            'message' => 'Authentication failed!',
            'errorcode' => self::ERRNO_PLUGIN_CREDENTIALS_WRONG,
        );
        $table = TABLE_CONFIGURATION;
        $usernameQuery = xtc_db_query("SELECT * FROM $table WHERE configuration_key = 'MODULE_NEWSLETTER2GO_USERNAME'");
        $user = xtc_db_fetch_array($usernameQuery);
        $apikeyQuery = xtc_db_query("SELECT * FROM $table WHERE configuration_key = 'MODULE_NEWSLETTER2GO_APIKEY'");
        $apikey = xtc_db_fetch_array($apikeyQuery);

        if (!empty($user) && !empty($apikey)) {
            $connected = $user['configuration_value'] == $this->username && $apikey['configuration_value'] == $this->apikey;
            if ($connected) {
                $response['success'] = true;
                $response['message'] = 'pong';
                unset($response['errorcode']);
            }
        }

        return $response;
    }

    /**
     * Helper function to create field array
     * @param $id
     * @param $name
     * @param string $type
     * @param string $description
     * @return array
     */
    private function createField($id, $name, $type = 'String', $description = '')
    {
        return array('id' => $id, 'name' => $name, 'description' => $description, 'type' => $type);
    }

    /**
     * @param array $fields
     * @param array $fieldMap
     * @return string
     */
    private function buildCustomersQuery($fields = array(), $fieldMap = array())
    {
        $select = array();

        if (empty($fields)) {
            $fields = array_keys($this->getCustomerFields());
        } else if (!in_array('cu.customers_id', $fields)) {
            //customer Id must always be present
            $fields[] = 'cu.customers_id';
        }

        foreach ($fields as $field) {
            if (empty($fieldMap)) {
                $select[] = "$field AS '$field'";
            } else {
                $value = (array_key_exists($field, $fieldMap) ? $fieldMap[$field] : 'NULL');
                $select[] = "$value AS '$field'";
            }
        }

        return 'SELECT ' . implode(', ', $select);
    }
}

$username = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
$apikey = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
$action = isset($_POST['action']) ? $_POST['action'] : '';

if (!$username && isset($_POST['username'])) {
    $username = $_POST['username'];
}

if (!$apikey && isset($_POST['apikey'])) {
    $apikey = $_POST['apikey'];
}

header('Content-Type: application/json');
if (!isset($username) || !isset($apikey)) {
    echo json_encode(array('success' => false, 'message' => 'Error: Credentials are missing!', 'errorcode' => N2GOApi::ERRNO_PLUGIN_CREDENTIALS_MISSING));
    exit;
}

$api = new N2GoApi($action, $username, $apikey, $_GET, $_POST);