<?php


/**
 * Dibs A/S
 * Dibs Payment Extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category   Payments & Gateways Extensions
 * @package    Dibspw_Dibspw
 * @author     Dibs A/S
 * @copyright  Copyright (c) 2012 Dibs A/S. (http://www.dibs.dk/)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


namespace XLite\Module\Dibs\DibsPayWin\Model\Payment\Processor;


class DibsPayWin extends \XLite\Model\Payment\Base\WebBased
{
    /**
     * Get settings widget or template
     *
     * @return string Widget class name or template path
     */
    public function getSettingsWidget()
    {
        return 'modules/Dibs/DibsPayWin/config.tpl';
    }

    /**
     * Process return
     *
     * @param \XLite\Model\Payment\Transaction $transaction Return-owner transaction
     *
     * @return void
     */
    public function processReturn(\XLite\Model\Payment\Transaction $transaction)
    {
        parent::processReturn($transaction);
    
        if (\XLite\Core\Request::getInstance()->cancel) {
            $this->setDetail(
                'cancel',
                'Payment transaction is cancelled'
            );

            $this->transaction->setStatus($transaction::STATUS_FAILED);
            $this->getOrder()->setStatus(\XLite\Model\Order::STATUS_DECLINED);
            
            \Xlite\Core\Operator::redirect(\XLite\Core\Converter::buildURL('cart'));
            
        } else {
            $status  = $transaction::STATUS_SUCCESS;
            if( !$this->checkReturnFields()) {
                 $status = $transaction::STATUS_FAILED;
            }                 
            $this->transaction->setStatus($status);
        }
        
    }
    
    
   /* 
    * Server to server callback 
    *  
    */
    public function processCallback(\XLite\Model\Payment\Transaction $transaction)
    {
        parent::processCallback($transaction);
        $this->transaction = $transaction;
        $status = $transaction::STATUS_SUCCESS;
        $this->logCallback(\XLite\Core\Request::getInstance()->getData());
        if(!$this->checkReturnFields()) {
            $status = $transaction::STATUS_FAILED;
        }  
        
        $this->transaction->setStatus($status);
    }
    

    /**
     * Check - payment method is configured or not
     *
     * @param \XLite\Model\Payment\Method $method Payment method
     *
     * @return boolean
     */
    public function isConfigured(\XLite\Model\Payment\Method $method)
    {
        return parent::isConfigured($method);
            
    }

    /**
     * Get return type
     *
     * @return string
     */
    public function getReturnType()
    {
        return self::RETURN_TYPE_HTML_REDIRECT;
    }

    /**
     * Returns the list of settings available for this payment processor
     *
     * @return array
     */
    public function checkReturnFields(){
        $request = \XLite\Core\Request::getInstance();
        $noErrors = true;
        if ($this->roundAmount($this->transaction->getValue(),2) != $request->amount) {
            $noErrors = false;
            $this->setDetail('amount_error', "This is an error with amount");
          }

         if (!$this->checkCurrency($request->currency)) {
             $noErrors = false;    
             $this->setDetail('currency_error', "This is an error with currency");
         }
         return $noErrors;
    }


    /**
     * Get redirect form URL
     *
     * @return string
     */
    protected function getFormURL()
    {
        return 'https://sat1.dibspayment.com/dibspaymentwindow/entrypoint';
            
    }

    /**
     * Get redirect form fields list
     *
     * @return array
     */
    protected function getFormFields()
    {
        // Main parameters
        $fields = array(
            'merchant'           => $this->getSetting('merchantid'),
            'currency'           => $this->getOrder()->getCurrency()->getCode(),
            'orderid'            => $this->getOrder()->getOrderId(),
            'amount'             => ($this->getOrder()->getSubtotal() > 0) ? $this->roundAmount($this->getOrder()->getTotal(), 2) : 0 ,
            's_trans_id'         => $this->transaction->getTransactionId(),
            'acceptreturnurl'    => $this->getReturnURL('s_trans_id'),
            'callbackurl'        => $this->getCallbackURL('s_trans_id'),
            'cancelreturnurl'    => $this->getReturnURL('s_trans_id', $withId = false, $asCancel = true),
            'language'           => $this->getSetting('language'),
            'billingfirstname'   => $this->getProfile()->getBillingAddress()->getFirstname(),
            'billingLastName'    => $this->getProfile()->getBillingAddress()->getLastname(),
            'billingaddress'     => $this->getProfile()->getBillingAddress()->getStreet(),
            'billingpostalcode'  => $this->getProfile()->getBillingAddress()->getZipcode(),
            'billingpostalplace' => $this->getProfile()->getBillingAddress()->getCity(), 
            'billingemail'       => $this->getProfile()->getLogin(),
            'billingmobile'      => $this->getProfile()->getBillingAddress()->getPhone(),
            'oitypes'            => 'QUANTITY;UNITCODE;DESCRIPTION;AMOUNT;ITEMID;VATAMOUNT',
            'oinames'            => 'Items;UnitCode;Description;Amount;ItemId;VatAmount',
        );
        
        
        if ($shippingAddress = $this->getProfile()->getShippingAddress()) {
            $fields += array(
                'shippingfirstname'    => $shippingAddress->getFirstname(),
                'shippingLastName'     => $shippingAddress->getLastname(),
                'shippingaddress'      => $shippingAddress->getStreet(),
                'shippingpostalplace'  => $shippingAddress->getCity(),
                'shippingpostalcode'   => $shippingAddress->getZipcode(),
                'shippingaddress2'     => $shippingAddress->getCountry()->getCountry(),
            ); 
        } 
        
          
        // Optional parameters 
        $opParams = array('test', 'addfee', 'capturenow', 'account', 'paytype');
        foreach( $opParams as $op ) {
            if($val = $this->getSetting($op)) {
                $fields[$op] = $val; 
            }
         }
         
        // Add invoice fileds
        $fields += $this->getItems();           
       
        // Trim all values
        array_walk($fields, create_function('&$val', '$val = trim($val);'));

        // Add MAC code if HMAC is avalaible        
        if($hmac = $this->getSetting('hmac')) {
            $fields['MAC'] = $this->calcMAC($fields, trim($hmac));
        }
	return $fields;
    }
    
   /*
    * Get invoice fields.  
    * 
    * @return array
    */ 
   public function getItems() {
        
        $i=1;
        $aItems = array();
        // Add product Items
        foreach($this->getOrder()->getItems()->getSnapshot() as $mItem) {
	  if((float)$mItem->getClearPrice()) {      
	    $name   = $mItem->getName();
            $name   = trim( str_replace(';','\;',$name ));
            $price  = $this->roundAmount($mItem->getClearPrice(),2);
            $id     = str_replace(';','\;',$mItem->getSku());
            $amount = $mItem->getAmount();
            $aItems['oiRow'.$i] = "{$amount};pcs;{$name};{$price};{$id};0"; 
            $i++;
	 }


        
    }
        
        // Get Surcharges (Tax, Shipping rate)
        foreach($this->getOrder()->getSurcharges()->getSnapshot() as $item) {
            if($item->getType() == 'shipping' || $item->getType() == 'tax') {
                if($item->getValue()>0) {
		    $name  = trim($item->getName());
            	    $price = $this->roundAmount($item->getValue(),2);
            	    $id    = $item->getType() . '_0';
            	    $aItems['oiRow'.$i] = "1;pcs;{$name};{$price};{$id};0";
        	}
	    }
            $i++;
        }
        return $aItems;
}
    /*
     * Calculation of MAC code 
     * 
     * @return string
     */
    public function calcMAC($aData, $sHMAC, $bUrlDecode = FALSE) {
        $sMAC = '';
        if(!empty($sHMAC)) {
            $sData = '';
            if(isset($aData['MAC'])) unset($aData['MAC']);
            ksort($aData);
            foreach($aData as $sKey => $sVal) {
                $sData .= '&' . $sKey . '=' . (($bUrlDecode === TRUE) ? urldecode($sVal) : $sVal);
            }
            $sMAC = hash_hmac('sha256', ltrim($sData, '&'), $this->hexToStr($sHMAC));
        }
        return $sMAC;
    }
    
    public function hexToStr($sHMAC) {
        $sRes = '';
        foreach(explode("\n", trim(chunk_split($sHMAC, 2))) as $h) $sRes .= chr(hexdec($h));
        return $sRes;
    }
    
    /*
     * Round amount 
     * 
     * @return int
     */
    public function roundAmount($fNum, $iPrec = 2) {
        return empty($fNum) ? (int)0 : (int)(string)(round($fNum, $iPrec) * pow(10, $iPrec));
    }
    
}
