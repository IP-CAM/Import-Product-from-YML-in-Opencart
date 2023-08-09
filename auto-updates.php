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

$loader->model('catalog/filter');
$model_filter = $registry->get('model_catalog_filter');

 
$data = simplexml_load_file('https://humidorpro.ru/yandex-yml.xml');

$i = 0;
$k = 0;
$j = 0;
// foreach ($data->shop->offers->offer as $row) {

//     $data['filter_model'] = $row->sku;
//     $result = $model->getProducts($data);
//     $k ++;

//     $find = 0;
//     $product_id = 0;
//     $price = 0;
//     $name = '';
//     $model_sku = '';
//     $product_data = array();

//     // дополнительная проверка, так как модель позвращает не полное сходство, а вхождение
//     foreach($result as $product) {
//         if ($product['model'] == $row->sku) {
//             $find = 1;
//             $product_id = $product['product_id'];
//             $price = $product['price'];
//             $name = $product['name'];
//             $model_sku = $product['model'];
//             $product_data = $product;
//             // print_r($model->getProductAttributes($product_id));
//         }
//     }


//     if(!$find) {
//         $main_cat = getCategory($row->categoryId, $data);
//         if(!in_array($main_cat, $RESTRICTED_CATEGORY_SUPPLIER)) {
//             echo 'Товара нет: ' . $row->name . ' ' . $row->sku;
//             echo ' (' .  getCategory($row->categoryId, $data) . ')';
//             $i++;
//             echo ' - Нужно добавить: ' . $i . ' товаров' .PHP_EOL;

//             $image_counter = 0;
//             $images = array();
//             foreach ($row->picture as $picture) {
//                 $image = getImage($picture);
//                 $images[] = $image;
//                 $image_counter++;
//                 echo 'Добавили изображение для товара: ' . $row->name . ' (' . $image . ' ) - ' . $image_counter . PHP_EOL;
//             }

//             $product_id = addNewProduct($row, $images);
//         }
       
//     } else {
//         echo 'Товар есть в нашей системе, проверяем разрешено ли нам его обновлять' . PHP_EOL;
//         if(isAllowedProduct($product_data)) {
//             $old_price = intval($price);
//             $new_price = generatePrice($row->price);
//             if($new_price != $old_price) {
//                 $j++;
//                 $data_prod['model'] = $row->sku;
//                 $data_prod['price'] = $new_price;
//                 $data_prod['quantity'] = 20;
//                 $model_supplier->editProduct($product_id, $data_prod);
//                 echo 'Обновление цены: ' . $name . ' "' . $row->sku .  '"';
//                 echo ' (с ' . $old_price . ' на ' . $new_price . ') ';
//                 echo ' - Количесто обновлений: ' . $j . ' товаров' .PHP_EOL;
                
//             } else {
//                 echo 'Цена на товар: ' . $product_data['name'] . ' не изменилась' . PHP_EOL;
//             }
//         } else {
//             echo 'Товар не этого поставщика: ' . $product_data['name'] . PHP_EOL;
//         }
        
//     }
// }



// echo 'Всего товаров: ' . $k;


