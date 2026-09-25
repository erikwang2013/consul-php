<?php

namespace Erikwang2013\Consul\Tests\Support;

use Erikwang2013\Consul\Support\Pet;
use PHPUnit\Framework\TestCase;

class PetTest extends TestCase
{
    public function testArtIsPlainTextWhenColorIsDisabled(): void
    {
        $art = Pet::art(false);

        $this->assertStringNotContainsString("\033", $art);
        $this->assertStringContainsString('●', $art);
        $this->assertStringContainsString('╱╲╱╲╱╲', $art);
    }

    public function testArtIsPaintedWhenColorIsForced(): void
    {
        $art = Pet::art(true);

        $this->assertStringContainsString("\033[38;5;141m", $art);
        $this->assertStringContainsString("\033[38;5;42m●", $art);
        $this->assertStringContainsString("\033[39m", $art);
    }

    public function testSayKeepsAntennaUnderBubbleTail(): void
    {
        $lines = explode("\n", trim(Pet::say('consul-php', false)));

        // 气泡底边、天线球所在行；两者之间还有一行竖直的尾巴
        $this->assertSame($this->columnsBefore($lines[2], '┬'), $this->columnsBefore($lines[3], '│'));
        $this->assertSame($this->columnsBefore($lines[2], '┬'), $this->columnsBefore($lines[4], '●'));
    }

    public function testSayFlattensNewlines(): void
    {
        $lines = explode("\n", trim(Pet::say("hello\nworld", false)));

        $this->assertSame(4 + count(explode("\n", trim(Pet::art(false)))), count($lines));
        $this->assertStringContainsString('hello world', $lines[1]);
    }

    /**
     * 目标字符前的显示列数（图案与气泡都只含单列字符）。
     */
    private function columnsBefore(string $line, string $needle): int
    {
        $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
        $index = array_search($needle, $chars, true);

        $this->assertNotFalse($index, "第 [$needle] 个字符未在行内找到: $line");

        return $index;
    }
}
