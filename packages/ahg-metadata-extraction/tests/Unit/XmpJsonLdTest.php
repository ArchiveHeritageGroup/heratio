<?php

/**
 * XmpJsonLdTest - heratio#1557: an XMP packet goes to JSON-LD and back without
 * losing namespaces, array kinds, language alternatives, structures or
 * qualifiers, and the privacy gate can strip GPS at any depth.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * License: AGPL-3.0-or-later
 */

namespace AhgMetadataExtraction\Tests\Unit;

use AhgMetadataExtraction\Xmp\XmpJsonLd;
use PHPUnit\Framework\TestCase;

class XmpJsonLdTest extends TestCase
{
    private const ID = 'urn:heratio:digital-object:42';

    /** Every form the issue lists: simple, Bag, Seq, Alt, languages, structure, qualifier. */
    public const PACKET = <<<'XML'
<?xpacket begin='﻿' id='W5M0MpCehiHzreSzNTczkc9d'?>
<x:xmpmeta xmlns:x="adobe:ns:meta/">
 <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:about=""
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:xmp="http://ns.adobe.com/xap/1.0/"
    xmlns:xmpRights="http://ns.adobe.com/xap/1.0/rights/"
    xmlns:tiff="http://ns.adobe.com/tiff/1.0/"
    xmlns:exif="http://ns.adobe.com/exif/1.0/"
    xmlns:Iptc4xmpCore="http://iptc.org/std/Iptc4xmpCore/1.0/xmlns/"
    xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/"
    xmlns:stEvt="http://ns.adobe.com/xap/1.0/sType/ResourceEvent#"
    xmlns:xmpMM="http://ns.adobe.com/xap/1.0/mm/"
    xmlns:ex="http://example.org/role/"
    tiff:Orientation="1"
    exif:GPSLatitude="33,55.2S">
   <dc:format>image/jpeg</dc:format>
   <dc:title><rdf:Alt>
     <rdf:li xml:lang="x-default">Wild mushroom</rdf:li>
     <rdf:li xml:lang="en">Wild mushroom</rdf:li>
     <rdf:li xml:lang="af">Wilde sampioen</rdf:li>
   </rdf:Alt></dc:title>
   <dc:creator><rdf:Seq>
     <rdf:li>First Photographer</rdf:li>
     <rdf:li><rdf:Description><rdf:value>Second Photographer</rdf:value><ex:role>assistant</ex:role></rdf:Description></rdf:li>
   </rdf:Seq></dc:creator>
   <dc:subject><rdf:Bag><rdf:li>fungi</rdf:li><rdf:li>macro</rdf:li></rdf:Bag></dc:subject>
   <xmpRights:WebStatement rdf:resource="https://example.org/licence"/>
   <Iptc4xmpCore:CreatorContactInfo rdf:parseType="Resource">
     <Iptc4xmpCore:CiAdrCity>Cape Town</Iptc4xmpCore:CiAdrCity>
     <Iptc4xmpCore:CiEmailWork>photo@example.org</Iptc4xmpCore:CiEmailWork>
   </Iptc4xmpCore:CreatorContactInfo>
   <Iptc4xmpExt:LocationShown><rdf:Bag><rdf:li>
     <rdf:Description Iptc4xmpExt:City="Cape Town" exif:GPSLongitude="18,25.4E"/>
   </rdf:li></rdf:Bag></Iptc4xmpExt:LocationShown>
   <xmpMM:History><rdf:Seq><rdf:li stEvt:action="created" stEvt:when="2021-05-01T10:00:00Z"/></rdf:Seq></xmpMM:History>
  </rdf:Description>
 </rdf:RDF>
</x:xmpmeta>
<?xpacket end='w'?>
XML;

    private function doc(): array
    {
        $doc = XmpJsonLd::fromRdfXml(self::PACKET, self::ID);
        $this->assertNotNull($doc);

        return $doc;
    }