updatingProductStocks();

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

    echo 'Начали добавлять товар: ' . strval($productData->name) . PHP_EOL;

    $product_filter = generateFilters($productData->param);
    $manufacturer_id = generateManufacturer($productData->param);
    $price = generatePrice($productData->price);
    
    $prod_data = array(
        'product_description' => array(
            1 => array(
                'name' => strval($productData->name) . ' (autoadd)',
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
        'price' => $price,
        'tax_class_id' => "0",
        'quantity' => "50",
        'minimum' => "1",
        'subtract' => "0",
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
        'manufacturer_id' => $manufacturer_id,
        'image' => $images[0],
        'points' => "",
        'product_image' => $add_images,
        'product_filter' => $product_filter
    );

    $product_id = $model->autoAddProduct($prod_data);

    echo 'Созадил товар: ' . strval($productData->name) . ' (autoadd)' . ' ('.strval($productData->sku).') ' . ' || Закупочная цена: ' . $productData->price . ' || Цена в магазине: ' . $price . PHP_EOL;

    return $product_id;
}

function generateFilters($params) : array {
    global $model_filter;
    $filter_info = array();

    foreach($params as $param) {
        if($param['name'] == 'Материал') {
            $filter_name = explode(', ', mb_strtolower(strval($param), 'utf-8'));
            $filter_name = implode(';', $filter_name);
            $data = array(
                'filter_name' => $filter_name,
                'filter_group_id' => 10
            );


            $existing_filters = $model_filter->getFilters($data);

            $flag = 0;
            $filter = array();

            foreach($existing_filters as $existing_filter) {
                if($filter_name == $existing_filter['name']) {
                    $flag = 1;
                    $filter = $existing_filter;
                }
            }

            if($flag) {
                $filter_info[] = $filter['filter_id'];
            } else {
                $filter_data = array(
                    'filter_group_id' => 10,
                    'filter_description' => array(
                        1 => array(
                            'name' => $filter_name
                        )
                    )
                );
                $filter_id = $model_filter->autoAddFilter($filter_data);
                $filter_info[] = $filter_id;
            }

            echo 'Добавили фильтр по материалу: ' . $filter_name . PHP_EOL;
        }
        if($param['name'] == 'Производитель') {
            $data_array = explode(', ', mb_strtolower(strval($param), 'utf-8'));
            $filter_name = '';
            if(count($data_array) == 2) {
                $filter_name = $data_array[1];
            } elseif (count($data_array) == 1) {
                $filter_name = $data_array[0];
            }

            $data = array(
                'filter_name' => $filter_name,
                'filter_group_id' => 5
            );

            $existing_filters = $model_filter->getFilters($data);

            $flag = 0;
            $filter = array();

            foreach($existing_filters as $existing_filter) {
                if($filter_name == $existing_filter['name']) {
                    $flag = 1;
                    $filter = $existing_filter;
                }
            }

            if($flag) {
                $filter_info[] = $filter['filter_id'];
            } else {
                $filter_data = array(
                    'filter_group_id' => 5,
                    'filter_description' => array(
                        1 => array(
                            'name' => $filter_name
                        )
                    )
                );
                $filter_id = $model_filter->autoAddFilter($filter_data);
                $filter_info[] = $filter_id;
            }

            echo 'Добавили фильтр по стране: ' . $filter_name . PHP_EOL;

        }
    }
    return $filter_info;
}


function generateManufacturer($params) : int {
    global $model_manufacturer;
    foreach($params as $param) {
        if($param['name'] == 'Производитель') {
            $data_array = explode(', ', mb_strtolower(strval($param), 'utf-8'));
            $manufacturer_name = $data_array[0];
        
            $filter_data = array(
                'filter_name' => $manufacturer_name
            );
        
            $existing_manufacturers = $model_manufacturer->getManufacturers($filter_data);
        
            $flag = 0;
            $manufacturer = array();
        
            foreach($existing_manufacturers as $existing_manufacturer) {
                if($manufacturer_name == $existing_manufacturer['name']) {
                    $flag = 1;
                    $manufacturer = $existing_manufacturer;
                }
            }

            if($flag) {
                echo 'Добавили производителя: ' . $manufacturer_name . PHP_EOL;
                return $manufacturer['manufacturer_id'];
            } else {
                $data = array(
                    'name' => $manufacturer_name,
                    'sort_order' => 0,
                    'manufacturer_description' => array(
                        1 => array(
                            'description' => ''
                        )
                    ),
                );

                $manufacturer_id = $model_manufacturer->autoAddManufacturer($data);
                echo 'Добавили производителя: ' . $manufacturer_name . PHP_EOL;
                return $manufacturer_id;
            }
        }
    }
}


function updatingProductStocks() {
    global $model;

    $updating_categories = array(
        87
    );

    $array = $model->getProductsByCategoryId(87);

    print_r(count($array));



}