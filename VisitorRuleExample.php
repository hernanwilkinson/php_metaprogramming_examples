<?php

class MessagesReceivedTracer {
    private $receivedMessageName;
    private $numberOfMessagesReceived = 0;
    private $arguments;

    public function __call($messageName,$arguments) {
        $this->numberOfMessagesReceived ++;
        $this->receivedMessageName = $messageName;
        $this->arguments = $arguments;
    }

    public function onlyReceivedMessageNamedWith($aMessageName,$visitedObject){
        return $this->receivedMessageName == $aMessageName
            && $this->numberOfMessagesReceived == 1
            && count($this->arguments) == 1
            && $this->arguments[0] == $visitedObject;
    }
}

class VisitorImplementationReviewer
{
    private $classHierarchyRoot;
    private $visitorInterface;
    private $hierarchyRootSubclasses;

    const ACCEPT_MESSAGE_NAME = "accept";
    const ACCEPT_MESSAGE_NOT_DEFINED = "Accept message not defined in hierarchy root";
    const ACCEPT_MESSAGE_SHOULD_RECEIVE_ONE_PARAMETER = "Accept message should receive one parameter";
    const ACCEPT_METHOD_IS_NOT_ABSTRACT = "Accept method is not abstract";

    public function __construct($classHierarchyRootName,$visitorInterfaceName)
    {
        $this->classHierarchyRoot = new ReflectionClass($classHierarchyRootName);
        $this->visitorInterface = new ReflectionClass($visitorInterfaceName);
    }

    public function verify()
    {
        $this->verifyAcceptMethodInRootHierarchyClass();
        $this->verifyVisitorInterfaceDefinition();
        $this->verifyAcceptImplementationInAllSubclasses();
    }

    private function verifyAcceptMethodInRootHierarchyClass()
    {
        $acceptMethod = $this->acceptMethodInHierarchyRoot();

        $this->verifyAcceptMethodInHierarchyRootHasOneParameter($acceptMethod);
        $this->verifyAcceptMethodInHierarchyRootIsAbstract($acceptMethod);
    }

    private function acceptMethodInHierarchyRoot()
    {
        try {
            $acceptMethod = $this->classHierarchyRoot->getMethod(self::ACCEPT_MESSAGE_NAME);
            return $acceptMethod;
        } catch (ReflectionException $e) {
            throw new Exception(self::ACCEPT_MESSAGE_NOT_DEFINED);
        }
    }

    private function verifyAcceptMethodInHierarchyRootHasOneParameter($acceptMethod)
    {
        if (count($acceptMethod->getParameters()) != 1)
            throw new Exception(self::ACCEPT_MESSAGE_SHOULD_RECEIVE_ONE_PARAMETER);
    }

    private function verifyAcceptMethodInHierarchyRootIsAbstract($acceptMethod)
    {
        if (!$acceptMethod->isAbstract())
            throw new Exception(self::ACCEPT_METHOD_IS_NOT_ABSTRACT);
    }

    private function verifyVisitorInterfaceDefinition()
    {
        array_walk (
            $this->hierarchyConcreteSubclasses(),
            function ($subclass) { $this->verifyVisitorInterfaceDefinitionFor($subclass); });
    }

    private function verifyVisitorInterfaceDefinitionFor($subclass)
    {
        $subclassName = $subclass->getName();
        $visitMethod = $this->visitMethodOf($subclassName);
        $this->verifyVisitMethodHasOneParameter($visitMethod, $subclassName);
    }

    private function visitMethodOf($subclassName)
    {
        try {
            $visitMethod = $this->visitorInterface->getMethod($this->visitMessageNameFor($subclassName));
            return $visitMethod;
        } catch (ReflectionException $e) {
            throw new Exception($this->visitNotImplementedErrorDescriptionFor($subclassName));
        }
    }

    private function verifyVisitMethodHasOneParameter($visitMethod, $subclassName)
    {
        if (count($visitMethod->getParameters()) != 1)
            throw new Exception($this->visitWithoutProperParametersErrorDescriptionFor($subclassName));
    }

    private function verifyAcceptImplementationInAllSubclasses()
    {
        array_walk(
            $this->hierarchyConcreteSubclasses(),
            function ($subclass) { $this->verifyAcceptImplementationOf($subclass); });
    }

    private function verifyAcceptImplementationOf($subclass)
    {
        $visitor = new MessagesReceivedTracer();
        $objectToVisit = $subclass->newInstanceWithoutConstructor();
        $subclass->getMethod(self::ACCEPT_MESSAGE_NAME)->invoke($objectToVisit,$visitor);

        if (! $visitor->onlyReceivedMessageNamedWith($this->visitMessageNameFor($subclass->getName()),$objectToVisit))
            throw new Exception($this->acceptMethodNotImplementedCorrectlyErrorDescriptionFor($subclass->getName()));
    }

    private function hierarchyConcreteSubclasses()
    {
        //Just for the shake of simplicity I'm using direct subclasses instead of
        //leaf subclasses
        if ($this->hierarchyRootSubclasses == null )
            $this->initializeHierarchyConcreteSubclasses();

        return $this->hierarchyRootSubclasses;
    }

    private function initializeHierarchyConcreteSubclasses()
    {
        $this->hierarchyRootSubclasses = array_reduce(
            get_declared_classes(),
            function ($subclasses, $subClassName) {
                if (is_subclass_of($subClassName, $this->classHierarchyRoot->getName()))
                    $subclasses[] = new ReflectionClass($subClassName);
                return $subclasses;
            },
            array());
    }

    public static function visitMessageNameFor($aClassName)
    {
        return "visit".$aClassName;
    }

    public static function acceptMethodNotImplementedCorrectlyErrorDescriptionFor($subclassName)
    {
        return self::ACCEPT_MESSAGE_NAME . " message in " . $subclassName . " not implemented correctly";
    }

