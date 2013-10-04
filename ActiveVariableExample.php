<?php
class CustomerWithBasicObserverImplementation {
    private $name;
    private $phone;
    private $observers;

    public function __construct($name,$phone) {
        $this->name = $name;
        $this->phone = $phone;
        $this->observers = array();
    }

    public function getName() {
        return $this->name;
    }

    public function setName($newName) {
        $this->name = $newName;
        $this->notify();
    }

    public function getPhone(){
        return $this->phone;
    }

    public function setPhone($newPhone) {
        $this->phone = $newPhone;
        $this->notify();
    }

    public function observeWith($anObserver){
        $this->observers[] = $anObserver;
    }

    public function notify(){
        foreach($this->observers as$observer)
            $observer->update($this);
    }
}

class CustomerWithObserverPerVariable {
    private $name;
    private $nameObservers;

    private $phone;
    private $phoneObservers;

    public function __construct($name,$phone) {
        $this->name = $name;
        $this->nameObservers = array();

        $this->phone = $phone;
        $this->phoneObservers= array();
    }

    public function getName() {
        return $this->name;
    }

    public function setName($newName) {
        $this->name = $newName;
        $this->notify($this->nameObservers,$newName);
    }

    public function observeNameWith($anObserver) {
        $this->nameObservers[] = $anObserver;
    }

    public function getPhone(){
        return $this->phone;
    }

    public function setPhone($newPhone) {
        $this->phone = $newPhone;
        $this->notify($this->phoneObservers,$newPhone);
    }

    public function observePhoneWith($anObserver) {
        $this->phoneObservers[] = $anObserver;
    }

    public function notify($observers,$newObject){
        foreach ($observers as $observer)
            $observer->update($newObject);
    }
}

class ActiveVariable  {
    private $value;
    private $observers;

    public function __construct($initialValue){
        $this->observers = array();
        $this->set($initialValue);
    }

    public function get(){
        return $this->value;
    }

    public function set($newValue){
        $this->value = $newValue;
        $this->notify();
    }

    public function observeWith($observer)
    {
        $this->observers[] = $observer;
    }

    public function neglectWith($observer)
    {
        unset($this->observers,$observer);
    }

    public function notify()
    {
        foreach($this->observers as $observer)
            $observer->update($this->value);
    }
}

class CustomerWithActiveVariable {
    private $name;
    private $phone;

    public function __construct($name,$phone){
        $this->name = new ActiveVariable($name);
        $this->phone = new ActiveVariable($phone);
    }

    public function getName(){
        return $this->name->get();
    }

    public function setName($newName){
        $this->name->set($newName);
    }

    public function observeNameWith($anObserver){
        $this->name->observeWith($anObserver);
    }

    public function getPhone(){
        return $this->phone->get();
    }

    public function setPhone($newPhone){
        $this->phone->set($newPhone);
    }

    public function observePhoneWith($anObserver){
        $this->phone->observeWith($anObserver);
    }
}

trait ObservableContainer {

    private $activeVariables = array();

    public function initializeContainer($variableNames) {
        foreach($variableNames as $variableName)
            $this->activeVariables[$variableName] = new ActiveVariable(null);
    }

    public function __get($name){
        return $this->activeVariables[$name]->get();
    }

    public function __set($name,$value){
        $this->activeVariables[$name]->set($value);
    }

    public function __call($messageName,$parameters){
        if ($this->isGetter($messageName,$parameters))
            return $this->__get($this->variableNameFrom($messageName));
        elseif ($this->isSetter($messageName,$parameters))
            return $this->__set($this->variableNameFrom($messageName),$parameters[0]);
        elseif ($this->isObserve($messageName,$parameters))
            return $this->observeWith($this->variableNameFromObserve($messageName),$parameters[0]);
        else
            throw new Exception ("Message not understood");
    }

    private function isGetter($messageName,$parameters) {
        return
            $this->startsWith($messageName,"get") &&
            strlen($messageName) > 3 &&
            count($parameters) == 0;
    }

    private function isSetter($messageName,$parameters) {
        return
            $this->startsWith($messageName,"set") &&
            strlen($messageName) > 3 &&
            count($parameters) == 1;
    }

    private function isObserve($messageName,$parameters) {
        return
            $this->startsWith($messageName,"observe") &&
            substr_compare($messageName,"With",-4) === 0 &&
            strlen($messageName) > 11 &&
            count($parameters) == 1;
    }

    private function startsWith($sourceString,$header) {
        return strpos($sourceString,$header) === 0;
    }

    private function variableNameFrom($messageName){
        return lcfirst(substr($messageName,3));
    }

    private function variableNameFromObserve($messageName){
        return lcfirst(substr($messageName,7,-4));
    }

    private function observeWith($name,$observer){
        $this->activeVariables[$name]->observeWith($observer);
    }
}

class CustomerAsObservableContainer {
    use ObservableContainer;

    public function __construct($name,$phone){
        $this->initializeContainer(array("name","phone"));
        $this->setName($name);
        $this->setPhone($phone);
    }
}

class RememberChangeObserver{
    private $newObject;

    public function update($newObject){
        $this->newObject = $newObject;
    }

    public function newObject(){
        return $this->newObject;
    }
}

class ActiveVariableTest extends PHPUnit_Framework_TestCase {

    public function testBasicObserverImplementation(){
        $customer = new CustomerWithBasicObserverImplementation("Juan Perez","4444-5555");
        $observer = new RememberChangeObserver();
        $customer->observeWith($observer);

        $customer->setName("Juan Domingo Perez");
        //I can't tell what changed
        $this->assertEquals($customer,$observer->newObject());

        $customer->setPhone("1111-2222");
        //I can't tell what changed
        $this->assertEquals($customer,$observer->newObject());

    }

    public function testObserverPerVariable(){
        $customer = new CustomerWithObserverPerVariable("Juan Perez","4444-5555");
        $nameObserver = new RememberChangeObserver();
        $phoneObserver = new RememberChangeObserver();
        $customer->observeNameWith($nameObserver);
        $customer->observePhoneWith($phoneObserver);

        $customer->setName("Juan Domingo Perez");
        $this->assertEquals("Juan Domingo Perez",$nameObserver->newObject());

        $customer->setPhone("1111-2222");
        $this->assertEquals("1111-2222",$phoneObserver->newObject());

    }

    public function testObserverWithActiveVariable(){
        $customer = new CustomerWithActiveVariable("Juan Perez","4444-5555");
        $nameObserver = new RememberChangeObserver();
        $phoneObserver = new RememberChangeObserver();
        $customer->observeNameWith($nameObserver);
        $customer->observePhoneWith($phoneObserver);

        $customer->setName("Juan Domingo Perez");
        $this->assertEquals("Juan Domingo Perez",$nameObserver->newObject());

        $customer->setPhone("1111-2222");
        $this->assertEquals("1111-2222",$phoneObserver->newObject());

    }

    public function testObservableContainer(){
        $customer = new CustomerAsObservableContainer("Juan Perez","4444-5555");
        $nameObserver = new RememberChangeObserver();
        $phoneObserver = new RememberChangeObserver();
        $customer->observeNameWith($nameObserver);
        $customer->observePhoneWith($phoneObserver);

        $customer->setName("Juan Domingo Perez");
        $this->assertEquals("Juan Domingo Perez",$nameObserver->newObject());
        $this->assertEquals("Juan Domingo Perez",$customer->getName());

        $customer->setPhone("1111-2222");
        $this->assertEquals("1111-2222",$phoneObserver->newObject());
        $this->assertEquals("1111-2222",$customer->getPhone());
    }

}