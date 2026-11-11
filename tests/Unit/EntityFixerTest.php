<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Tests\Unit;

use ISBNLab\Onix\Internal\EntityFixer;
use ISBNLab\Onix\OnixReader;
use PHPUnit\Framework\TestCase;

final class EntityFixerTest extends TestCase
{
    public function testRewritesHtmlEntitiesAndLeavesXmlOnes(): void
    {
        self::assertSame('caf&#233; &amp; &lt;b&gt; &#8212; &#169; &#38; &#x26;', EntityFixer::fix('caf&eacute; &amp; &lt;b&gt; &mdash; &copy; &#38; &#x26;'));
        self::assertSame('&amp;bogus;', EntityFixer::fix('&bogus;'));
        self::assertSame('no entities', EntityFixer::fix('no entities'));
    }

    public function testEntitiesSplitAcrossReadChunksAreRepaired(): void
    {
        // Place an entity across every offset around PHP's 8192-byte stream chunk.
        $file = sys_get_temp_dir() . '/isbnlab-onix-chunk-' . getmypid() . '.xml';
        $products = '';
        for ($i = 0; $i < 400; ++$i) {
            $products .= '<Product><RecordReference>r' . $i . '</RecordReference><DescriptiveDetail><TitleDetail><TitleType>01</TitleType><TitleElement><TitleElementLevel>01</TitleElementLevel><TitleText>' . str_repeat('x', $i % 13) . '&eacute;&mdash;</TitleText></TitleElement></TitleDetail></DescriptiveDetail></Product>';
        }
        file_put_contents($file, '<ONIXMessage release="3.0">' . $products . '</ONIXMessage>');

        try {
            $reader = OnixReader::open($file);
            $count = 0;
            foreach ($reader as $index => $product) {
                ++$count;
                self::assertStringEndsWith('é—', (string) $product->title(), 'product ' . $index);
            }
            self::assertSame(400, $count);
            self::assertSame(0, $reader->errorCount());
        } finally {
            unlink($file);
        }
    }
}
