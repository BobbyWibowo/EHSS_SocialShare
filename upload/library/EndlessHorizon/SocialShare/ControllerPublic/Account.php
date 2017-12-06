<?php
/*
 * EH_SocialShare
 * by Bobby Wibowo
 */

class EndlessHorizon_SocialShare_ControllerPublic_Account extends XFCP_EndlessHorizon_SocialShare_ControllerPublic_Account
{
    public function actionPreferencesSave()
    {
        $parent = parent::actionPreferencesSave();

        if ($this->_request->isPost())
        {
            $input = $this->_input->filterSingle('ehss_display', XenForo_Input::STRING);

            $writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
            $writer->setExistingData(XenForo_Visitor::getUserId());
            $writer->set('ehss_display', $input, 'xf_user_option');
            $writer->save();
        }

        return $parent;
    }
}
