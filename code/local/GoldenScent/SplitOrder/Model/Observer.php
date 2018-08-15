<?php
class GoldenScent_SplitOrder_Model_Observer
{
    public function SplitOrder(Varien_Event_Observer $observer)
    {
        if (Mage::registry('my_observer_has_run')) {
            Mage::log('Mstop', null, 'mylogfile.log');
            return true;
        }

        $order = $observer->getEvent()->getOrder();
        //Mage::log($order->getShippingMethod(), null, 'mylogfile.log');
        $allItem = $order->getAllVisibleItems();
        foreach ($allItem as $item) {
            $product = $item->getProduct();
            $_product = Mage::getModel('catalog/product')->load($product->getId());
            $brand_Item = $_product->getData("brand");
            if ($brand_Item != "") {
                $brand[$product->getId()] =  $brand_Item;
                $itemQty[$product->getId()] = $item->getData('qty_ordered');
            }

        }
        Mage::log($brand, null, 'mylogfile.log');
        $productArray= array();
        foreach ($brand as $key => $val) {
            $productArray[$val][] = $key;
        }
        if (count($productArray) > 1) {
          $this->createOrder($order, $productArray, $itemQty);
        }
    }

    public function createOrder($order, $array, $itemQty)
    {
        try {
            Mage::register("parentId", $order->getId()); //save orderId
        }
        catch (Exception $ex) {
            echo $ex->getMessage();
        }
        foreach($array as $productId)
        {$store = $order->getStore();
            $quote = Mage::getModel('sales/quote')->setStoreId($store->getId());
            $quote->setCurrency($order->AdjustmentAmount->currencyID);
            $customer = $order->getCustomer();
            if($customer->getId()==""){
                //Its guest order
                $quote->setCheckoutMethod('guest')
                    ->setCustomerId(null)
                    ->setCustomerEmail($order->getCustomerEmail())
                    ->setCustomerIsGuest(true)
                    ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
            } else {
                // Assign Customer To Sales Order Quote
                $quote->assignCustomer($customer);
            }
            foreach($productId as $id){
                $product = Mage::getModel('catalog/product')->load($id);
                $quantity = $itemQty[$id];
                $quote->addProduct($product,new Varien_Object(array('qty'   => $quantity)));
            }
            $billingAddress = $order->getBillingAddress()->getData();
            $shippingAddress = $order->getShippingAddress()->getData();
            $billingAddress = $quote->getBillingAddress()->addData($billingAddress);
            $shippingAddress = $quote->getShippingAddress()->addData($shippingAddress);
            $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();
            $shippingAddress->setCollectShippingRates(true)->collectShippingRates()
                ->setShippingMethod($order->getShippingMethod())
                ->setPaymentMethod($paymentMethod);
            // Set Sales Order Payment

            $parentId = Mage::registry('parentId');
            $quote->getPayment()->importData(array('method' => $paymentMethod));
            $quote->collectTotals()->save();
            $service = Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();
            $sub_order = $service->getOrder();
            $sub_order->setRelationParentId($parentId);
            $sub_order->save();
            $quote->setIsActive(0)->save();
            Mage::getSingleton('checkout/cart')->truncate()->save();


        }
        try {
            Mage::register('my_observer_has_run', true);
        }
        catch (Exception $ex) {
            echo $ex->getMessage();
        }

    }

}