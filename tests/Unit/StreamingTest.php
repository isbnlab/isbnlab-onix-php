<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Tests\Unit;

use ISBNLab\Onix\OnixReader;
use PHPUnit\Framework\TestCase;

final class StreamingTest extends TestCase
{
    private const PRODUCTS = 20000;

    private static string $file;

    public static function setUpBeforeClass(): void
    {
        self::$file = sys_get_temp_dir() . '/isbnlab-onix-stream-' . getmypid() . '.xml';
        $handle = fopen(self::$file, 'w');
        self::assertIsResource($handle);
        fwrite($handle, '<?xml version="1.0" encoding="UTF-8"?><ONIXMessage release="3.0"><Header><Sender><SenderName>Bulk</SenderName></Sender></Header>');
        $description = str_repeat('A long description &eacute; with an entity. ', 40);
        for ($i = 1; $i <= self::PRODUCTS; ++$i) {
            fwrite($handle, sprintf(
                '<Product><RecordReference>r%1$d</RecordReference><ProductIdentifier><ProductIDType>01</ProductIDType><IDValue>%1$d</IDValue></ProductIdentifier>'
                . '<DescriptiveDetail><TitleDetail><TitleType>01</TitleType><TitleElement><TitleElementLevel>01</TitleElementLevel><TitleText>Title %1$d</TitleText></TitleElement></TitleDetail></DescriptiveDetail>'
                . '<CollateralDetail><TextContent><TextType>03</TextType><Text>%2$s</Text></TextContent></CollateralDetail></Product>',
                $i,
                $description,
            ));
        }
        fwrite($handle, '</ONIXMessage>');
        fclose($handle);
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$file);
        @unlink(self::$file . '.gz');
    }

    public function testMemoryStaysFlatAcrossALargeFile(): void
    {
        self::assertGreaterThan(30_000_000, filesize(self::$file));
        $count = 0;
        $baseline = null;
        $peakGrowth = 0;
        foreach (OnixReader::open(self::$file) as $index => $product) {
            ++$count;
            if (1000 === $index) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            } elseif (null !== $baseline && 0 === $index % 1000) {
                $peakGrowth = max($peakGrowth, memory_get_usage() - $baseline);
            }
            if (self::PRODUCTS === $index) {
                self::assertSame('Title ' . self::PRODUCTS, $product->title());
                self::assertStringContainsString('é', $product->description()->text ?? '');
            }
        }

        self::assertSame(self::PRODUCTS, $count);
        self::assertLessThan(1_000_000, $peakGrowth, 'memory grew while streaming');
    }

    public function testReadsGzipFiles(): void
    {
        if (!\function_exists('gzopen')) {
            self::markTestSkipped('zlib is not available');
        }
        $source = fopen(self::$file, 'r');
        $target = gzopen(self::$file . '.gz', 'w1');
        self::assertIsResource($source);
        self::assertNotFalse($target);
        while (!feof($source)) {
            gzwrite($target, (string) fread($source, 1 << 20));
        }
        fclose($source);
        gzclose($target);

        $reader = OnixReader::open(self::$file . '.gz');
        self::assertSame('Bulk', $reader->header()?->senderName);
        $count = 0;
        foreach ($reader->rawProducts() as $xml) {
            ++$count;
        }
        self::assertSame(self::PRODUCTS, $count);
    }
}
