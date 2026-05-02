<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class QrCode
{
    private const DATA_CODEWORDS = [1 => 19, 2 => 34, 3 => 55, 4 => 80, 5 => 108];
    private const ECC_CODEWORDS = [1 => 7, 2 => 10, 3 => 15, 4 => 20, 5 => 26];
    private const ALIGNMENT = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30]];

    private array $exp = [];
    private array $log = [];

    public function __construct()
    {
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $this->exp[$i] = $x;
            $this->log[$x] = $i;
            $x <<= 1;
            if (($x & 0x100) !== 0) {
                $x ^= 0x11D;
            }
        }

        for ($i = 255; $i < 512; $i++) {
            $this->exp[$i] = $this->exp[$i - 255];
        }
    }

    public function svg(string $data, int $scale = 6, int $margin = 4): string
    {
        $matrix = $this->matrix($data);
        $size = count($matrix);
        $viewSize = $size + ($margin * 2);
        $paths = [];

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($matrix[$y][$x]) {
                    $paths[] = 'M' . ($x + $margin) . ',' . ($y + $margin) . 'h1v1h-1z';
                }
            }
        }

        $pixelSize = $viewSize * max(1, $scale);

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<svg xmlns="http://www.w3.org/2000/svg" width="' . $pixelSize . '" height="' . $pixelSize . '" viewBox="0 0 ' . $viewSize . ' ' . $viewSize . '" shape-rendering="crispEdges">'
            . '<rect width="100%" height="100%" fill="#fff"/>'
            . '<path fill="#000" d="' . implode('', $paths) . '"/>'
            . '</svg>';
    }

    public function dataUri(string $data): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode($this->svg($data));
    }

    private function matrix(string $data): array
    {
        $bytes = array_values(unpack('C*', $data) ?: []);
        $version = $this->chooseVersion(count($bytes));
        $size = $version * 4 + 17;
        $modules = array_fill(0, $size, array_fill(0, $size, null));
        $isFunction = array_fill(0, $size, array_fill(0, $size, false));

        $this->drawFunctionPatterns($modules, $isFunction, $version);
        $codewords = $this->encodeCodewords($bytes, $version);
        $this->drawCodewords($modules, $isFunction, $codewords);

        $bestMask = 0;
        $bestPenalty = PHP_INT_MAX;
        $bestModules = $modules;

        for ($mask = 0; $mask < 8; $mask++) {
            $trial = $modules;
            $this->applyMask($trial, $isFunction, $mask);
            $this->drawFormatBits($trial, $isFunction, $mask);
            $penalty = $this->penalty($trial);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $bestMask = $mask;
                $bestModules = $trial;
            }
        }

        $this->drawFormatBits($bestModules, $isFunction, $bestMask);

        return $bestModules;
    }

    private function chooseVersion(int $byteCount): int
    {
        foreach (self::DATA_CODEWORDS as $version => $capacity) {
            $charCountBits = $version <= 9 ? 8 : 16;
            if (4 + $charCountBits + ($byteCount * 8) <= $capacity * 8) {
                return $version;
            }
        }

        throw new RuntimeException('QR verisi cok uzun. Daha kisa bir site adresi veya token kullanin.');
    }

    private function encodeCodewords(array $bytes, int $version): array
    {
        $capacity = self::DATA_CODEWORDS[$version];
        $bits = [];
        $this->appendBits($bits, 0b0100, 4);
        $this->appendBits($bits, count($bytes), $version <= 9 ? 8 : 16);

        foreach ($bytes as $byte) {
            $this->appendBits($bits, $byte, 8);
        }

        $maxBits = $capacity * 8;
        $this->appendBits($bits, 0, min(4, $maxBits - count($bits)));
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $data = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $value = 0;
            for ($j = 0; $j < 8; $j++) {
                $value = ($value << 1) | $bits[$i + $j];
            }
            $data[] = $value;
        }

        $pad = [0xEC, 0x11];
        $padIndex = 0;
        while (count($data) < $capacity) {
            $data[] = $pad[$padIndex++ % 2];
        }

        $ecc = $this->reedSolomonRemainder($data, self::ECC_CODEWORDS[$version]);

        return array_merge($data, $ecc);
    }

    private function appendBits(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private function drawFunctionPatterns(array &$modules, array &$isFunction, int $version): void
    {
        $size = count($modules);
        $this->drawFinder($modules, $isFunction, 3, 3);
        $this->drawFinder($modules, $isFunction, $size - 4, 3);
        $this->drawFinder($modules, $isFunction, 3, $size - 4);

        for ($i = 0; $i < $size; $i++) {
            if (!$isFunction[6][$i]) {
                $this->setFunction($modules, $isFunction, $i, 6, $i % 2 === 0);
            }
            if (!$isFunction[$i][6]) {
                $this->setFunction($modules, $isFunction, 6, $i, $i % 2 === 0);
            }
        }

        foreach (self::ALIGNMENT[$version] as $cy) {
            foreach (self::ALIGNMENT[$version] as $cx) {
                if ($isFunction[$cy][$cx]) {
                    continue;
                }
                $this->drawAlignment($modules, $isFunction, $cx, $cy);
            }
        }

        $this->reserveFormat($modules, $isFunction);
        $this->setFunction($modules, $isFunction, 8, $size - 8, true);
    }

    private function drawFinder(array &$modules, array &$isFunction, int $cx, int $cy): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x < 0 || $y < 0 || $y >= count($modules) || $x >= count($modules)) {
                    continue;
                }

                $dist = max(abs($dx), abs($dy));
                $dark = $dist !== 2 && $dist !== 4;
                $this->setFunction($modules, $isFunction, $x, $y, $dark);
            }
        }
    }

    private function drawAlignment(array &$modules, array &$isFunction, int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $this->setFunction($modules, $isFunction, $cx + $dx, $cy + $dy, $dist !== 1);
            }
        }
    }

    private function reserveFormat(array &$modules, array &$isFunction): void
    {
        $size = count($modules);
        for ($i = 0; $i < 9; $i++) {
            if ($i !== 6) {
                $this->setFunction($modules, $isFunction, 8, $i, false);
                $this->setFunction($modules, $isFunction, $i, 8, false);
            }
        }

        for ($i = 0; $i < 8; $i++) {
            $this->setFunction($modules, $isFunction, $size - 1 - $i, 8, false);
            $this->setFunction($modules, $isFunction, 8, $size - 1 - $i, false);
        }
    }

    private function setFunction(array &$modules, array &$isFunction, int $x, int $y, bool $dark): void
    {
        $modules[$y][$x] = $dark;
        $isFunction[$y][$x] = true;
    }

    private function drawCodewords(array &$modules, array $isFunction, array $codewords): void
    {
        $bits = [];
        foreach ($codewords as $codeword) {
            $this->appendBits($bits, $codeword, 8);
        }

        $size = count($modules);
        $bitIndex = 0;
        $upward = true;

        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right--;
            }

            for ($vertical = 0; $vertical < $size; $vertical++) {
                $y = $upward ? $size - 1 - $vertical : $vertical;
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    if ($isFunction[$y][$x]) {
                        continue;
                    }

                    $modules[$y][$x] = (($bits[$bitIndex] ?? 0) === 1);
                    $bitIndex++;
                }
            }

            $upward = !$upward;
        }
    }

    private function applyMask(array &$modules, array $isFunction, int $mask): void
    {
        $size = count($modules);
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (!$isFunction[$y][$x] && $this->mask($mask, $x, $y)) {
                    $modules[$y][$x] = !$modules[$y][$x];
                }
            }
        }
    }

    private function mask(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0 => (($x + $y) % 2) === 0,
            1 => ($y % 2) === 0,
            2 => ($x % 3) === 0,
            3 => (($x + $y) % 3) === 0,
            4 => ((intdiv($y, 2) + intdiv($x, 3)) % 2) === 0,
            5 => (($x * $y) % 2 + ($x * $y) % 3) === 0,
            6 => ((($x * $y) % 2 + ($x * $y) % 3) % 2) === 0,
            7 => ((($x + $y) % 2 + ($x * $y) % 3) % 2) === 0,
            default => false,
        };
    }

    private function drawFormatBits(array &$modules, array &$isFunction, int $mask): void
    {
        $size = count($modules);
        $bits = $this->formatBits($mask);

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction($modules, $isFunction, 8, $i, (($bits >> $i) & 1) === 1);
        }
        $this->setFunction($modules, $isFunction, 8, 7, (($bits >> 6) & 1) === 1);
        $this->setFunction($modules, $isFunction, 8, 8, (($bits >> 7) & 1) === 1);
        $this->setFunction($modules, $isFunction, 7, 8, (($bits >> 8) & 1) === 1);
        for ($i = 9; $i < 15; $i++) {
            $this->setFunction($modules, $isFunction, 14 - $i, 8, (($bits >> $i) & 1) === 1);
        }

        for ($i = 0; $i < 8; $i++) {
            $this->setFunction($modules, $isFunction, $size - 1 - $i, 8, (($bits >> $i) & 1) === 1);
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunction($modules, $isFunction, 8, $size - 15 + $i, (($bits >> $i) & 1) === 1);
        }
        $this->setFunction($modules, $isFunction, 8, $size - 8, true);
    }

    private function formatBits(int $mask): int
    {
        $data = (0b01 << 3) | $mask;
        $bits = $data << 10;
        $generator = 0x537;

        for ($i = 14; $i >= 10; $i--) {
            if ((($bits >> $i) & 1) !== 0) {
                $bits ^= $generator << ($i - 10);
            }
        }

        return (($data << 10) | $bits) ^ 0x5412;
    }

    private function penalty(array $modules): int
    {
        $size = count($modules);
        $penalty = 0;

        for ($y = 0; $y < $size; $y++) {
            $runColor = $modules[$y][0];
            $runLength = 1;
            for ($x = 1; $x < $size; $x++) {
                if ($modules[$y][$x] === $runColor) {
                    $runLength++;
                } else {
                    if ($runLength >= 5) {
                        $penalty += 3 + ($runLength - 5);
                    }
                    $runColor = $modules[$y][$x];
                    $runLength = 1;
                }
            }
            if ($runLength >= 5) {
                $penalty += 3 + ($runLength - 5);
            }
        }

        for ($x = 0; $x < $size; $x++) {
            $runColor = $modules[0][$x];
            $runLength = 1;
            for ($y = 1; $y < $size; $y++) {
                if ($modules[$y][$x] === $runColor) {
                    $runLength++;
                } else {
                    if ($runLength >= 5) {
                        $penalty += 3 + ($runLength - 5);
                    }
                    $runColor = $modules[$y][$x];
                    $runLength = 1;
                }
            }
            if ($runLength >= 5) {
                $penalty += 3 + ($runLength - 5);
            }
        }

        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $color = $modules[$y][$x];
                if ($color === $modules[$y][$x + 1] && $color === $modules[$y + 1][$x] && $color === $modules[$y + 1][$x + 1]) {
                    $penalty += 3;
                }
            }
        }

        $dark = 0;
        foreach ($modules as $row) {
            foreach ($row as $module) {
                if ($module) {
                    $dark++;
                }
            }
        }

        $total = $size * $size;
        $percent = (int) floor(($dark * 100) / $total);
        $penalty += (int) (abs($percent - 50) / 5) * 10;

        return $penalty;
    }

    private function reedSolomonRemainder(array $data, int $degree): array
    {
        $generator = $this->reedSolomonGenerator($degree);
        $remainder = array_fill(0, $degree, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ $remainder[0];
            array_shift($remainder);
            $remainder[] = 0;

            foreach ($generator as $i => $coefficient) {
                $remainder[$i] ^= $this->multiply($coefficient, $factor);
            }
        }

        return $remainder;
    }

    private function reedSolomonGenerator(int $degree): array
    {
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($poly) + 1, 0);
            foreach ($poly as $j => $coefficient) {
                $next[$j] ^= $coefficient;
                $next[$j + 1] ^= $this->multiply($coefficient, $this->exp[$i]);
            }
            $poly = $next;
        }

        array_shift($poly);
        return $poly;
    }

    private function multiply(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }

        return $this->exp[$this->log[$a] + $this->log[$b]];
    }
}

