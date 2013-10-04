<?php
class CustomerWithoutLazyInitProxy {
    private $phones;
    private $addresses;

    public function phones(){
        if ($this->phones==null)
            $this->phones = $this->createPhones();

        return $this->phones;
    }

    public function addresses(){
        if ($this->addresses==null)
            $this->addresses = $this->createAddresses();

        return $this->addresses;
    }

    public function createPhones(){
        //creates a costly collection of phones
        return array ("phone1","phone2","phone n...");
    }

    public function createAddresses(){
        //creates a costly collection of phones
        return array ("address 1","address 2","address n...");
    }
}

class CustomerWithLazyInitProxyNoPoly {
    private $phones;
    private $addresses;

    public function __construct(){
        $this->phones = new LazyInitializationProxy( function () { return $this->createPhones();});
        $this->addresses = new LazyInitializationProxy( function () { return $this->createAddresses();});
    }

    public function phones(){
        return $this->phones->value();
    }

    public function addresses(){
        return $this->addresses->value();
    }

    public function createPhones(){
        //creates a costly collection of phones
        return array ("phone1","phone2","phone n...");
    }

    public function createAddresses(){
        //creates a costly collection of phones
        return array ("address 1","address 2","address n...");
    }
}

class CustomerWithLazyInitProxyPoly {
    private $phones;
    private $addresses;

    public function __construct(){
        $this->phones = new LazyInitializationProxy( function () { return new ArrayAsObject($this->createPhones());});
        $this->addresses = new LazyInitializationProxy( function () { return new ArrayAsObject($this->createAddresses());});
    }

    public function phones(){
        return $this->phones;
    }

    public function addresses(){
        return $this->addresses;
    }

    public function createPhones(){
        //creates a costly collection of phones
        return array ("phone1","phone2","phone n...");
    }

    public function createAddresses(){
        //creates a costly collection of phones
        return array ("address 1","address 2","address n...");
    }
}


class ArrayAsObject extends ArrayObject {
    public function isEmpty () {
        return $this->count()==0;
    }

    public function __call($func, $argv)
    {
        return call_user_func_array("array_".$func,
            array_merge(array($this->getArrayCopy()), $argv));
    }

}
class LazyInitializationProxy {
    private $value;
    private $initializationClosure;

    public function __construct($initializationClosure){
        $this->value = null;
        $this->initializationClosure = $initializationClosure;
    }
    public function value(){
        if ($this->isValueNotInitialized())
            $this->initializeValue();

        return $this->value;
    }

    public function isValueNotInitialized(){
        return $this->value == null;
    }

    public function initializeValue(){
        $this->value = $this->initializationClosure->__invoke();
    }

    public function __call($methodName,$arguments){
        return call_user_func_array(array($this->value(),$methodName),$arguments);
    }
}

class LazyInitTest extends PHPUnit_Framework_TestCase {

    public function testJustToDebugCustomerWithoutLazyInitProxy() {
        //This is just to see how lazy init is implemented
        $customer = new CustomerWithoutLazyInitProxy();

        $this->assertNotEmpty($customer->phones());
        $this->assertNotEmpty($customer->addresses());
    }

    public function testJustToDebugCustomerWithLazyInitProxyNoPoly() {
        //This is just to see how lazy init is implemented
        $customer = new CustomerWithLazyInitProxyNoPoly();

        $this->assertNotEmpty($customer->phones());
        $this->assertNotEmpty($customer->addresses());
    }

    public function testJustToDebugCustomerWithLazyInitProxyPoly() {
        //This is just to see how lazy init is implemented
        $customer = new CustomerWithLazyInitProxyPoly();

        $this->assertEquals(3,$customer->phones()->count());
        $this->assertFalse($customer->phones()->isEmpty());
        $this->assertEquals(3,$customer->addresses()->count());
        $this->assertFalse($customer->addresses()->isEmpty());
    }
}