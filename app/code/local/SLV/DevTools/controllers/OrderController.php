<?php
/**
 * Class SLV_DevTools_OrderController
 *
 * Develop Order Controller
 */
class SLV_DevTools_OrderController extends Mage_Core_Controller_Front_Action
{

    protected function _getProductId()
    {
        $productsId = array(10507,10508,10509,10510,10511,10512);

        return $productsId[array_rand($productsId)];
    }

    protected function _getCustomer()
    {
        $customersId = array(1,10,4,5,8);
        $customerId = $customersId[array_rand($customersId)];

        return Mage::getModel('customer/customer')->load($customerId);
    }


    /**
     *
    for (var i = 0; i < 100; i++)
    {
    xmlhttp = new XMLHttpRequest();

    xmlhttp.open("GET","http://test.loc/index.php/slv_devtool/order/placeorder/",true);
    xmlhttp.send();
    }
     *
     * @return Mage_Core_Controller_Varien_Action
     * @throws Exception
     */
    public function placeorderAction()
    {
        $recursion = $this->getRequest()->getParam('recursion');

        $customer = $this->_getCustomer();

        $quote = Mage::getModel('sales/quote')
            ->setStoreId(Mage::app()->getStore()->getId()) // will be used current store
            ->assignCustomer($customer);

        $product = Mage::getModel('catalog/product')->load($this->_getProductId());


        $item = $quote->addProduct($product, new Varien_Object(array(
            'product'         => $product->getId(),
            'qty'             => 1,
        )));

        if(!is_object($item)) throw new Exception('something wrong with product');

        // save quotes
        $quote->collectTotals()->save();

        $billingAddress = Mage::getSingleton('sales/quote_address')->importCustomerAddress(
            //Mage::getModel('customer/address')->load($customer->getDefaultBilling())
            $customer->getDefaultBillingAddress()
        );

        if($billingAddress->getId())
        {
            $quote->setBillingAddress($billingAddress);
        }
//        else
//        {
//            $quote->getBillingAddress()->addData($this->getRequest()->getParams());
//        }


        $shippingAddress = Mage::getSingleton('sales/quote_address')->importCustomerAddress(
            //Mage::getModel('customer/address')->load($customer->getDefaultShipping())
            $customer->getDefaultShippingAddress()
        );

        if($shippingAddress->getId())
        {
            $quote->setShippingAddress($shippingAddress);
        }
//        $quote->getShippingAddress()->addData($this->getRequest()->getParams());

        $quote->collectTotals();

        $quote->getShippingAddress()->setPaymentMethod('checkmo');
        $quote->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates()
            ->setShippingMethod('freeshipping_freeshipping'); // set shipping method

        $quote->getPayment()->importData(array('method' => 'checkmo')); // set payment method
        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();

        try
        {
            $quote->setSendCconfirmation(true);
            $service = Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();
            $order = $service->getOrder();
            $order->sendNewOrderEmail();
            $quote
                ->setIsActive(false)
                ->save();
        }
        catch(Exception $e)
        {
            Mage::log($e->getMessage(), null, "slv_devtool_placeorder.log");
            echo $e->getMessage();

            die();
//            return $this->_redirect('');
        }

//        if($recursion)
//        {
//            return $this->_redirect('*/*/*',array('recursion' => 1));
//        }

        return $this->_redirect('checkout/onepage/success');
    }
}