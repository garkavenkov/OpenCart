<?php

namespace Garkavenkov\Opencart;

use Garkavenkov\DBConnector\DBConnect;

class Opencart
{
    /**
     * Prefix used in Opencart database table name
     * @var string
     */
    private static $table_prefix;

    /**
     * Database handler
     * @var PDO handler
     */
    private static $dbh;

    /**
     * Default language id
     * @var int
     */
    private static $language_id;

    /**
     * Store id
     * @var int
     */
    private static $store_id;

    /**
     * Layout id
     * @var int
     */
    private static $layout_id;

    /**
     * Set settings
     * @param  string       $prefix Table name prefix
     * @param  DBConnect    $dbh    Instance of DBConnect class
     * @return void
     */
    public static function initiate(string $prefix, DBConnect $dbh)
    {
        self::$table_prefix = $prefix;
        self::$dbh = $dbh;
        self::$store_id = 0;
        self::$layout_id = 0;
    }

    /**
     * Returns default language id
     * @return int  Language Id
     */
    public static function getLanguageId()
    {
        if (!self::$language_id) {
            $sql  = "SELECT `language_id` ";
            $sql .= "FROM `" . self::$table_prefix . "language` ";
            $sql .= "WHERE `code` = (";
            $sql .=     "SELECT `value` ";
            $sql .=     "FROM `". self::$table_prefix . "setting` ";
            $sql .=     "WHERE `key` ='config_language' and `code`='config')";

            self::$language_id = self::$dbh->getFieldValue($sql, 'language_id');
        }
        return self::$language_id;
    }

    /**
     * Returns Category id
     * @param  string $category_path    Category path
     * @return int                      Category id
     */
    public static function getCategoryId($category_path)
    {
        // Delete excess spaces in the category name
        $category_path = preg_replace('/\s+/', ' ', $category_path);
        $categories = explode('/', $category_path);
        $category_id = null;
        $parent_category_id = 0;

        $sql  = "SELECT cd.category_id, c.parent_id ";
        $sql .= "FROM `" . self::$table_prefix . "category_description` cd ";
        $sql .= "INNER JOIN `" . self::$table_prefix . "category` c ";
        $sql .=     "USING (category_id) ";
        $sql .= "WHERE cd.name = :category_name and ";
        $sql .=     "cd.language_id = " . self::getLanguageId() . " and ";
        $sql .=     "c.parent_id = :parent_category_id";
        $stmt = self::$dbh->prepare($sql);
        $i=1;
        foreach ($categories as $category) {
            if ($stmt->execute(array(
                ":category_name" => $category,
                ":parent_category_id" => $parent_category_id
            ))) {
                $res = $stmt->fetch(\PDO::FETCH_ASSOC);
                if (!$res) {
                    return;
                }
                $category_id = $res['category_id'];
                $parent_category_id =  $category_id;
                $i++;
            }
        }
        return $category_id;
    }

    /**
     * Returns next value for sort_order field
     * @param  string $table_name Table name for search next value
     * @return int                Next value
     */
    public static function getNextSortOrderValue($table_name)
    {
        $sql  = "SELECT MAX(`sort_order`)+1 AS `next` ";
        $sql .= "FROM `" . self::$table_prefix. $table_name ."`";

        $sort_order = self::$dbh->getFieldValue($sql, 'next');
        if (!$sort_order) {
            $sort_order = 0;
        }
        return $sort_order;
    }

