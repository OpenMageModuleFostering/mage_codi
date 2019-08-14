<?php
class Mage_CodiScript_Helper_Data extends Mage_Core_Helper_Abstract 
{    
    public function getCodiPassword()
    {
        if($password = Mage::getStoreConfig('codiscript/config/password')){
            return $password;
        }
        return '';
    }
}