<?php declare(strict_types = 1);

namespace PHPStan\Type;

use BcMath\Number;
use PHPStan\Node\Expr\TypeExpr;
use PHPStan\Php\PhpVersion;
use PHPStan\Reflection\InitializerExprContext;
use PHPStan\Reflection\InitializerExprTypeResolver;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeUtils;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use function count;
use function in_array;

/**
 * @see https://wiki.php.net/rfc/support_object_type_in_bcmath
 */
final class BcMathNumberOperatorTypeSpecifyingExtension implements OperatorTypeSpecifyingExtension
{

	private const OPERATORS = [
		'-' => BinaryOp\Minus::class,
		'+' => BinaryOp\Plus::class,
		'*' => BinaryOp\Mul::class,
		'/' => BinaryOp\Div::class,
		'**' => BinaryOp\Pow::class,
		'%' => BinaryOp\Mod::class,
	];

	public function __construct(
		private PhpVersion $phpVersion,
		private InitializerExprTypeResolver $InitializerExprTypeResolver,
	) {
	}

	public function isOperatorSupported(string $operatorSigil, Type $leftSide, Type $rightSide): bool
	{
		if (!$this->phpVersion->supportsBcMathNumberOperatorOverloading()) {
			return false;
		}

		return in_array($operatorSigil, ['-', '+', '*', '/', '**', '%'], true)
			&& (
				$leftSide->isSuperTypeOf(new ObjectType('BcMath\Number'))->yes()
				|| $rightSide->isSuperTypeOf(new ObjectType('BcMath\Number'))->yes()
			);
	}

	public function specifyType(string $operatorSigil, Type $leftSide, Type $rightSide): Type
	{
		if (count($leftSide->getConstantArrays()) > 0 || count($rightSide->getConstantArrays()) > 0) {
			return new ErrorType();
		}

		$possibleTypes = [
			new ObjectType('BcMath\Number'),
		];

		$leftTypes = TypeUtils::flattenTypes(TypeCombinator::remove($leftSide, new ObjectType('BcMath\Number')));
		$rightTypes = TypeUtils::flattenTypes(TypeCombinator::remove($rightSide, new ObjectType('BcMath\Number')));
		$operator = self::OPERATORS[$operatorSigil];
		$contest = InitializerExprContext::createEmpty();

		if (count($leftTypes) === 0 xor count($rightTypes) === 0) {
			$otherType = count($leftTypes) === 0 ? $rightSide : $leftSide;
			if ($otherType->isSuperTypeOf(new ObjectType('BcMath\Number'))->no()
				&& (
					!$otherType->isInteger()->no() || !$otherType->isFloat()->no() || !$otherType->isNumericString()->no()
				)
			) {
				return new ErrorType();
			}
		}

		foreach ($leftTypes as $leftType) {
			foreach ($rightTypes as $rightType) {
				$node = new $operator(new TypeExpr($leftType), new TypeExpr($rightType));
				$possibleTypes[] = $this->InitializerExprTypeResolver->getType($node, $contest);
			}
		}

		return TypeCombinator::union(...$possibleTypes);
	}

}
