<?php

namespace ReplaceFunctions;

use function PHPStan\Testing\assertType;

function ($mixed) {

	$array = ['a' => 'a', 'b' => 'b'];
	$string = 'str';

	$arrayOrString = [];
	if (doFoo()) {
		$arrayOrString = 'foo';
	}

	/** @var callable[] $callbacks */
	$callbacks = [];

	$expectedString = str_replace('aaa', 'bbb', $string);
	$expectedArray = str_replace('aaa', 'bbb', $array);
	$expectedArrayOrString = str_replace('aaa', 'bbb', $arrayOrString);
	$expectedBenevolentArrayOrString = str_replace('aaa', 'bbb', $mixed);

	$anotherExpectedString = preg_replace('aaa', 'bbb', $string);
	$anotherExpectedArray = preg_replace('aaa', 'bbb', $array);
	$anotherExpectedArrayOrString = preg_replace('aaa', 'bbb', $arrayOrString);

	$expectedString2 = preg_replace_callback('aaa', function () {}, $string);
	$expectedArray2 = preg_replace_callback('aaa', function () {}, $array);
	$expectedArrayOrString2 = preg_replace_callback('aaa', function () {}, $arrayOrString);

	$expectedString3 = str_ireplace('aaa', 'bbb', $string);
	$expectedArray3 = str_ireplace('aaa', 'bbb', $array);
	$expectedArrayOrString3 = str_ireplace('aaa', 'bbb', $arrayOrString);
	$expectedBenevolentArrayOrString3 = str_ireplace('aaa', 'bbb', $mixed);

	$expectedString4 = mb_ereg_replace('aaa', 'bbb', $string);
	$expectedString5 = mb_ereg_replace_callback('aaa', function () {}, $string);
	$expectedArrayOrString4 = mb_ereg_replace('aaa', 'bbb', $arrayOrString);
	$expectedArrayOrString5 = mb_ereg_replace_callback('aaa', function () {}, $arrayOrString);
	$lowercaseCallback = static function (array $matches): string {
		return strtolower($matches[0]);
	};
	$expectedString6 = preg_replace_callback('aaa', $lowercaseCallback, $string);
	$expectedArray6 = preg_replace_callback('aaa', $lowercaseCallback, $array);
	$expectedArrayOrString6 = preg_replace_callback('aaa', $lowercaseCallback, $arrayOrString);
	$expectedString7 = mb_ereg_replace_callback('aaa', $lowercaseCallback, $string);
	$expectedArrayOrString7 = mb_ereg_replace_callback('aaa', $lowercaseCallback, $arrayOrString);
	$lowercaseCallbacks = ['/[ab]+/' => $lowercaseCallback];
	$expectedString8 = preg_replace_callback_array($lowercaseCallbacks, $string);
	$expectedArray8 = preg_replace_callback_array($lowercaseCallbacks, $array);
	$expectedArrayOrString8 = preg_replace_callback_array($lowercaseCallbacks, $arrayOrString);

	/** @var Foo[] $arr */
	$arr = doFoo();

	foreach ($arr as $intOrStringKey => $value) {
		assertType('lowercase-string&non-falsy-string', $expectedString);
		assertType('string|null', $expectedString2);
		assertType('(lowercase-string&non-falsy-string)|null', $anotherExpectedString);
		assertType('array{a: lowercase-string&non-falsy-string, b: lowercase-string&non-falsy-string}', $expectedArray);
		assertType('array{a?: string, b?: string}', $expectedArray2);
		assertType('array{a?: lowercase-string&non-falsy-string, b?: lowercase-string&non-falsy-string}', $anotherExpectedArray);
		assertType('array{}|(lowercase-string&non-falsy-string)', $expectedArrayOrString);
		assertType('(array<string>|string)', $expectedBenevolentArrayOrString);
		assertType('array{}|string|null', $expectedArrayOrString2);
		assertType('array{}|(lowercase-string&non-falsy-string)|null', $anotherExpectedArrayOrString);
		assertType('array{a?: string, b?: string}', preg_replace_callback_array($callbacks, $array));
		assertType('string|null', preg_replace_callback_array($callbacks, $string));
		assertType('lowercase-string&non-falsy-string', $expectedString4);
		assertType('string', $expectedString5);
		assertType('array{}|(lowercase-string&non-falsy-string)', $expectedArrayOrString4);
		assertType('array{}|string', $expectedArrayOrString5);
		assertType('lowercase-string|null', $expectedString6);
		assertType('array{a?: lowercase-string, b?: lowercase-string}', $expectedArray6);
		assertType('array{}|lowercase-string|null', $expectedArrayOrString6);
		assertType('lowercase-string', $expectedString7);
		assertType('array{}|lowercase-string', $expectedArrayOrString7);
		assertType('lowercase-string|null', $expectedString8);
		assertType('array{a?: lowercase-string, b?: lowercase-string}', $expectedArray8);
		assertType('array{}|lowercase-string|null', $expectedArrayOrString8);
		assertType('string', str_replace('.', ':', $intOrStringKey));
		assertType('string', str_ireplace('.', ':', $intOrStringKey));
	}

};