    public function test_the_packet_becomes_json_ld_with_every_xmp_form_kept(): void
    {
        $doc = $this->doc();

        $this->assertSame(self::ID, $doc['@id']);
        $this->assertSame('http://purl.org/dc/elements/1.1/', $doc['@context']['dc']);
        $this->assertSame(XmpJsonLd::RDF, $doc['@context']['rdf']);

        $this->assertSame('image/jpeg', $doc['dc:format'], 'simple element');
        $this->assertSame('1', $doc['tiff:Orientation'], 'simple attribute shorthand');
        $this->assertSame(['@id' => 'https://example.org/licence'], $doc['xmpRights:WebStatement'], 'URI');

        $this->assertSame(['@set' => ['fungi', 'macro']], $doc['dc:subject'], 'Bag');

        $this->assertSame('First Photographer', $doc['dc:creator']['@list'][0], 'Seq keeps order');
        $this->assertSame(['ex:role' => 'assistant', 'rdf:value' => 'Second Photographer'], $doc['dc:creator']['@list'][1], 'qualifier');

        $this->assertSame('rdf:Alt', $doc['dc:title']['@type'], 'Alt');
        $this->assertSame(['@value' => 'Wild mushroom', '@language' => 'x-default'], $doc['dc:title']['rdf:_1'], 'x-default first');
        $this->assertSame(['@value' => 'Wilde sampioen', '@language' => 'af'], $doc['dc:title']['rdf:_3'], 'language alternative');

        $this->assertSame('Cape Town', $doc['Iptc4xmpCore:CreatorContactInfo']['Iptc4xmpCore:CiAdrCity'], 'structure (parseType Resource)');
        $this->assertSame('Cape Town', $doc['Iptc4xmpExt:LocationShown']['@set'][0]['Iptc4xmpExt:City'], 'structure in a Bag (rdf:Description attributes)');
        $this->assertSame('created', $doc['xmpMM:History']['@list'][0]['stEvt:action'], 'structure as empty element with attributes');
    }

    public function test_the_round_trip_to_rdf_xml_and_back_is_lossless(): void
    {
        $doc = $this->doc();
        $again = XmpJsonLd::fromRdfXml(XmpJsonLd::toRdfXml($doc), self::ID);

        $this->assertSame($doc, $again);
    }

    public function test_gps_is_stripped_at_every_depth(): void
    {
        $doc = $this->doc();
        $this->assertArrayHasKey('exif:GPSLatitude', $doc);

        $gated = XmpJsonLd::stripGps($doc);
        $this->assertArrayNotHasKey('exif:GPSLatitude', $gated);
        $this->assertArrayNotHasKey('exif:GPSLongitude', $gated['Iptc4xmpExt:LocationShown']['@set'][0], 'GPS inside a structure');
        $this->assertSame('Cape Town', $gated['Iptc4xmpExt:LocationShown']['@set'][0]['Iptc4xmpExt:City'], 'the rest stays');
        $this->assertSame('image/jpeg', $gated['dc:format']);

        $onlyGps = ['@context' => ['exif' => 'http://ns.adobe.com/exif/1.0/'], '@id' => self::ID, 'exif:GPSLatitude' => '1'];
        $this->assertNull(XmpJsonLd::stripGps($onlyGps), 'nothing left means no block');
    }

    public function test_several_files_combine_into_a_graph(): void
    {
        $a = XmpJsonLd::fromRdfXml(self::PACKET, 'urn:heratio:digital-object:1');
        $b = XmpJsonLd::fromRdfXml(self::PACKET, 'urn:heratio:digital-object:2');
        $both = XmpJsonLd::combine([$a, $b]);

        $this->assertCount(2, $both['@graph']);
        $this->assertSame('urn:heratio:digital-object:2', $both['@graph'][1]['@id']);
        $this->assertArrayNotHasKey('@context', $both['@graph'][0]);
        $this->assertSame($a, XmpJsonLd::combine([$a]));
    }

    public function test_a_packet_with_no_properties_or_bad_xml_gives_null(): void
    {
        $this->assertNull(XmpJsonLd::fromRdfXml('<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="'.XmpJsonLd::RDF.'"/></x:xmpmeta>', self::ID));
        $this->assertNull(XmpJsonLd::fromRdfXml('not xml', self::ID));
    }
}
