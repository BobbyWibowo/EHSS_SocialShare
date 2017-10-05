<?php
/*
 * EH_SocialShare
 * by Bobby Wibowo
 */

class EndlessHorizon_SocialShare_DataWriter_User extends XFCP_EndlessHorizon_SocialShare_DataWriter_User
{
    protected function _getFields()
    {
        $parent = parent::_getFields();

        $parent['xf_user_option']['ehss_display'] = array(
            'type' => self::TYPE_STRING, 'default' => ''
        );

        return $parent;
    }
}