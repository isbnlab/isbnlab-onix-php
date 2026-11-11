<?php

declare(strict_types=1);

namespace ISBNLab\Onix\Internal;

use ISBNLab\Onix\Model\Header;
use ISBNLab\Onix\Model\Identifier;
use ISBNLab\Onix\OnixVersion;

/**
 * Maps an ONIX 3.0 or 2.1 Header element.
 *
 * @internal
 */
final class HeaderMapper extends Mapper
{
    public function map(Element $header, ?OnixVersion $version): Header
    {
        $sender = $header->first('Sender');
        if (null !== $sender) {
            // ONIX 3.0
            $addressees = [];
            foreach ($header->children('Addressee') as $addressee) {
                $name = $addressee->text('AddresseeName');
                if (null !== $name) {
                    $addressees[] = $name;
                }
            }

            return new Header(
                version: $version ?? OnixVersion::V30,
                senderName: $sender->text('SenderName'),
                contactName: $sender->text('ContactName'),
                emailAddress: $sender->text('EmailAddress'),
                senderIdentifiers: $this->identifiers($sender, 'SenderIdentifier', 'SenderIDType', 44),
                addresseeNames: $addressees,
                messageNumber: $header->text('MessageNumber'),
                sentDateTime: $header->text('SentDateTime'),
                messageNotes: $header->texts('MessageNote'),
                defaultLanguageOfText: $header->text('DefaultLanguageOfText'),
                defaultCurrencyCode: $header->text('DefaultCurrencyCode'),
                defaultPriceType: $this->code(58, $header->text('DefaultPriceType')),
            );
        }

        // ONIX 2.1
        $identifiers = $this->identifiers($header, 'SenderIdentifier', 'SenderIDType', 44);
        foreach ([['FromSAN', '07'], ['FromEANNumber', '06']] as [$name, $type]) {
            $value = $header->text($name);
            if (null !== $value) {
                $identifiers[] = new Identifier($this->code(44, $type), $value);
            }
        }
        $addressees = $header->texts('ToCompany');

        return new Header(
            version: $version,
            senderName: $header->text('FromCompany'),
            contactName: $header->text('FromPerson'),
            emailAddress: $header->text('FromEmail'),
            senderIdentifiers: $identifiers,
            addresseeNames: $addressees,
            messageNumber: $header->text('MessageNumber'),
            sentDateTime: $header->text('SentDate'),
            messageNotes: $header->texts('MessageNote'),
            defaultLanguageOfText: $header->text('DefaultLanguageOfText'),
            defaultCurrencyCode: $header->text('DefaultCurrencyCode'),
            defaultPriceType: $this->code(58, $header->text('DefaultPriceTypeCode')),
        );
    }
}