    public static function visitNotImplementedErrorDescriptionFor($className)
    {
        return self::visitMessageNameFor($className) . " not implemented";
    }

    public static function visitWithoutProperParametersErrorDescriptionFor($className)
    {
        return self::visitMessageNameFor($className) . " should receive a visitor as parameter";
    }

}

abstract class Transaction {
    public abstract function accept($visitor);
}

class Deposit extends Transaction {

    public function accept($visitor)
    {
        $visitor->visitDeposit($this);
    }
}

class Withdraw extends Transaction {
    public function accept($visitor)
    {
        $visitor->visitWithdraw($this);
    }
}

interface TransactionVisitor
{
    public function visitDeposit($aDeposit);
    public function visitWithdraw($aWithdraw);
}

abstract class ClassH1 {}

interface ClassH1Visitor {}

abstract class ClassH2 {
    public abstract function accept();
}

interface ClassH2Visitor{
}

abstract class ClassH4 {
    public abstract function accept($visitor,$invalidParameter);
}

interface ClassH4Visitor {}

abstract class ClassH5 {
    public function accept($visitor) { }
}

interface ClassH5Visitor {
}

abstract class ClassH6 {
    public abstract function accept($visitor);
}

class ClassH6Subclass extends ClassH6 {
    public function accept($visitor) {}
}

interface ClassH6Visitor { }

abstract class ClassH7 {
    public abstract function accept($visitor);
}

class ClassH7Subclass extends ClassH7 {
    public function accept($visitor) { }
}

interface ClassH7Visitor {
    public function visitClassH7Subclass();
}

abstract class ClassH8 {
    public abstract function accept($visitor);
}

class ClassH8Subclass extends ClassH8 {
    public function accept($visitor) { }
}

interface ClassH8Visitor {
    public function visitClassH8Subclass($aClassH8Subclass, $invalidParameter);
}

abstract class ClassH9 {
    public abstract function accept($visitor);
}

class ClassH9Subclass extends ClassH9 {
    public function accept($visitor) { }
}

interface ClassH9Visitor {
    public function visitClassH9Subclass($aClassH9Subclass);
}

class VisitorImplementationVerifierTest extends PHPUnit_Framework_TestCase
{
    public function verifyShouldThrowExceptionWithMessage ($reviewer,$expectedExceptionMessage) {
        try {
            $reviewer->verify();
            $this->fail();
        } catch (Exception $e) {
            $this->assertEquals($expectedExceptionMessage,$e->getMessage());
        }
    }

    public function test01_AcceptMethodShouldBeDefinedOnRootHierarchyClass()
    {
        $this->verifyShouldThrowExceptionWithMessage (
            new VisitorImplementationReviewer("ClassH1", "ClassH1Visitor"),
            VisitorImplementationReviewer::ACCEPT_MESSAGE_NOT_DEFINED );
    }

    public function test02_AcceptMessageShouldReceiveOneParameter()
    {
        $this->verifyShouldThrowExceptionWithMessage (
            new VisitorImplementationReviewer("ClassH2", "ClassH2Visitor"),
            VisitorImplementationReviewer::ACCEPT_MESSAGE_SHOULD_RECEIVE_ONE_PARAMETER );
    }

    public function test04_AcceptMessageShouldNotReceiveMoreThanOneParameter()
    {
        $this->verifyShouldThrowExceptionWithMessage (
            new VisitorImplementationReviewer("ClassH4", "ClassH4Visitor"),
            VisitorImplementationReviewer::ACCEPT_MESSAGE_SHOULD_RECEIVE_ONE_PARAMETER );
    }

    public function test05_AcceptMethodShouldBeAbstractOnRootHierarchyClass()
    {
        $this->verifyShouldThrowExceptionWithMessage (
            new VisitorImplementationReviewer("ClassH5", "ClassH5Visitor"),
            VisitorImplementationReviewer::ACCEPT_METHOD_IS_NOT_ABSTRACT);
    }

    public function test06_VisitorShouldDefineAllVisitMethodsFollowingNamingConvention()
    {
        $this->verifyShouldThrowExceptionWithMessage (
            new VisitorImplementationReviewer("ClassH6", "ClassH6Visitor"),
            VisitorImplementationReviewer::visitNotImplementedErrorDescriptionFor("ClassH6Subclass"));
    }

    public function test07_VisitMessagesCanNotBeDefinedWithoutParameters()
    {
        $this->verifyShouldThrowExceptionWithMessage (
            new VisitorImplementationReviewer("ClassH7", "ClassH7Visitor"),
            VisitorImplementationReviewer::visitWithoutProperParametersErrorDescriptionFor("ClassH7Subclass") );
    }
        
    public function test08_VisitMessagesCanNotBeDefinedWithMoreThanOneParameter()
    {
        $this->verifyShouldThrowExceptionWithMessage (
            new VisitorImplementationReviewer("ClassH8", "ClassH8Visitor"),
            VisitorImplementationReviewer::visitWithoutProperParametersErrorDescriptionFor("ClassH8Subclass"));
    }

    public function test09_AcceptImplementationInSubclassesShouldSendRightVisitMessage()
    {
        $this->verifyShouldThrowExceptionWithMessage (
            new VisitorImplementationReviewer("ClassH9", "ClassH9Visitor"),
            VisitorImplementationReviewer::acceptMethodNotImplementedCorrectlyErrorDescriptionFor("ClassH9Subclass"));
    }

    public function test10_CorrectVisitImplementationPasses()
    {
        $reviewer = new VisitorImplementationReviewer("Transaction", "TransactionVisitor");
        try {
            $reviewer->verify();
        } catch (Exception $e) {
            $this->fail();
        }
    }
}