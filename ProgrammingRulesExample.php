<?php
/*
 * Developed by Hernán Wilkinson - 10Pines SRL
 * License:
 * This work is licensed under the
 * Creative Commons Attribution-NonCommercial-ShareAlike 3.0 Unported License.
 * To view a copy of this license, visit http://creativecommons.org/licenses/by-nc-sa/3.0/
 * or send a letter to Creative Commons, 444 Castro Street, Suite 900, Mountain View,
 * California, 94041, USA.
 *
 */
class ValidClass {
    private $va1;

    public function method1(){

    }
}

class InvalidClass {
    private $var1;
    private $var2;

    public function method1(){

    }

    public function method2(){

    }
}

class InvalidClassWithRightRuleExceptions {
    private $var1;
    private $var2;

    public function method1(){

    }

    public function method2(){

    }

    public static function programmingRuleExceptions(){
        return array (
            new ClassRuleException(__CLASS__,"NumberOfMethodsPerClassRule","It is ok because...","Hernan"),
            new ClassRuleException(__CLASS__,"NumberOfPropertiesPerClassRule","It is ok because...","Hernan")
        );
    }
}

class InvalidClassWithLessRuleExceptions {
    private $var1;
    private $var2;

    public function method1(){

    }

    public function method2(){

    }

    public static function programmingRuleExceptions(){
        return array (
            new ClassRuleException(__CLASS__,"NumberOfMethodsPerClassRule","It is ok because...","Hernan"),
        );
    }
}

class InvalidClassWithMoreRuleExceptions {
    private $var1;
    private $var2;

    public function method1(){

    }

    public static function programmingRuleExceptions(){
        return array (
            new ClassRuleException(__CLASS__,"NumberOfMethodsPerClassRule","It is ok because...","Hernan"),
            new ClassRuleException(__CLASS__,"NumberOfPropertiesPerClassRule","It is ok because...","Hernan")
        );
    }
}

class ClassRuleException {
    private $className;
    private $ruleName;
    private $reason;
    private $owner;

    public function __construct($className,$ruleName,$reason,$owner){
        $this->className = $className;
        $this->ruleName = $ruleName;
        $this->reason = $reason;
        $this->owner = $owner;
    }

    public function filters($aRule) {
        return $aRule->isNamed($this->ruleName) && $aRule->isForClassNamed($this->className);
    }

    public function isForClassNamed($aClassName) {
        return $this->className == $aClassName;
    }

    public function isForRuleNamed($aRuleName){
        return $this->ruleName == $aRuleName;
    }
}

abstract class ProgrammingClassRule {
    protected $className;
    protected $limit;

    public function __construct($className,$limit){
        $this->className = $className;
        $this->limit = $limit;
    }

    public function className(){
        return $this->className;
    }

    public abstract function isNamed($aName);

    public function isForClassNamed($aName){
        return $aName == $this->className;
    }

    public abstract function doesHold();
}

class NumberOfMethodsPerClassRule extends ProgrammingClassRule {

    public function doesHold(){
        $reflector = new ReflectionClass($this->className);
        $numberOfMethods = sizeof($reflector->getMethods());
        return $numberOfMethods <= $this->limit;
    }

    public function isNamed($aName) {
        return $aName == __CLASS__;
    }
}

class NumberOfPropertiesPerClassRule extends ProgrammingClassRule {

    public function doesHold(){
        $reflector = new ReflectionClass($this->className);
        $numberOfProperties = sizeof($reflector->getProperties());
        return $numberOfProperties <= $this->limit;
    }

    public function isNamed($aName) {
        return $aName == __CLASS__;
    }
}

class ProgrammingRulesAutomaticReviwer {
    private $rules;
    private $failingRules;
    private $exceptions;

    const PROGRAMMING_RULE_EXCEPTIONS_MESSAGE_NAME = "programmingRuleExceptions";

    public function __construct($rules){
        $this->rules = $rules;
    }

    public function notExpectedFailingRules(){
        $notExpectedFailingRules = array_filter(
            $this->failingRules(),
            function ($aRule) { return empty($this->exceptionsOf($aRule)); });

        return $notExpectedFailingRules;
    }

    public function notUsedExceptions(){
        $notUsedExceptions = array_filter(
            $this->exceptions(),
            function($aRuleException) { return empty($this->failedRulesFilteredBy($aRuleException));});

        return $notUsedExceptions;
    }

    public function failingRules()
    {
        if ($this->failingRules==null)
            $this->failingRules = array_filter($this->rules, function ($aRule) { return !$aRule->doesHold(); });

        return $this->failingRules;
    }

    public function exceptions()
    {
        if ($this->exceptions==null)
            $this->exceptions = array_reduce(
                $this->inspectedClasses(),
                function ($exceptions, $inspectedClass) {
                    $inspectedClassExceptions = $this->rulesExceptionsOf($inspectedClass);
                    return array_merge($exceptions,$inspectedClassExceptions); },
                array());

        return $this->exceptions;
    }

    public function exceptionsOf($aRule)
    {
        return array_filter(
            $this->exceptions(),
            function ($aRuleException) use ($aRule) {return $aRuleException->filters($aRule); });
    }

