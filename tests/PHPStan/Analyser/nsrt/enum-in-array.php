<?php // lint >= 8.1

use function PHPStan\Testing\assertType;

enum MyEnum: string
{

	case A = 'a';
	case B = 'b';
	case C = 'c';

	const SET_AB = [self::A, self::B];
	const SET_C = [self::C];
	const SET_ABC = [self::A, self::B, self::C];

	public function test1(): void
	{
		foreach (self::cases() as $enum) {
			if (in_array($enum, MyEnum::SET_AB, true)) {
				assertType('MyEnum::A|MyEnum::B', $enum);
			} elseif (in_array($enum, MyEnum::SET_C, true)) {
				assertType('MyEnum::C', $enum);
			} else {
				assertType('*NEVER*', $enum);
			}
		}
	}

	public function test2(): void
	{
		foreach (self::cases() as $enum) {
			if (in_array($enum, MyEnum::SET_ABC, true)) {
				assertType('MyEnum::A|MyEnum::B|MyEnum::C', $enum);
			} else {
				assertType('*NEVER*', $enum);
			}
		}
	}

	public function test3(): void
	{
		foreach (self::cases() as $enum) {
			if (in_array($enum, MyEnum::SET_C, true)) {
				assertType('MyEnum::C', $enum);
			} else {
				assertType('MyEnum::A|MyEnum::B', $enum);
			}
		}
	}

	public function test4(): void
	{
		foreach ([MyEnum::C] as $enum) {
			if (in_array($enum, MyEnum::SET_C, true)) {
				assertType('MyEnum::C', $enum);
			} else {
				assertType('*NEVER*', $enum);
			}
		}
	}

	public function testNegative1(): void
	{
		foreach (self::cases() as $enum) {
			if (!in_array($enum, MyEnum::SET_AB, true)) {
				assertType('MyEnum::C', $enum);
			} else {
				assertType('MyEnum::A|MyEnum::B', $enum);
			}
		}
	}

	public function testNegative2(): void
	{
		foreach (self::cases() as $enum) {
			if (!in_array($enum, MyEnum::SET_AB, true)) {
				assertType('MyEnum::C', $enum);
			} elseif (!in_array($enum, MyEnum::SET_AB, true)) {
				assertType('*NEVER*', $enum);
			}
		}
	}

	public function testNegative3(): void
	{
		foreach ([MyEnum::C] as $enum) {
			if (!in_array($enum, MyEnum::SET_C, true)) {
				assertType('*NEVER*', $enum);
			}
		}
	}

	/**
	 * @param array<MyEnum|null> $array
	 */
	public function testNegative4(MyEnum $enum, array $array): void
	{
		if (!in_array($enum, $array, true)) {
			assertType('MyEnum', $enum);
			assertType('array<MyEnum|null>', $array);
		} else {
			assertType('MyEnum', $enum);
			assertType('non-empty-array<MyEnum|null>', $array);
		}
	}

}

class InArrayEnum
{

	/** @param list<MyEnum> $list */
	public function testPositive(MyEnum $enum, array $list): void
	{
		if (in_array($enum, $list, true)) {
			return;
		}

		assertType(MyEnum::class, $enum);
		assertType('list<MyEnum>', $list);
	}

	/** @param list<MyEnum> $list */
	public function testNegative(MyEnum $enum, array $list): void
	{
		if (!in_array($enum, $list, true)) {
			return;
		}

		assertType(MyEnum::class, $enum);
		assertType('non-empty-list<MyEnum>', $list);
	}

}


class InArrayOtherFiniteType {

	const SET_AB = ['a', 'b'];
	const SET_C = ['c'];
	const SET_ABC = ['a', 'b', 'c'];

	public function test1(): void
	{
		foreach (['a', 'b', 'c'] as $item) {
			if (in_array($item, self::SET_AB, true)) {
				assertType("'a'|'b'", $item);
			} elseif (in_array($item, self::SET_C, true)) {
				assertType("'c'", $item);
			} else {
				assertType('*NEVER*', $item);
			}
		}
	}

	public function test2(): void
	{
		foreach (['a', 'b', 'c'] as $item) {
			if (in_array($item, self::SET_ABC, true)) {
				assertType("'a'|'b'|'c'", $item);
			} else {
				assertType('*NEVER*', $item);
			}
		}
	}

	public function test3(): void
	{
		foreach (['a', 'b', 'c'] as $item) {
			if (in_array($item, self::SET_C, true)) {
				assertType("'c'", $item);
			} else {
				assertType("'a'|'b'", $item);
			}
		}
	}
	public function test4(): void
	{
		foreach (['c'] as $item) {
			if (in_array($item, self::SET_C, true)) {
				assertType("'c'", $item);
			} else {
				assertType('*NEVER*', $item);
			}
		}
	}
}
