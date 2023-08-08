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

// тут загружаем другую модель, написанную ранее, так как требует меньше данных для обновления
$loader->model('tool/supplier');
$model_supplier = $registry->get('model_tool_supplier');

 
$data = simplexml_load_file('https://humidorpro.ru/yandex-yml.xml');

$i = 0;
$k = 0;
$j = 0;
foreach ($data->shop->offers->offer as $row) {

    $data['filter_model'] = $row->sku;
    $result = $model->getProducts($data);
    $k ++;

    $find = 0;
    $product_id = 0;
    $price = 0;
    $name = '';

    // дополнительная проверка, так как модель позвращает не полное сходство, а вхождение
    foreach($result as $product) {
        if ($product['model'] == $row->sku) {
            $find = 1;
            $product_id = $product['product_id'];
            $price = $product['price'];
            $name = $product['name'];
        }
    }


    if(!$find) {
        echo 'Товара нет: ' . $row->name;
        echo ' (' .  getCategory($row->categoryId, $data) . ')';
        $i++;
        echo ' - Нужно добавить: ' . $i . ' товаров' .PHP_EOL;
    } else {
        $old_price = intval($price);
        $new_price = generatePrice($row->price);
        if($new_price != $old_price) {
            $j++;
            echo 'Обновление цены: ' . $name . ' "' . $row->sku .  '"';
            echo ' (с ' . $old_price . ' на ' . $new_price . ') ';
            echo ' - Нужно обновить: ' . $j . ' товаров' .PHP_EOL;
            $data['model'] = $row->sku;
            $data['price'] = $new_price;
            $data['quantity'] = 20;
 
            // $model_supplier->editProduct($product_id, $data);
            
        }
    }
}



echo 'Всего товаров: ' . $k;


function generatePrice($supplier_price) : int {
    if(intval($supplier_price) < 6000) {
        return $supplier_price * 2;
    } else {
        return  $supplier_price * 1.5;
    }
}


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