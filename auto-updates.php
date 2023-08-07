<?php
if (is_file('../admin/config.php')) {
	require_once('../admin/config.php');		
}

// Startup
require_once(DIR_SYSTEM . 'startup.php');

set_time_limit(0);

ini_set('memory_limit', '999M');
ini_set('set_time_limit', '0');

error_reporting(1);

// Registry
$registry = new Registry();

// Config
$config = new Config();
$registry->set('config', $config);

// Database
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
$registry->set('db', $db);

// Loader
$loader = new Loader($registry);
$registry->set('load', $loader);

// Front Controller
$controller = new Front($registry);

// Url
$url = new Url(HTTP_SERVER, $config->get('config_secure') ? HTTPS_SERVER : HTTP_SERVER);
$registry->set('url', $url);

// Language
$languages = array();

$query = $db->query("SELECT * FROM `" . DB_PREFIX . "language`");

foreach ($query->rows as $result) {
	$languages[$result['code']] = $result;
}

$config->set('config_language_id', 1);

$loader->model('catalog/product');
$model = $registry->get('model_catalog_product');

 
$data = simplexml_load_file('https://humidorpro.ru/yandex-yml.xml');

$i = 0;
$k = 0;
foreach ($data->shop->offers->offer as $row) {

    $data['filter_model'] = $row->sku;
    $result = $model->getProducts($data);
    $k ++;


    if(!count($result)) {
        echo 'Товара нет: ' . $row->name;
        echo ' (' .  getCategory($row->categoryId, $data) . ')';
        $i++;
        echo ' - Нужно добавить: ' . $i . ' товаров' .PHP_EOL;
    }
}



echo 'Всего товаров: ' . $k;



function getCategory($category_id, $data, $category_name = '') : string {
    foreach ($data->shop->categories->category as $row) {
        if(intval($row['id']) == intval($category_id)) {
            if($row['parentId']) {
                return getCategory($row['parentId'], $data, $category_name);
            } else {
                $category_name = $row;
                return $category_name; 
            }
        }
    }    
}