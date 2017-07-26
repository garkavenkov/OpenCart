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
     * Set settings
     * @param  string       $prefix Table name prefix
     * @param  DBConnect    $dbh    Instance of DBConnect class
     * @return void
     */
    public static function initiate(string $prefix, DBConnect $dbh)
    {
        self::$table_prefix = $prefix;
        self::$dbh = $dbh;
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
     * @param  string $category_name    Category name
     * @return int                      Category id
     */
    public static function getCategoryId($category_name)
    {
        // Delete excess spaces in the category name
        $category_name = preg_replace('/\s+/', ' ', $category_name);

        $sql  = "SELECT `category_id` ";
        $sql .= "FROM `" . self::$table_prefix . "category_description` ";
        $sql .= "WHERE `name` = :category_name and ";
        $sql .=     "`language_id` = " . self::getLanguageId();
        $stmt = self::$dbh->prepare($sql);

        if ($stmt->execute(array(":category_name" => $category_name))) {
            $category_id = $stmt->fetch(\PDO::FETCH_ASSOC)['category_id'];
            return $category_id;
        } else {
            return ;
        }
    }
}
