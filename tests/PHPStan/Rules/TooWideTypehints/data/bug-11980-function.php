<?php

namespace Bug11980Function;

/**
 * @param array<int, array<string, int>> $tokens
 * @param int                            $stackPtr
 *
 * @return int|void
 */
function process($tokens, $stackPtr)
{
	if (empty($tokens[$stackPtr]['nested_parenthesis']) === false) {
		// Not a stand-alone statement.
		return;
	}

	$end = 10;

	if ($tokens[$end]['code'] !== 10
		&& $tokens[$end]['code'] !== 20
	) {
		// Not a stand-alone statement.
		return $end;
	}
}

/**
 * @param array<int, array<string, int>> $tokens
 * @param int                            $stackPtr
 *
 * @return int|void
 */
function process2($tokens, $stackPtr)
{
	if (empty($tokens[$stackPtr]['nested_parenthesis']) === false) {
		// Not a stand-alone statement.
		return null;
	}

	$end = 10;

	if ($tokens[$end]['code'] !== 10
		&& $tokens[$end]['code'] !== 20
	) {
		// Not a stand-alone statement.
		return $end;
	}
}
