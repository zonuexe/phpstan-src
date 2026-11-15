<?php declare(strict_types = 1);

namespace Bug15194;

interface SourceFile {
	public function getString(): string;
}

interface FileLocation {
	public function getFile(): SourceFile;
	public function getOffset(): int;
}

class Character {
    /**
     * Returns whether $character is an ASCII newline character.
     */
    public static function isNewline(?string $character): bool
    {
        return $character === "\n" || $character === "\r" || $character === "\f";
    }
}

class InterpolationMap
{
    /**
     * Given the end of a {@see FileSpan} covering an interpolated expression, returns
     * the offset of the interpolation's closing `}`.
     */
    public function expandInterpolationSpanRight(FileLocation $end): int
    {
        $source = $end->getFile()->getString();
        $i = $end->getOffset();

        while ($i < \strlen($source)) {
            $next = $source[$i++];

            if ($next === '}') {
                break;
            }

            if ($next === '/') {
                $second = $source[$i++];
                if ($second === '/') {
                    while (!Character::isNewline($source[$i++] ?? null)) {
                        // Move forward
                    }
                } elseif ($second === '*') {
                    while (true) {
                        $char = $source[$i++] ?? null;

                        if ($char !== '*') {
                            continue;
                        }

                        do {
                            $char = $source[$i++] ?? null;
                        } while ($char === '*');

                        if ($char === '/') {
                            break;
                        }
                    }
                }
            }
        }

        return $i;
    }
}


/** @param array<int, string> $a */
function storedValue(array $a, int $i): void
{
	$char = $a[$i++] ?? null;
	if ($char === '*') {
		$next = $a[$i++] ?? null;
	}
}

/** @param array<int, string> $a */
function directCheck(array $a, int $i): void
{
	if (($a[$i++] ?? null) === '*') {
		$next = $a[$i++] ?? null;
	}
}

/** @param array<int, string> $a */
function issetCheck(array $a, int $i): void
{
	if (isset($a[$i++])) {
		$next = $a[$i++] ?? null;
	}
}

/** @param array<int, string> $a */
function preDecrement(array $a, int $i): void
{
	$char = $a[--$i] ?? null;
	if ($char === '*') {
		$previous = $a[--$i] ?? null;
	}
}

/** @param array<int, string> $a */
function sameOffsetStillReported(array $a, int $i): void
{
	$char = $a[$i] ?? null;
	if ($char === '*') {
		$again = $a[$i] ?? null;
	}
}
