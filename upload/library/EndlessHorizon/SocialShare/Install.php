<?php
/*
 * EH_SocialShare
 * by Bobby Wibowo
 */

class EndlessHorizon_SocialShare_Install
{
    protected static $_db = null;

    public static function install()
    {
        self::_runQuery("
            ALTER TABLE xf_user_option ADD COLUMN ehss_display ENUM('', 'standard', 'floating_l', 'floating_r', 'none') NOT NULL
        ");
    }

    public static function uninstall()
    {
        self::_runQuery("
            ALTER TABLE xf_user_option DROP COLUMN ehss_display
        ");
    }

    protected static function _runQuery($sql)
    {
        $db = self::_getDb();

        try
        {
            $db->query($sql);
        }
        catch (Zend_Db_Exception $e) {}
    }

    /**
     * @return Zend_Db_Adapter_Abstract
     */
    protected static function _getDb()
    {
        if (!self::$_db)
        {
            self::$_db = XenForo_Application::getDb();
        }

        return self::$_db;
    }
}
