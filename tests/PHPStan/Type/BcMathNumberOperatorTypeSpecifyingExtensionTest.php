<?php declare(strict_types = 1);

namespace PHPStan\Type;

use Closure;
use PHPStan\Fixture\TestDecimal;
use PHPStan\Php\PhpVersion;
use PHPStan\Testing\PHPStanTestCase;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;
use function sprintf;

class BcMathNumberOperatorTypeSpecifyingExtensionTest extends PHPStanTestCase
{

	/**
	 * @dataProvider dataSigilAndSidesProvider
	 */
	public function test(string $sigil, Type $left, Type $right, string $expected): void
	{
		$extension = self::getContainer()->getByType(BcMathNumberOperatorTypeSpecifyingExtension::class);

		$this->assertTrue($extension->isOperatorSupported($sigil, $left, $right));

		$actualType = $extension->specifyType($sigil, $left, $right)->describe(VerbosityLevel::precise());
		$this->assertSame($expected, $actualType);
	}

	public static function dataSigilAndSidesProvider(): iterable
	{
		$phpVersion = self::getContainer()->getByType(PhpVersion::class);
		if (!$phpVersion->supportsBcMathNumberOperatorOverloading()) {
			return;
		}

		$supportedOperators = Closure::bind(
			static fn () => BcMathNumberOperatorTypeSpecifyingExtension::OPERATORS,
			null,
			BcMathNumberOperatorTypeSpecifyingExtension::class,
		)();
		foreach ($supportedOperators as $operator => $_) {
			yield sprintf('BcMath\Number %s BcMath\Number', $operator) => [
				'sigil' => $operator,
				'left' => new ObjectType('BcMath\Number'),
				'right' => new ObjectType('BcMath\Number'),
				'expected' => 'BcMath\Number',
			];
		}

		$oneType = new ConstantIntegerType(1);


		yield 'BcMath\Number + 1' => [
			'sigil' => '+',
			'left' => new ObjectType('BcMath\Number'),
			'right' => new ConstantIntegerType(1),
			'expected' => 'BcMath\Number',
		];

		yield 'BcMath\Number + 1' => [
			'sigil' => '+',
			'left' => new ObjectType('BcMath\Number'),
			'right' => TypeCombinator::union(
				new ObjectType('BcMath\Number'),
				new StringType(),
			),
			'expected' => 'BcMath\Number',
		];

		yield '1|2|BcMath\Number + 3|4|BcMath\Number' => [
			'sigil' => '+',
			'left' => TypeCombinator::union(
				new ObjectType('BcMath\Number'),
				new ConstantIntegerType(1),
				new ConstantIntegerType(2),
			),
			'right' => TypeCombinator::union(
				new ObjectType('BcMath\Number'),
				new ConstantIntegerType(3),
				new ConstantIntegerType(4),
			),
			'expected' => '4|5|6|BcMath\Number',
		];

		yield 'int|BcMath\Number + float|BcMath\Number' => [
			'sigil' => '+',
			'left' => TypeCombinator::union(
				new ObjectType('BcMath\Number'),
				new IntegerType(),
			),
			'right' => TypeCombinator::union(
				new ObjectType('BcMath\Number'),
				new IntegerType(),
				new FloatType(),
			),
			'expected' => 'BcMath\Number|float|int',
		];
	}

	/**
	 * @dataProvider dataNotMatchingSidesProvider
	 */
	public function testNotSupportsNotMatchingSides(string $sigil, Type $left, Type $right): void
	{
		$extension = self::getContainer()->getByType(BcMathNumberOperatorTypeSpecifyingExtension::class);

		$this->assertFalse($extension->isOperatorSupported($sigil, $left, $right));
	}

	public static function dataNotMatchingSidesProvider(): iterable
	{
		$phpVersion = self::getContainer()->getByType(PhpVersion::class);
		if (!$phpVersion->supportsBcMathNumberOperatorOverloading()) {
			$desc = sprintf("BcMath\Number is supported in PHP 8.4.0 and above. %s was given.", $phpVersion->getVersionString());
			yield $desc => [
				'sigil' => '+',
				'left' => new ObjectType('BcMath\Number'),
				'right' => new ObjectType('BcMath\Number'),
			];
			return;
		}

		$notSupportedOperators = ['&', '|', '^', '||', '&&'];
		foreach ($notSupportedOperators as $notSupportedOperator) {
			yield sprintf('Do not support %s operator', $notSupportedOperator) => [
				$notSupportedOperator,
				new ObjectType('BcMath\Number'),
				new ObjectType('BcMath\Number'),
			];
		}

		yield 'Do not support int + int' => [
			'sigil' => '+',
			'right' => new IntegerType,
			'left' => new IntegerType,
		];
	}

}