    public function failedRulesFilteredBy($aRuleException){
        return array_filter(
            $this->failingRules(),
            function ($aFailingRule) use ($aRuleException) { return $aRuleException->filters($aFailingRule); } );
    }

    public function rulesExceptionsOf($aClass){
        try {
            $ruleExceptionsMethod = new ReflectionMethod($aClass, self::PROGRAMMING_RULE_EXCEPTIONS_MESSAGE_NAME);
            return $ruleExceptionsMethod->invoke(null);
        } catch (ReflectionException $e) {
            return array();
        }
    }

    public function inspectedClasses()
    {
        return array_unique(array_map(
            function ($aRule) { return $aRule->className(); },
            $this->rules));
    }
}

class ProgrammingRuleTest extends PHPUnit_Framework_TestCase {

    public function testValidNumberOfMethods(){
        $rule = new NumberOfMethodsPerClassRule ("ValidClass", 1);
        $this->assertTrue($rule->doesHold());
    }

    public function testInvalidNumberOfMethods(){
        $rule = new NumberOfMethodsPerClassRule ("InvalidClass", 1);
        $this->assertFalse($rule->doesHold());
    }

    public function testValidNumberOfProperties(){
        $rule = new NumberOfPropertiesPerClassRule ("ValidClass",1);
        $this->assertTrue($rule->doesHold());
    }

    public function testInvalidNumberOfProperties(){
        $rule = new NumberOfPropertiesPerClassRule ("InvalidClass",1);
        $this->assertFalse($rule->doesHold());
    }

    public function testAutomaticReviewerDoesNotReportFailingRulesWhenAllRulesHold(){
        $reviewer = new ProgrammingRulesAutomaticReviwer(array(
            new NumberOfMethodsPerClassRule("ValidClass",1),
            new NumberOfPropertiesPerClassRule("ValidClass",1)));

        $this->assertEmpty($reviewer->notExpectedFailingRules());
    }

    public function testAutomaticReviewerReportsTheFailingRulesThatDoNotHold(){
        $numberOfMethodsPerClassRule = new NumberOfMethodsPerClassRule("InvalidClass", 1);
        $numberOfPropertiesPerClassRule = new NumberOfPropertiesPerClassRule("InvalidClass", 1);
        $reviewer = new ProgrammingRulesAutomaticReviwer(array(
            $numberOfMethodsPerClassRule,
            $numberOfPropertiesPerClassRule));

        $failingRules = $reviewer->notExpectedFailingRules();
        $this->assertCount(2,$failingRules);
        $this->assertContains($numberOfMethodsPerClassRule,$failingRules);
        $this->assertContains($numberOfPropertiesPerClassRule,$failingRules);
    }

    public function testAutomaticReviewerDoesNotReportErrorsForRulesThatDoNotHoldAndHaveExceptions(){
        $numberOfMethodsPerClassRule = new NumberOfMethodsPerClassRule("InvalidClassWithRightRuleExceptions", 1);
        $numberOfPropertiesPerClassRule = new NumberOfPropertiesPerClassRule("InvalidClassWithRightRuleExceptions", 1);
        $reviewer = new ProgrammingRulesAutomaticReviwer(array(
            $numberOfMethodsPerClassRule,
            $numberOfPropertiesPerClassRule));

        $this->assertEmpty($reviewer->notExpectedFailingRules());
    }

    public function testAutomaticReviewerReportsFailingRulesThatDoNotHoldAndDoNotHaveExceptions(){
        $numberOfMethodsPerClassRule = new NumberOfMethodsPerClassRule("InvalidClassWithLessRuleExceptions", 1);
        $numberOfPropertiesPerClassRule = new NumberOfPropertiesPerClassRule("InvalidClassWithLessRuleExceptions", 1);
        $reviewer = new ProgrammingRulesAutomaticReviwer(array(
            $numberOfMethodsPerClassRule,
            $numberOfPropertiesPerClassRule));

        $failingRules = $reviewer->notExpectedFailingRules();
        $this->assertCount(1,$failingRules);
        $this->assertContains($numberOfPropertiesPerClassRule,$failingRules);
    }

    public function testAutomaticReviewerReportsNotUsedExceptions(){
        $numberOfMethodsPerClassRule = new NumberOfMethodsPerClassRule("InvalidClassWithMoreRuleExceptions", 2);
        $numberOfPropertiesPerClassRule = new NumberOfPropertiesPerClassRule("InvalidClassWithMoreRuleExceptions", 1);
        $reviewer = new ProgrammingRulesAutomaticReviwer(array(
            $numberOfMethodsPerClassRule,
            $numberOfPropertiesPerClassRule));

        $notUsedExceptions = $reviewer->notUsedExceptions();
        $this->assertCount(1,$notUsedExceptions);
        $notUsedException = $notUsedExceptions[0];
        $this->assertTrue($notUsedException->isForClassNamed("InvalidClassWithMoreRuleExceptions"));
        $this->assertTrue($notUsedException->isForRuleNamed("NumberOfMethodsPerClassRule"));

    }
}