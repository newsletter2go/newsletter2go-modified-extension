<?php

$tracking_order_query = xtc_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_NEWSLETTER2GO_TRACKING_ORDER'");
$tracking_order = xtc_db_fetch_array($tracking_order_query);

$company_id_query = xtc_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_NEWSLETTER2GO_COMPANYID'");
$company_id = xtc_db_fetch_array($company_id_query);

if (!empty($company_id['configuration_value'])
    && !empty($tracking_order['configuration_value'])
    && $tracking_order['configuration_value'] === 'True'
) {
    if (basename($PHP_SELF) == FILENAME_CHECKOUT_CONFIRMATION) {
        $_SESSION['nl2goOrder'] = array('info' => $order->info, 'products' => $order->products);
    }

    if (basename($PHP_SELF) == FILENAME_CHECKOUT_SUCCESS && !empty($_SESSION['nl2goOrder'])) {
        echo getTrackingScript($orders['orders_id'], $_SESSION['nl2goOrder'], $company_id['configuration_value']);
    }
}

function getTrackingScript($id, $order, $companyId)
{
    $transactionData = [
        'id' => (string)$id,
        'affiliation' => (string)$GLOBALS['current_domain'],
        'revenue' => (string)round($order['info']['total'], 2),
        'shipping' => (string)round($order['info']['shipping_cost'], 2),
        'tax' => (string)round($order['info']['tax'], 2)
    ];

    $script = '<script id="n2g_script"> 
            !function(e,t,n,c,r,a,i) { 
                e.Newsletter2GoTrackingObject=r, 
                e[r]=e[r]||function(){(e[r].q=e[r].q||[]).push(arguments)}, 
                e[r].l=1*new Date, 
                a=t.createElement(n), 
                i=t.getElementsByTagName(n)[0], 
                a.async=1, 
                a.src=c, 
                i.parentNode.insertBefore(a,i) 
            } 
            (window,document,"script","//static-sandbox.newsletter2go.com/utils.js","n2g"); 
            n2g(\'create\', \'' . $companyId . '\'); 
            n2g(\'ecommerce:addTransaction\', ' . json_encode($transactionData) . ');';

    foreach ($order['products'] as $product) {
        $productData = [
            'id' => (string)$id,
            'name' => (string)$product['name'],
            'sku' => (string)(!empty($product['model']) ? $product['model'] : $product['id']),
            'category' => (string)getCategoryNameByProductId($product['id']),
            'price' => (string)round($product['price'], 2),
            'quantity' => (string)$product['quantity']
        ];

        $script .= " 
            n2g('ecommerce:addItem', " . json_encode($productData) . ");";
    }

    return $script . ' 
            n2g(\'ecommerce:send\'); 
        </script>';
}

function getCategoryNameByProductId($id)
{
    $category_name_query = xtc_db_query("
        SELECT c.categories_name FROM " . TABLE_CATEGORIES_DESCRIPTION . " c
        LEFT JOIN " . TABLE_PRODUCTS_TO_CATEGORIES . " p ON c.categories_id = p.categories_id
        WHERE c.language_id = " . $_SESSION['languages_id'] . " AND p.products_id = " . $id
    );

    $category_name = xtc_db_fetch_array($category_name_query);

    return !empty($category_name['categories_name']) ? $category_name['categories_name'] : '';
}