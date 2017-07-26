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
}
