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

$RESTRICTED_BRANDS = array(
    'Aficionado',
    'Lotus',
    'Qualityimport',
    'Xikar',
    'Boveda',
    'Habanos',
    'Lotus',
    'Vertigo',
    'Aficionado',
    'Elie Bleu',
    'Qualityimport',
    'Savoy'
);
$RESTRICTED_PRODUCT = array(
    'Подарочная упаковка La Aurora на 3 сигары',
    'Подарочная упаковка на 4-5 сигар',
    'Подарочная карта',
    'Подарочный пенал на 2 сигары'
);
$RESTRICTED_CATEGORY_SUPPLIER = array('УЦЕНКА');

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

$loader->model('catalog/manufacturer');
$model_manufacturer = $registry->get('model_catalog_manufacturer');

 
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
    $model_sku = '';
    $product_data = array();

    // дополнительная проверка, так как модель позвращает не полное сходство, а вхождение
    foreach($result as $product) {
        if ($product['model'] == $row->sku) {
            $find = 1;
            $product_id = $product['product_id'];
            $price = $product['price'];
            $name = $product['name'];
            $model_sku = $product['model'];
            $product_data = $product;
            // print_r($model->getProductAttributes($product_id));
        }
    }


    if(!$find) {
        // echo 'Товара нет: ' . $row->name . ' ' . $row->sku;
        // echo ' (' .  getCategory($row->categoryId, $data) . ')';
        // $i++;
        // echo ' - Нужно добавить: ' . $i . ' товаров' .PHP_EOL;
        if($row->sku == '151-10-BlackU') {
            $image_counter = 0;
            $images = array();
            foreach ($row->picture as $picture) {
                $image = getImage($picture);
                $images[] = $image;
                $image_counter++;
            }

            $product_id = addNewProduct($row, $images);
            echo 'id: ' . $product_id;
        }
    } else {
        if(isAllowedProduct($product_data)) {
            $old_price = intval($price);
            $new_price = generatePrice($row->price);
            if($new_price != $old_price) {
                $j++;
                $data_prod['model'] = $row->sku;
                $data_prod['price'] = $new_price;
                $data_prod['quantity'] = 20;
                $model_supplier->editProduct($product_id, $data_prod);
                echo 'Обновление цены: ' . $name . ' "' . $row->sku .  '"';
                echo ' (с ' . $old_price . ' на ' . $new_price . ') ';
                echo ' - Количесто обновлений: ' . $j . ' товаров' .PHP_EOL;
                
            }
        }
        
    }
}



echo 'Всего товаров: ' . $k;


function generatePrice($supplier_price) : int {
    if(intval($supplier_price) < 6000) {
        return intval($supplier_price) * 2;
    } else {
        return  intval($supplier_price) * 1.5;
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

function getImage($url) : string {
    $url_arr = explode('/', $url);
    $img_name = array_pop($url_arr);

    $ch = curl_init($url);
    $fp = fopen('../image/catalog/' . $img_name, 'wb');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    return 'catalog/' . $img_name;
}

function isAllowedProduct($product) : bool {
    global $RESTRICTED_BRANDS, $RESTRICTED_PRODUCT, $model_manufacturer;
    $manufacturer = $model_manufacturer->getManufacturer($product['manufacturer_id']);

    if(in_array($product['name'], $RESTRICTED_PRODUCT)) {
        return false;
    }

    if(in_array($manufacturer['name'], $RESTRICTED_BRANDS)) {
        return false;
    }    

    return true;

}

function addNewProduct($productData, $images) : int {
    global $model;

    $add_images = array();

    foreach($images as $key => $value) {
        if($key != 0) {
            $add_images[] = array (
                'image' => $value,
                'sort' => 0
            );
        }
    }
    
    $prod_data = array(
        'product_description' => array(
            1 => array(
                'name' => strval($productData->name),
                'description' => strval($productData->description),
                'meta_title' => strval($productData->name),
                'meta_h1' => '',
                'meta_description' => '',
                'meta_keyword' => '',
                'tag' => '',
            )
        ),
        'model' => strval($productData->sku),
        'sku' => "",
        'upc' => "",
        'ean' => "",
        'jan' => "",
        'isbn' => "",
        'mpn' => "",
        'location' => "",
        'price' => generatePrice($productData->price),
        'tax_class_id' => "0",
        'quantity' => "50",
        'minimum' => "1",
        'subtract' => "1",
        'stock_status_id' => "5",
        'shipping' => "1",
        'date_available' => '0000-00-00',
        'length' => "",
        'width' => "",
        'height' => "",
        'length_class_id' => "1",
        'weight' => "",
        'weight_class_id' => "1",
        'status' => 0,
        'sort_order' => "0",
        'manufacturer_id' => "0",
        'image' => $images[0],
        'points' => "",
        // 'product_attribute' => array(
        //     array(
        //         'attribute_id' => 10,
        //         'product_attribute_description' => array(
        //             1 => array(
        //                 'text' => 'test'
        //             )
        //         )
        //     ),
        // ),
        'product_image' => $add_images
    );

    print_r(gettype($images[0]));
    print_r($prod_data);

    // $product_id = $model->autoAddProduct($prod_data);

    $product_id = 2;

    return $product_id;
}