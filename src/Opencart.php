<?php

namespace Garkavenkov\Opencart;

use Garkavenkov\DBConnector\DBConnect;
use Garkavenkov\SQLGenerator\SQL;

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
     * Returns id for attribute group name
     * @param  string $name Attribute group name
     * @return int          Attribute group id
     */
    public static function getAttributeGroupId($name)
    {
        // Delete excess spaces in the group name
        $name = preg_replace('/\s+/', ' ', $name);

        $sql  = "SELECT `attribute_group_id` ";
        $sql .= "FROM `" . self::$table_prefix . "attribute_group_description` ";
        $sql .= "WHERE  `name` = '{$name}' and ";
        $sql .=        "`language_id` = " . self::getLanguageId() ;
        $attributeGroupId = self::$dbh->getFieldValue($sql, 'attribute_group_id');

        return $attributeGroupId;
    }

    /**
     * Imports Attribute group name
     * @param  string   $name           Attribute group name
     * @param  int      $sort_order     Attribute group sort order
     * @return int                      Attribute group id
     */
    public function importAttributeGroup($name, $sort_order = null)
    {
        // Check whether group exists or not
        $attribute_group_id = self::getAttributeGroupId($name);

        if (!$attribute_group_id) {
            // Find sor_order value for table 'attribute_group'
            if (!$sort_order) {
                $sort_order = self::getNextSortOrderValue("attribute_group");
            }

            $sql  = "INSERT INTO `" . self::$table_prefix . "attribute_group` (";
            $sql .=     "`sort_order`";
            $sql .= ") VALUES (";
            $sql .=     ":sort_order";
            $sql .= ")";
            $stmt = self::$dbh->prepare($sql);
            $stmt->execute(array(
                ":sort_order" => $sort_order
            ));

            $attribute_group_id = self::$dbh->getLastInsertedId();

            $sql  = "INSERT INTO `" . self::$table_prefix . "attribute_group_description` (";
            $sql .=     "`attribute_group_id`, ";
            $sql .=     "`language_id`, ";
            $sql .=     "`name`";
            $sql .= ") VALUES (";
            $sql .=     ":attribute_group_id, ";
            $sql .=     ":language_id, ";
            $sql .=     ":name";
            $sql .= ")";
            $stmt = self::$dbh->prepare($sql);
            $stmt->execute(array(
                ":attribute_group_id" => $attribute_group_id,
                ":language_id" => self::getLanguageId(),
                ":name" => $name
            ));
        }
        return $attribute_group_id;
    }

    /**
     * Returns Attribute id
     * @param  string $name Attribute name
     * @return int          Attribute id
     */
    public static function getAttributeId($name)
    {
        // Delete excess spaces in the group name
        $name = preg_replace('/\s+/', ' ', $name);

        $sql  = "SELECT `attribute_id` ";
        $sql .= "FROM `" . self::$table_prefix . "attribute_description` ";
        $sql .= "WHERE `name` = '{$name}'";

        $attributeId = self::$dbh->getFieldValue($sql, 'attribute_id');
        return $attributeId;
    }

    /**
     * Imports an Attribute
     * @param  string $name  Attribute name
     * @param  string $group Attribute group name
     * @return int           Attribute id
     */
    public static function importAttribute($name, $group)
    {
        // Find Attribute Group Id by $group
        $attribute_group_id = self::getAttributeGroupId($group);

        // If Attribute group not found, create one
        if (!$attribute_group_id) {
            $attribute_group_id = self::importAttributeGroup($group);
        }

        // Sort order for new attribute
        $sort_order = self::getNextSortOrderValue("attribute");

        // Insert record into 'attribute' table
        $sql  = "INSERT INTO `" . self::$table_prefix . "attribute` (";
        $sql .=     "`attribute_group_id`, ";
        $sql .=     "`sort_order`";
        $sql .= ") VALUES (";
        $sql .=     ":attribute_group_id,";
        $sql .=     ":sort_order";
        $sql .= ")";
        $stmt = self::$dbh->prepare($sql);
        $stmt->execute(array(
            ":attribute_group_id" => $attribute_group_id,
            ":sort_order" => $sort_order
        ));

        // Attribute id
        $attribute_id = self::$dbh->getLastInsertedId();

        // Insert record into 'attribute_description' table
        $sql  = "INSERT INTO `" . self::$table_prefix . "attribute_description` (";
        $sql .=     "`attribute_id`, ";
        $sql .=     "`language_id`, ";
        $sql .=     "`name`";
        $sql .= ") VALUES (";
        $sql .=     ":attribute_id, ";
        $sql .=     ":language_id, ";
        $sql .=     ":name";
        $sql .= ")";

        $stmt = self::$dbh->prepare($sql);
        $stmt->execute(array(
            ":attribute_id" => $attribute_id,
            ":language_id" => self::getLanguageId(),
            ":name" => $name
        ));

        return $attribute_id;
    }

    /**
     * Import product
     * @param  array  $product Information about product/products
     * @return void
     */
    public static function importProducts(array $products)
    {
        // SQL statement for 'product' table
        $table_name = self::$table_prefix . "product";
        $fields = [
            "model",              // Модель
            "sku",                // Артикул
            "upc",                // Универсальный код товара
            "ean",                // Европейский номер товара
            "jan",                // Японский штрихкод
            "isbn",               // Номер книжного издания
            "mpn",                // Номер партии изготовителя
            "location",           // Расположение
            "quantity",           // Количество = 0
            "stock_status_id",    // Отсутсвие на складе
            "image",              // Изображение = NULL
            "manufacturer_id",    // Id производителя
            "shipping",           // Необходима доставка = 1
            "price",              // Цена
            "points",             // Баллы = 0
            "tax_class_id",       // Налог
            "date_available",     // Дата поступления
            "weight",             // Вес = '0.00'
            "weight_class_id",    // Единица веса = 0
            "length",             // Длина = '0.00'
            "width",              // Ширина = '0.00'
            "height",             // Высота = '0.00'
            "length_class_id",    // Единица длины = 0
            "subtract",           // Вычитать со склада = 1
            "minimum",            // Мин. кол-во товара для заказа =1
            "sort_order",         // Порядок сортировки = 0
            "status",             // Статус = 0
            "viewed",             // Кол-во просмотров = 0
            "date_added",         // Дата создания
            "date_modified"       // Дата изменения
        ];
        $sql_product = SQL::insert($table_name, $fields);

        // SQL statement for 'product_description' table
        $table_name = self::$table_prefix . "product_description";
        $fields = [
            "product_id",
            "language_id",
            "name",
            "description",
            "tag",
            "meta_title",
            "meta_h1",
            "meta_description",
            "meta_keyword"
        ];
        $sql_description = SQL::insert($table_name, $fields);

        // SQL statement for 'product_to_store' table
        $table_name = self::$table_prefix . "product_to_store";
        $fields = [
            "product_id",
            "store_id"
        ];
        $sql_store = SQL::insert($table_name, $fields);

        //  SQL statement for 'product_to_layout' table
        $table_name = self::$table_prefix . "product_to_layout";
        $fields = [
            "product_id",
            "store_id",
            "layout_id"
        ];
        $sql_layout = SQL::insert($table_name, $fields);

        // SQL statement for 'product_to_category' table
        $table_name = self::$table_prefix . "product_to_category";
        $fields = [
            "product_id",
            "category_id",
            "main_category"
        ];
        $sql_category = SQL::insert($table_name, $fields);

        // SQL statement for 'product_image' table
        $table_name = self::$table_prefix . "product_image";
        $fields = [
            "product_id",
            "image",
            "sort_order"
        ];
        $sql_images = SQL::insert($table_name, $fields);

        // SQL statement for 'product_attribute' table
        $table_name = self::$table_prefix . "product_attribute";
        $fields = [
            "product_id",
            "attribute_id",
            "language_id",
            "text"
        ];
        $sql_attributes = SQL::insert($table_name, $fields);

        try {
            $stmt_product = self::$dbh->prepare($sql_product);
            $stmt_description = self::$dbh->prepare($sql_description);
            $stmt_store = self::$dbh->prepare($sql_store);
            $stmt_layout = self::$dbh->prepare($sql_layout);
            $stmt_category = self::$dbh->prepare($sql_category);
            $stmt_images = self::$dbh->prepare($sql_images);
            $stmt_attributes = self::$dbh->prepare($sql_attributes);
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

            // Insert images into 'product_image' table
            if (isset($product['images'])) {
                foreach ($product['images'] as $image) {
                    print_r($image);
                    $stmt_images->execute(array(
                        ":product_id" => $product_id,
                        ":image"      => $image['path'],
                        ":sort_order" => $image['sort_order']
                    ));
                }
            }

            // Insert attributes into 'product_attribute' table
            if (isset($product['attributes'])) {
                $group_name = "Технические характеристики";
                foreach ($product['attributes'] as $attribute) {
                    $name = array_keys($attribute)[0];
                    $value = array_values($attribute)[0];
                    $attribute_id = self::getAttributeId($name);
                    if (!$attribute_id) {
                        $attribute_id = self::importAttribute($name, $group_name);
                    }
                    $stmt_attributes->execute(array(
                        ":product_id"   => $product_id,
                        ":attribute_id" => $attribute_id,
                        ":language_id"  => self::getLanguageId(),
                        ":text"         => $value
                    ));
                }
            }
        }
    }

    public static function deleteAllProducts()
    {
        $sql  = "DELETE FROM `" . self::$table_prefix . "product`; ";
        $sql .= "DELETE FROM `" . self::$table_prefix . "product_attribute`; ";
        $sql .= "DELETE FROM `" . self::$table_prefix . "product_description`; ";
        $sql .= "DELETE FROM `" . self::$table_prefix . "product_discount`; ";
        $sql .= "DELETE FROM `" . self::$table_prefix . "product_filter`; ";
        $sql .= "DELETE FROM `" . self::$table_prefix . "product_image`; ";
        $sql .= "DELETE FROM `" . self::$table_prefix . "product_option_value`; ";
        $sql .= "DELETE FROM `" . self::$table_prefix . "product_option`; ";
        $sql .= "DELETE FROM `" . self::$table_prefix . "product_recurring`; ";
        $sql .= "DELETE FROM `" . self::$table_prefix . "product_related`; ";
        $sql .= "DELETE FROM `" . self::$table_prefix . "product_reward`; ";
        $sql .= "DELETE FROM `" . self::$table_prefix . "product_special`; ";
        $sql .= "DELETE FROM `" . self::$table_prefix . "product_to_category`; ";
        $sql .= "DELETE FROM `" . self::$table_prefix . "product_to_download`; ";
        $sql .= "DELETE FROM `" . self::$table_prefix . "product_to_layout`; ";
        $sql .= "DELETE FROM `" . self::$table_prefix . "product_to_store`; ";

        try {
            self::$dbh->query($sql);
        } catch (\PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}