    /**
     * Import product
     * @param  array  $product Information about product/products
     * @return void
     */
    public static function importProducts(array $products)
    {
        // SQL statement for 'product' table
        $sql_product  = "INSERT INTO `" . self::$table_prefix . "product` (";
        $sql_product .=     "`model`, ";              // Модель
        $sql_product .=     "`sku`, ";                // Артикул
        $sql_product .=     "`upc`, ";                // Универсальный код товара
        $sql_product .=     "`ean`, ";                // Европейский номер товара
        $sql_product .=     "`jan`, ";                // Японский штрихкод
        $sql_product .=     "`isbn`, ";               // Номер книжного издания
        $sql_product .=     "`mpn`, ";                // Номер партии изготовителя
        $sql_product .=     "`location`, ";           // Расположение
        $sql_product .=     "`quantity`, ";           // Количество = 0
        $sql_product .=     "`stock_status_id`, ";    // Отсутсвие на складе
        $sql_product .=     "`image`, ";              // Изображение = NULL
        $sql_product .=     "`manufacturer_id`, ";    // Id производителя
        $sql_product .=     "`shipping`, ";           // Необходима доставка = 1
        $sql_product .=     "`price`, ";              // Цена
        $sql_product .=     "`points`, ";             // Баллы = 0
        $sql_product .=     "`tax_class_id`, ";       // Налог
        $sql_product .=     "`date_available`, ";     // Дата поступления
        $sql_product .=     "`weight`, ";             // Вес = '0.00'
        $sql_product .=     "`weight_class_id`, ";    // Единица веса = 0
        $sql_product .=     "`length`, ";             // Длина = '0.00'
        $sql_product .=     "`width`, ";              // Ширина = '0.00'
        $sql_product .=     "`height`, ";             // Высота = '0.00'
        $sql_product .=     "`length_class_id`, ";    // Единица длины = 0
        $sql_product .=     "`subtract`, ";           // Вычитать со склада = 1
        $sql_product .=     "`minimum`, ";            // Мин. кол-во товара для заказа =1
        $sql_product .=     "`sort_order`, ";         // Порядок сортировки = 0
        $sql_product .=     "`status`," ;             // Статус = 0
        $sql_product .=     "`viewed`, ";             // Кол-во просмотров = 0
        $sql_product .=     "`date_added`, ";         // Дата создания
        $sql_product .=     "`date_modified`";        // Дата изменения
        $sql_product .= ") VALUES ( ";
        $sql_product .=     ":model, ";
        $sql_product .=     ":sku, ";
        $sql_product .=     ":upc, ";
        $sql_product .=     ":ean, ";
        $sql_product .=     ":jan, ";
        $sql_product .=     ":isbn, ";
        $sql_product .=     ":mpn, ";
        $sql_product .=     ":location, ";
        $sql_product .=     ":quantity, ";
        $sql_product .=     ":stock_status_id, ";
        $sql_product .=     ":image, ";
        $sql_product .=     ":manufacturer_id, ";
        $sql_product .=     ":shipping, ";
        $sql_product .=     ":price, ";
        $sql_product .=     ":points, ";
        $sql_product .=     ":tax_class_id, ";
        $sql_product .=     ":date_available, ";
        $sql_product .=     ":weight, ";
        $sql_product .=     ":weight_class_id, ";
        $sql_product .=     ":length, ";
        $sql_product .=     ":width, ";
        $sql_product .=     ":height, ";
        $sql_product .=     ":length_class_id, ";
        $sql_product .=     ":subtract, ";
        $sql_product .=     ":minimum, ";
        $sql_product .=     ":sort_order, ";
        $sql_product .=     ":status," ;
        $sql_product .=     ":viewed, ";
        $sql_product .=     ":date_added, ";
        $sql_product .=     ":date_modified";
        $sql_product .= ")";

        // SQL statement for 'product_description' table
        $sql_description  = "INSERT INTO `" . self::$table_prefix . "product_description` (";
        $sql_description .=     "`product_id`, ";
        $sql_description .=     "`language_id`, ";
        $sql_description .=     "`name`, ";
        $sql_description .=     "`description`, ";
        $sql_description .=     "`tag`, ";
        $sql_description .=     "`meta_title`, ";
        $sql_description .=     "`meta_h1`, ";
        $sql_description .=     "`meta_description`, ";
        $sql_description .=     "`meta_keyword` ";
        $sql_description .= ") VALUES ( ";
        $sql_description .=     ":product_id, ";
        $sql_description .=     ":language_id, ";
        $sql_description .=     ":name, ";
        $sql_description .=     ":description, ";
        $sql_description .=     ":tag, ";
        $sql_description .=     ":meta_title, ";
        $sql_description .=     ":meta_h1, ";
        $sql_description .=     ":meta_description, ";
        $sql_description .=     ":meta_keyword";
        $sql_description .= ")";

        // SQL statement for 'product_to_store' table
        $sql_store  = "INSERT INTO `" . self::$table_prefix . "product_to_store` (";
        $sql_store .=     "`product_id`, ";
        $sql_store .=     "`store_id`";
        $sql_store .= ") VALUES ( ";
        $sql_store .=     ":product_id, ";
        $sql_store .=     ":store_id";
        $sql_store .= ")";

        //  SQL statement for 'product_to_layout' table
        $sql_layout  = "INSERT INTO `" . self::$table_prefix . "product_to_layout` (";
        $sql_layout .=     "`product_id`, ";
        $sql_layout .=     "`store_id`, ";
        $sql_layout .=     "`layout_id` ";
        $sql_layout .= ") VALUES ( ";
        $sql_layout .=     ":product_id, ";
        $sql_layout .=     ":store_id, ";
        $sql_layout .=     ":layout_id";
        $sql_layout .= ")";

        // SQL statement for 'product_to_category' table
        $sql_category  = "INSERT INTO `" . self::$table_prefix . "product_to_category` (";
        $sql_category .=    "`product_id`, ";
        $sql_category .=    "`category_id`, ";
        $sql_category .=    "`main_category`";
        $sql_category .= ") VALUES (";
        $sql_category .=    ":product_id, ";
        $sql_category .=    ":category_id, ";
        $sql_category .=    ":main_category";
        $sql_category .= ")";


        try {
            $stmt_product = self::$dbh->prepare($sql_product);
            $stmt_description = self::$dbh->prepare($sql_description);
            $stmt_store = self::$dbh->prepare($sql_store);
            $stmt_layout = self::$dbh->prepare($sql_layout);
            $stmt_category = self::$dbh->prepare($sql_category);
        } catch (\PDOException $e) {
            echo "Error: " . $e-getMessage();
        }

        foreach ($products as $product) {
            // Insert product  into 'product' table
            $params = [
                ":model" => isset($product['model']) ? $product['model'] : "" ,
                ":sku" => isset($product['article']) ? $product['article'] : "",
                ":upc" => isset($product['upc']) ? $product['upc'] : "",
                ":ean" => isset($product['ean']) ? $product['ean'] : "",
                ":jan" => isset($product['jan']) ? $product['jan'] : "",
                ":isbn" => isset($product['isbn']) ? $product['isbn'] : "",
                ":mpn" => isset($product['mpn']) ? $product['mpn'] : "",
                ":location" => isset($product['location']) ? $product['location'] : "",
                ":quantity" => isset($product['quantity']) ? $product['quantity'] : 0,
                ":stock_status_id" => isset($product['stock_status_id']) ? $product['stock_status_id'] : 0,
                ":image" => isset($product['image']) ? $product['image'] : "",
                ":manufacturer_id" => isset($product['manufacturer_id']) ? $product['manufacturer_id'] : 0,
                ":shipping" => isset($product['shipping']) ? $product['shipping'] : 1,
                ":price" => isset($product['price']) ? $product['price'] : 0,
                ":points" => isset($product['points']) ? $product['points'] : 0,
                ":tax_class_id" => isset($product['tax_class_id']) ? $product['tax_class_id'] : 0,
                ":date_available" => isset($product['date_available']) ? $product['date_available'] : date("Y-m-d G:i:s"),
                ":weight" => isset($product['weight']) ? $product['weight'] : 0,
                ":weight_class_id" => isset($product['weight_class_id']) ? $product['weight_class_id'] : 0,
                ":length" => isset($product['length']) ? $product['length'] : 0,
                ":width" => isset($product['width']) ? $product['width'] : 0,
                ":height"=> isset($product['height']) ? $product['height'] : 0,
                ":length_class_id" => isset($product['length_class_id']) ? $product['length_class_id'] : 0,
                ":subtract" => isset($product['subtract']) ? $product['subtract'] : 1,
                ":minimum" => isset($product['minimum']) ? $product['minimum'] : 1,
                ":sort_order" => self::getNextSortOrderValue('product'),
                ":status" => isset($product['status']) ? $product['status'] : 0,
                ":viewed" => isset($product['viewed']) ? $product['viewed'] : 0,
                ":date_added" => isset($product['date_added']) ? $product['date_added'] : date("Y-m-d G:i:s"),
                ":date_modified" => isset($product['date_modified']) ? $product['date_modified'] : date("Y-m-d G:i:s")
            ];

            $stmt_product->execute($params);

            $product_id = self::$dbh->getLastInsertedId();

            // Insert product into 'product_description' table
            $params = [
                ":product_id" => $product_id,
                ":language_id" => self::getLanguageId(),
                ":name" => isset($product['name']) ? $product['name'] : "",
                ":description" => isset($product['description']) ? $product['description'] : "",
                ":tag" => isset($product['tag']) ? $product['tag'] : "",
                ":meta_title" => isset($product['meta_title']) ? $product['meta_title'] : "",
                ":meta_h1" => isset($product['meta_h1']) ? $product['meta_h1'] : "",
                ":meta_description" => isset($product['meta_description']) ? $product['meta_description'] : "",
                ":meta_keyword" => isset($product['meta_keyword']) ? $product['meta_keyword'] : ""
            ];
            $stmt_description->execute($params);

            // Insert product into 'product_to_store' table
            $params = [
                ":product_id" => $product_id,
                ":store_id" => self::$store_id
            ];
            $stmt_store->execute($params);

            // Insert product into 'product_to_layout' table
            $params = [
                ":product_id" => $product_id,
                ":store_id" => self::$store_id,
                ":layout_id" => self::$layout_id
            ];
            $stmt_layout->execute($params);

            // Insert product into 'product_to_category' table
            $main_category = 1;
            $category_id = self::getCategoryId($product['category']);
            $params = [
                ":product_id" => $product_id,
                ":category_id" => $category_id,
                ":main_category" => $main_category
            ];
            $stmt_category->execute($params);
        }

    }
}
