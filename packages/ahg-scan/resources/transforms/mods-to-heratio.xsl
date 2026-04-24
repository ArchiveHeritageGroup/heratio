<?xml version="1.0" encoding="UTF-8"?>
<!--
  mods-to-heratio.xsl

  Transforms a MODS 3.x <mods> element (or <modsCollection><mods>) into a
  heratioScan sidecar envelope with sector=library + libraryProfile.

  Common MODS elements mapped:
    titleInfo/title + subTitle → title
    name/namePart + role/roleTerm → creators
    typeOfResource                → (logged as profile hint)
    originInfo/publisher          → publisher
    originInfo/place/placeTerm    → placeOfPublication
    originInfo/dateIssued         → yearOfPublication + date
    originInfo/edition            → edition
    physicalDescription/extent    → pagination / dimensions
    physicalDescription/form      → materialType
    subject/topic | geographic | name → subjects
    genre                         → genres
    identifier[type=isbn/issn/doi/lccn/oclc] → individual fields
    abstract                      → scopeAndContent
    note                          → archivalHistory (appended)
    accessCondition               → accessConditions
    relatedItem[type=series]/titleInfo/title → seriesTitle
    location/physicalLocation     → physicalLocation
    location/shelfLocator         → callNumber

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
-->
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:mods="http://www.loc.gov/mods/v3"
    xmlns="https://heratio.io/scan/v1"
    exclude-result-prefixes="mods">

  <xsl:output method="xml" indent="yes" encoding="UTF-8"/>

  <xsl:template match="/">
    <heratioScan>
      <sector>library</sector>
      <standard>mods</standard>
      <xsl:apply-templates select="(//*[local-name()='mods'])[1]"/>
    </heratioScan>
  </xsl:template>

  <xsl:template match="*[local-name()='mods']">
    <!-- Identifier: prefer ISBN > ISSN > DOI > recordInfo/recordIdentifier -->
    <xsl:variable name="isbn" select="normalize-space(*[local-name()='identifier' and @type='isbn'][1])"/>
    <xsl:variable name="issn" select="normalize-space(*[local-name()='identifier' and @type='issn'][1])"/>
    <xsl:variable name="doi"  select="normalize-space(*[local-name()='identifier' and @type='doi'][1])"/>
    <xsl:variable name="lccn" select="normalize-space(*[local-name()='identifier' and @type='lccn'][1])"/>
    <xsl:variable name="oclc" select="normalize-space(*[local-name()='identifier' and @type='oclc'][1])"/>
    <xsl:variable name="recId" select="normalize-space(*[local-name()='recordInfo']/*[local-name()='recordIdentifier'][1])"/>

    <xsl:choose>
      <xsl:when test="$isbn != ''"><identifier><xsl:value-of select="$isbn"/></identifier></xsl:when>
      <xsl:when test="$issn != ''"><identifier><xsl:value-of select="$issn"/></identifier></xsl:when>
      <xsl:when test="$doi  != ''"><identifier><xsl:value-of select="$doi"/></identifier></xsl:when>
      <xsl:when test="$recId != ''"><identifier><xsl:value-of select="$recId"/></identifier></xsl:when>
    </xsl:choose>

    <!-- Title (main titleInfo only — skip alternatives for v1) -->
    <xsl:variable name="mainTitle" select="*[local-name()='titleInfo' and not(@type)][1]"/>
    <xsl:variable name="anyTitle"  select="(*[local-name()='titleInfo'])[1]"/>
    <xsl:variable name="titleNode" select="($mainTitle | $anyTitle)[1]"/>
    <xsl:if test="$titleNode">
      <xsl:variable name="t"  select="normalize-space($titleNode/*[local-name()='title'][1])"/>
      <xsl:variable name="sub" select="normalize-space($titleNode/*[local-name()='subTitle'][1])"/>
      <title>
        <xsl:choose>
          <xsl:when test="$sub != ''"><xsl:value-of select="concat($t, ': ', $sub)"/></xsl:when>
          <xsl:otherwise><xsl:value-of select="$t"/></xsl:otherwise>
        </xsl:choose>
      </title>
    </xsl:if>

    <levelOfDescription>item</levelOfDescription>

    <!-- Dates -->
    <xsl:variable name="dateIssued" select="(*[local-name()='originInfo']/*[local-name()='dateIssued'])[1]"/>
    <xsl:variable name="dateCreated" select="(*[local-name()='originInfo']/*[local-name()='dateCreated'])[1]"/>
    <xsl:if test="$dateIssued or $dateCreated">
      <dates>
        <xsl:if test="$dateIssued">
          <date type="publication">
            <xsl:attribute name="start"><xsl:value-of select="normalize-space($dateIssued)"/></xsl:attribute>
          </date>
        </xsl:if>
        <xsl:if test="$dateCreated">
          <date type="creation">
            <xsl:attribute name="start"><xsl:value-of select="normalize-space($dateCreated)"/></xsl:attribute>
          </date>
        </xsl:if>
      </dates>
    </xsl:if>

    <!-- Abstract → envelope-level scopeAndContent; access conditions -->
    <xsl:variable name="abs" select="normalize-space(*[local-name()='abstract'][1])"/>
    <xsl:variable name="ac"  select="normalize-space(*[local-name()='accessCondition'][1])"/>
    <xsl:if test="$abs != ''"><scopeAndContent><xsl:value-of select="$abs"/></scopeAndContent></xsl:if>
    <xsl:if test="$ac != ''"><accessConditions><xsl:value-of select="$ac"/></accessConditions></xsl:if>

    <libraryProfile>
      <xsl:if test="$isbn != ''"><isbn><xsl:value-of select="$isbn"/></isbn></xsl:if>
      <xsl:if test="$issn != ''"><issn><xsl:value-of select="$issn"/></issn></xsl:if>
      <xsl:if test="$doi != ''"><doi><xsl:value-of select="$doi"/></doi></xsl:if>
      <xsl:if test="$lccn != ''"><lccn><xsl:value-of select="$lccn"/></lccn></xsl:if>
      <xsl:if test="$oclc != ''"><oclc><xsl:value-of select="$oclc"/></oclc></xsl:if>

      <!-- Publisher / place / date / edition -->
      <xsl:variable name="orig" select="(*[local-name()='originInfo'])[1]"/>
      <xsl:if test="$orig">
        <xsl:variable name="pub" select="normalize-space($orig/*[local-name()='publisher'][1])"/>
        <xsl:variable name="place" select="normalize-space($orig/*[local-name()='place']/*[local-name()='placeTerm'][not(@type) or @type='text'][1])"/>
        <xsl:variable name="ed" select="normalize-space($orig/*[local-name()='edition'][1])"/>
        <xsl:if test="$pub != ''"><publisher><xsl:value-of select="$pub"/></publisher></xsl:if>
        <xsl:if test="$place != ''"><placeOfPublication><xsl:value-of select="$place"/></placeOfPublication></xsl:if>
        <xsl:if test="$dateIssued"><yearOfPublication><xsl:value-of select="normalize-space($dateIssued)"/></yearOfPublication></xsl:if>
        <xsl:if test="$ed != ''"><edition><xsl:value-of select="$ed"/></edition></xsl:if>
      </xsl:if>

      <!-- Extent / dimensions / form -->
      <xsl:variable name="phys" select="(*[local-name()='physicalDescription'])[1]"/>
      <xsl:if test="$phys">
        <xsl:variable name="extent" select="normalize-space($phys/*[local-name()='extent'][1])"/>
        <xsl:variable name="form" select="normalize-space($phys/*[local-name()='form'][1])"/>
        <xsl:if test="$extent != ''"><pagination><xsl:value-of select="$extent"/></pagination></xsl:if>
        <xsl:if test="$form != ''"><materialType><xsl:value-of select="$form"/></materialType></xsl:if>
      </xsl:if>

      <!-- Language -->
      <xsl:variable name="lang" select="normalize-space(*[local-name()='language']/*[local-name()='languageTerm'][@type='code' or not(@type)][1])"/>
      <xsl:if test="$lang != ''"><language><xsl:value-of select="$lang"/></language></xsl:if>

      <!-- Series from relatedItem[type='series']/titleInfo/title -->
      <xsl:variable name="series" select="normalize-space(*[local-name()='relatedItem' and @type='series']/*[local-name()='titleInfo']/*[local-name()='title'][1])"/>
      <xsl:if test="$series != ''"><seriesTitle><xsl:value-of select="$series"/></seriesTitle></xsl:if>

      <!-- Shelf location / call number -->
      <xsl:variable name="call" select="normalize-space(*[local-name()='location']/*[local-name()='shelfLocator'][1])"/>
      <xsl:if test="$call != ''"><callNumber><xsl:value-of select="$call"/></callNumber></xsl:if>

      <!-- Creators (any name with namePart) -->
      <xsl:variable name="names" select="*[local-name()='name'][*[local-name()='namePart']]"/>
      <xsl:if test="$names">
        <creators>
          <xsl:for-each select="$names">
            <xsl:variable name="np" select="normalize-space(*[local-name()='namePart'][not(@type) or @type='family' or @type='given'][1])"/>
            <xsl:variable name="npAll" select="normalize-space(*[local-name()='namePart'])"/>
            <xsl:variable name="role" select="normalize-space(*[local-name()='role']/*[local-name()='roleTerm'][@type='text'][1])"/>
            <xsl:variable name="authority" select="normalize-space(@authority)"/>
            <xsl:variable name="valueURI" select="normalize-space(@valueURI)"/>
            <xsl:if test="$np != '' or $npAll != ''">
              <creator>
                <xsl:if test="$authority != ''"><xsl:attribute name="vocab"><xsl:value-of select="$authority"/></xsl:attribute></xsl:if>
                <xsl:if test="$valueURI != ''"><xsl:attribute name="uri"><xsl:value-of select="$valueURI"/></xsl:attribute></xsl:if>
                <xsl:if test="$role != ''"><xsl:attribute name="role"><xsl:value-of select="$role"/></xsl:attribute></xsl:if>
                <xsl:choose>
                  <xsl:when test="$np != ''"><xsl:value-of select="$np"/></xsl:when>
                  <xsl:otherwise><xsl:value-of select="$npAll"/></xsl:otherwise>
                </xsl:choose>
              </creator>
            </xsl:if>
          </xsl:for-each>
        </creators>
      </xsl:if>

      <!-- Subjects: topic / geographic / temporal / titleInfo/title -->
      <xsl:variable name="subjects" select="*[local-name()='subject']"/>
      <xsl:if test="$subjects">
        <subjects>
          <xsl:for-each select="$subjects">
            <xsl:variable name="authority" select="normalize-space(@authority)"/>
            <xsl:variable name="valueURI" select="normalize-space(@valueURI)"/>
            <xsl:for-each select="*[local-name()='topic' or local-name()='geographic' or local-name()='temporal' or local-name()='genre']">
              <xsl:variable name="h" select="normalize-space(.)"/>
              <xsl:if test="$h != ''">
                <subject>
                  <xsl:if test="$authority != ''"><xsl:attribute name="vocab"><xsl:value-of select="$authority"/></xsl:attribute></xsl:if>
                  <xsl:if test="$valueURI != ''"><xsl:attribute name="uri"><xsl:value-of select="$valueURI"/></xsl:attribute></xsl:if>
                  <xsl:value-of select="$h"/>
                </subject>
              </xsl:if>
            </xsl:for-each>
          </xsl:for-each>
        </subjects>
      </xsl:if>

      <!-- Genres -->
      <xsl:variable name="genres" select="*[local-name()='genre']"/>
      <xsl:if test="$genres">
        <genres>
          <xsl:for-each select="$genres">
            <genre>
              <xsl:if test="@authority != ''"><xsl:attribute name="vocab"><xsl:value-of select="@authority"/></xsl:attribute></xsl:if>
              <xsl:value-of select="normalize-space(.)"/>
            </genre>
          </xsl:for-each>
        </genres>
      </xsl:if>
    </libraryProfile>

    <merge>add-sequence</merge>
  </xsl:template>
</xsl:stylesheet>
