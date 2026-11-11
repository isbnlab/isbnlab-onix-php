# ONIX for Books reader for PHP

[![Tests](https://github.com/isbnlab/isbnlab-onix-php/actions/workflows/tests.yml/badge.svg)](https://github.com/isbnlab/isbnlab-onix-php/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/isbnlab/onix.svg)](https://packagist.org/packages/isbnlab/onix)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

`isbnlab/onix` reads ONIX for Books files, the XML format publishers and distributors use to
send book metadata, into typed, read-only PHP objects. It streams, so a multi-gigabyte feed is
read one product at a time in flat memory, and it is tolerant of the messy ONIX found in real
feeds.

**Why this exists:** the PHP ONIX readers most people found were abandoned or ONIX 2.1 only,
and real feeds still mix ONIX 3.0, 3.1 and 2.1, reference names and short tags.

- ONIX 3.0 and 3.1, plus ONIX 2.1 (still sent by many suppliers), mapped to one model
- Reference names (`<ProductIdentifier>`) and short tags (`<productidentifier>`), detected per element
- Streaming with `XMLReader`; reads `.xml.gz` directly
- Typed objects for the header and each product: identifiers, titles, contributors, edition,
  languages, extents, subjects (BISAC, Thema, keywords), texts, publisher and imprint, dates and
  status, form and measurements, sales rights, supply details and prices, related products and
  works, collections and series, cover and other resource links
- Every coded value keeps its code list number and value, and gets EDItEUR's label from the code lists included in the package
- Unknown elements are ignored; bad records are reported and skipped, not fatal
- No framework dependency; PHP 8.1 or later with the standard `dom`, `libxml` and `xmlreader` extensions

## Install

```bash
composer require isbnlab/onix
```

## Quick start

```php
use ISBNLab\Onix\OnixReader;

$reader = OnixReader::open('/path/to/feed.xml'); // or feed.xml.gz

echo $reader->header()?->senderName, "\n";

foreach ($reader as $position => $product) {
    if ($product->isDeleted()) {
        continue;
    }

    echo $product->isbn13(), ' ', $product->title(), "\n";
    echo '  by ', implode(', ', array_map(fn ($c) => $c->displayName(), $product->authors())), "\n";
    echo '  ', $product->productForm?->value, ', ', $product->pageCount() ?? '?', " pages\n";
    echo '  published ', $product->publicationDate()?->toDateTime()?->format('Y-m-d') ?? 'n/a',
         ' by ', $product->publisherName(), "\n";
    echo '  BISAC ', implode(' ', $product->bisacCodes()), "\n";
    echo '  cover ', $product->frontCoverUrl() ?? 'none', "\n";
    echo '  USD ', $product->price('USD')?->amount ?? 'n/a', "\n";
    echo '  ', $product->description()?->plainText(), "\n";
}

foreach ($reader->errors() as $error) {
    echo "Skipped product #{$error->index} ({$error->recordReference}): {$error->message}\n";
}
```

Each `Product` exposes the full data as public read-only properties (`$product->identifiers`,
`$product->contributors`, `$product->subjects`, `$product->supplyDetails`...) and convenience
methods for the common questions (`isbn13()`, `title()`, `subtitle()`, `authors()`, `series()`,
`pageCount()`, `languageCode()`, `bisacCodes()`, `themaCodes()`, `keywords()`, `description()`,
`publisherName()`, `imprintName()`, `publicationDate()`, `workIdentifiers()`, `frontCoverUrl()`,
`prices()`, `price($currency)`, `measure($type)`).

## Coded values and labels

ONIX uses codes from EDItEUR's code lists ("15" is an ISBN-13, "BC" a paperback). Every coded
value is a `Code` holding the list number, the value and, when available, the label:

```php
$product->productForm->list;   // 150
$product->productForm->value;  // "BC"
$product->productForm->label;  // "Paperback / softback"
$product->productForm->is('BB', 'BC'); // true
```

The labels come from EDItEUR's code lists, included unchanged in
[data/codelists](data/codelists) (currently Issue 75). They are EDItEUR's copyright, used under
[EDItEUR's licence](https://doi.org/10.4400/nwgj), not the MIT License; see that folder's README.
They are read once, the first time a label is needed.

To skip labels, or to use a different issue:

```php
use ISBNLab\Onix\CodeList\EditeurCodeLists;
use ISBNLab\Onix\CodeList\NoCodeLists;
use ISBNLab\Onix\ReaderOptions;

// No labels: codes only.
$reader = OnixReader::open('feed.xml', new ReaderOptions(codeLists: new NoCodeLists()));

// A file you downloaded from https://www.editeur.org/14/Code-Lists/
$options = new ReaderOptions(codeLists: EditeurCodeLists::fromFile('ONIX_BookProduct_Codelists_Issue_76.json'));
```

`EditeurDownload::latestInto($directory)` downloads the newest issue from EDItEUR for you. A
downloaded file can also be compiled into a PHP file, which OPcache keeps in memory:

```bash
vendor/bin/onix-codelists ONIX_BookProduct_Codelists_Issue_76.json var/onix-codelists.php
```

```php
use ISBNLab\Onix\CodeList\ArrayCodeLists;

$options = new ReaderOptions(codeLists: ArrayCodeLists::fromPhpFile('var/onix-codelists.php'));
```

EDItEUR publishes a new issue about four times a year, and new releases of this package include
it. You can also implement the `CodeLists` interface yourself, for example to translate labels.

## Bad records and broken files

Real feeds contain records that cannot be used. The reader skips them and keeps going:

```php
use ISBNLab\Onix\ProductError;
use ISBNLab\Onix\ReaderOptions;

$reader = OnixReader::open('feed.xml', new ReaderOptions(
    onError: function (ProductError $error): void {
        // $error->index, $error->recordReference, $error->message, $error->xml
    },
));
```

- A product with neither a `RecordReference` nor any identifier is skipped and reported.
- Values that are present but unreadable (a price of "n/a", a page count of "about 300") become
  `null`, and a note is added to `$product->warnings`.
- Unknown and vendor-specific elements are ignored.
- HTML named entities such as `&eacute;` (common in ONIX 2.1 files that relied on the DTD) are
  converted, so they do not break the XML. The DTD itself is never fetched, and external
  entities are not loaded.
- If the file stops being well-formed XML (for example, a truncated download), reading ends
  with a `MalformedXmlException` that gives the line. Products before the fault have already
  been returned, apart from possibly the last few, because libxml reads a few kilobytes ahead.

`ReaderOptions(throwOnError: true)` throws an `InvalidProductException` at the first bad
product instead. `errors()` keeps the first 1,000 errors (`maxStoredErrors`); `errorCount()` is
always exact.

## Parsing one product

To stage raw records and parse them later (or to parse the response of a lookup API), split the
file without parsing, then parse each fragment with `ProductParser`:

```php
use ISBNLab\Onix\ProductParser;

foreach ($reader->rawProducts() as $position => $xml) {
    $storage->save($position, $xml);
}

$product = (new ProductParser())->parse($xml); // a <Product> element or a whole message
```

## ONIX versions

The version is taken from the message's `release` attribute or namespace, and checked against
each product's own structure, so a stored fragment or a mislabelled file is still read correctly.
`ReaderOptions(version: OnixVersion::V21)` forces a version.

ONIX 3.1 is read like ONIX 3.0 and reported as `OnixVersion::V31`. ONIX 2.1 products are mapped
onto the same model as ONIX 3.0:

| ONIX 2.1 | Becomes |
|---|---|
| `Title`, `DistinctiveTitle` | `titles` (title detail with a product-level element) |
| `Series`, `Set` | `collections` (`series()`) |
| `NumberOfPages` | `numberOfPages` (used first by `pageCount()`) |
| `BASICMainSubject`, `BICMainSubject`, `MainSubject` | main `subjects` |
| `OtherText` | `texts` (text types from list 33, so `description()` looks for 01, 03, 02) |
| `MediaFile` | `resources` (types from list 38, so `frontCoverUrl()` looks for 04, 06) |
| `PublicationDate`, `YearFirstPublished` | `publishingDates` with roles 01 and 11 |
| `WorkIdentifier` | a related work with relation 01 (`workIdentifiers()`) |
| `ISBN`, `EAN13`, `UPC`... | `identifiers` with types 02, 03, 04... |
| `SupplyDetail` | `supplyDetails`; `AvailabilityCode` (list 54) when `ProductAvailability` is missing |

Codes keep the list they came from, so a 2.1 product form is `list: 7` (labelled from list 150,
which kept the 2.1 codes). EDItEUR no longer publishes the 2.1-only lists (33, 38, 54...), so
those codes have no labels.

## Requirements

PHP 8.1 to 8.4 with `ext-dom`, `ext-libxml` and `ext-xmlreader` (all enabled in standard builds).
`ext-zlib` is needed for `.gz` files.

## Development

```bash
composer install
composer test      # PHPUnit
composer phpstan   # PHPStan, level max with strict rules
composer cs        # PHP-CS-Fixer, dry run
```

Test fixtures are hand-written and describe fictional books; please do not add real publisher
feeds to the repository.

## About

Maintained by [ISBNLab, the book metadata API](https://www.isbnlab.com/). Released under the
[MIT License](LICENSE).

ONIX is a standard of [EDItEUR](https://www.editeur.org/). ONIX element names, code values and the
code lists in `data/codelists` are used under [EDItEUR's licence](https://doi.org/10.4400/nwgj); the
code lists are EDItEUR's copyright and are not covered by the MIT License. This project is not
affiliated with or endorsed by EDItEUR. See [NOTICE](NOTICE).
