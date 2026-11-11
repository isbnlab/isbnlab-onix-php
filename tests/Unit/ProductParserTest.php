<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Tests\Unit;

use ISBNLab\Onix\Exception\InvalidProductException;
use ISBNLab\Onix\OnixReader;
use ISBNLab\Onix\OnixVersion;
use ISBNLab\Onix\ProductParser;
use ISBNLab\Onix\ReaderOptions;
use PHPUnit\Framework\TestCase;

final class ProductParserTest extends TestCase
{
    public function testRawProductsCanBeStagedAndParsedLater(): void
    {
        $reader = OnixReader::open(__DIR__ . '/../Fixtures/onix30-short.xml');
        $parser = new ProductParser();

        $raw = iterator_to_array($reader->rawProducts());
        self::assertSame([1, 2, 3], array_keys($raw));
        self::assertStringStartsWith('<product', $raw[1]);

        // A stored fragment has no message around it, so its version comes from its structure.
        $product = $parser->parse($raw[1]);
        self::assertSame(OnixVersion::V30, $product->version);
        self::assertEquals(iterator_to_array($reader)[1]->title(), $product->title());
    }

    public function testParsesTheFirstProductOfAWholeMessage(): void
    {
        $xml = (string) file_get_contents(__DIR__ . '/../Fixtures/onix21-reference.xml');
        $parser = new ProductParser();

        $product = $parser->parse($xml);
        self::assertSame('EXB-9781234567897', $product->recordReference);
        self::assertSame(OnixVersion::V21, $product->version);
        self::assertSame('Example Books Pty Ltd', $parser->parseHeader($xml)?->senderName);
    }

    public function testStructureWinsOverAMislabelledMessage(): void
    {
        $xml = '<ONIXMessage release="3.0"><Product><RecordReference>x</RecordReference><Title><TitleType>01</TitleType><TitleText>Old shape</TitleText></Title></Product></ONIXMessage>';

        $product = (new ProductParser())->parse($xml);
        self::assertSame(OnixVersion::V21, $product->version);
        self::assertSame('Old shape', $product->title());
    }

    public function testVersionCanBeForced(): void
    {
        $xml = '<Product><RecordReference>x</RecordReference><Title><TitleType>01</TitleType><TitleText>Old shape</TitleText></Title></Product>';

        $product = (new ProductParser(new ReaderOptions(version: OnixVersion::V30)))->parse($xml);
        self::assertSame(OnixVersion::V30, $product->version);
        self::assertNull($product->title());
    }

    public function testOnix31IsReadAsOnix3(): void
    {
        $xml = '<ONIXMessage release="3.1" xmlns="http://ns.editeur.org/onix/3.1/reference"><Product><RecordReference>x</RecordReference><DescriptiveDetail><TitleDetail><TitleType>01</TitleType><TitleElement><TitleElementLevel>01</TitleElementLevel><TitleText>New</TitleText></TitleElement></TitleDetail></DescriptiveDetail></Product></ONIXMessage>';

        $reader = OnixReader::fromString($xml);
        $products = iterator_to_array($reader);
        self::assertSame(OnixVersion::V31, $reader->version());
        self::assertSame(OnixVersion::V31, $products[1]->version);
        self::assertSame('New', $products[1]->title());
    }

    public function testMalformedXmlThrows(): void
    {
        try {
            (new ProductParser())->parse('<Product><RecordReference>abc</RecordReference><Title>');
            self::fail('Expected an exception');
        } catch (InvalidProductException $e) {
            self::assertStringStartsWith('Malformed XML at line 1', $e->getMessage());
            self::assertSame('abc', $e->error?->recordReference);
        }
    }

    public function testXmlWithoutAProductThrows(): void
    {
        $this->expectException(InvalidProductException::class);
        $this->expectExceptionMessage('No Product element found');
        (new ProductParser())->parse('<ONIXMessage><Header/></ONIXMessage>');
    }

    public function testEmptyStringThrows(): void
    {
        $this->expectException(InvalidProductException::class);
        (new ProductParser())->parse('');
    }

    public function testExternalEntitiesAreNotLoaded(): void
    {
        $secret = tempnam(sys_get_temp_dir(), 'onix');
        self::assertIsString($secret);
        file_put_contents($secret, 'SECRET');
        $xml = '<?xml version="1.0"?><!DOCTYPE Product [<!ENTITY x SYSTEM "file://' . $secret . '">]><Product><RecordReference>a&x;b</RecordReference></Product>';

        try {
            $product = (new ProductParser())->parse($xml);
            self::assertStringNotContainsString('SECRET', (string) $product->recordReference);
        } catch (InvalidProductException) {
            $this->addToAssertionCount(1);
        } finally {
            unlink($secret);
        }
    }
}
