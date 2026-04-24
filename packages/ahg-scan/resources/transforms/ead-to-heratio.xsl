<?xml version="1.0" encoding="UTF-8"?>
<!--
  ead-to-heratio.xsl

  Transforms an EAD2002 <c> component into a heratioScan sidecar envelope.
  Handles the most common case: one EAD component per scan. For whole-finding-
  aid imports use the ingest wizard's batch EAD loader instead.

  Input:  EAD2002 fragment rooted at <c> (or <ead><archdesc><c>).
  Output: heratioScan envelope with archiveProfile populated.

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
-->
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:ead="urn:isbn:1-931666-22-9"
    xmlns="https://heratio.io/scan/v1"
    exclude-result-prefixes="ead">

  <xsl:output method="xml" indent="yes" encoding="UTF-8"/>

  <xsl:template match="/">
    <heratioScan>
      <sector>archive</sector>
      <standard>ead</standard>
      <xsl:apply-templates select="//*[local-name()='c' or local-name()='archdesc'][1]"/>
    </heratioScan>
  </xsl:template>

  <xsl:template match="*[local-name()='c' or local-name()='archdesc']">
    <xsl:variable name="did" select="*[local-name()='did']"/>

    <xsl:if test="$did/*[local-name()='unitid']">
      <identifier><xsl:value-of select="normalize-space($did/*[local-name()='unitid'][1])"/></identifier>
    </xsl:if>

    <xsl:if test="$did/*[local-name()='unittitle']">
      <title><xsl:value-of select="normalize-space($did/*[local-name()='unittitle'][1])"/></title>
    </xsl:if>

    <xsl:if test="@level">
      <levelOfDescription><xsl:value-of select="@level"/></levelOfDescription>
    </xsl:if>

    <xsl:if test="$did/*[local-name()='unitdate']">
      <dates>
        <xsl:for-each select="$did/*[local-name()='unitdate']">
          <date type="creation">
            <xsl:if test="@normal">
              <xsl:attribute name="start">
                <xsl:value-of select="substring-before(concat(@normal, '/'), '/')"/>
              </xsl:attribute>
              <xsl:if test="contains(@normal, '/')">
                <xsl:attribute name="end">
                  <xsl:value-of select="substring-after(@normal, '/')"/>
                </xsl:attribute>
              </xsl:if>
            </xsl:if>
          </date>
        </xsl:for-each>
      </dates>
    </xsl:if>

    <archiveProfile>
      <xsl:if test="*[local-name()='scopecontent']">
        <scopeAndContent>
          <xsl:value-of select="normalize-space(*[local-name()='scopecontent'])"/>
        </scopeAndContent>
      </xsl:if>
      <xsl:if test="$did/*[local-name()='physdesc']">
        <extentAndMedium>
          <xsl:value-of select="normalize-space($did/*[local-name()='physdesc'][1])"/>
        </extentAndMedium>
      </xsl:if>
      <xsl:if test="*[local-name()='custodhist']">
        <archivalHistory>
          <xsl:value-of select="normalize-space(*[local-name()='custodhist'])"/>
        </archivalHistory>
      </xsl:if>
      <xsl:if test="*[local-name()='acqinfo']">
        <acquisition>
          <xsl:value-of select="normalize-space(*[local-name()='acqinfo'])"/>
        </acquisition>
      </xsl:if>
      <xsl:if test="*[local-name()='arrangement']">
        <arrangement>
          <xsl:value-of select="normalize-space(*[local-name()='arrangement'])"/>
        </arrangement>
      </xsl:if>
      <xsl:if test="$did/*[local-name()='origination']">
        <creators>
          <xsl:for-each select="$did/*[local-name()='origination']/*[local-name()='persname' or local-name()='corpname' or local-name()='famname']">
            <creator>
              <xsl:if test="@authfilenumber"><xsl:attribute name="uri"><xsl:value-of select="@authfilenumber"/></xsl:attribute></xsl:if>
              <xsl:value-of select="normalize-space(.)"/>
            </creator>
          </xsl:for-each>
        </creators>
      </xsl:if>
      <xsl:if test="*[local-name()='controlaccess']/*[local-name()='subject']">
        <subjects>
          <xsl:for-each select="*[local-name()='controlaccess']/*[local-name()='subject']">
            <subject>
              <xsl:if test="@source"><xsl:attribute name="vocab"><xsl:value-of select="@source"/></xsl:attribute></xsl:if>
              <xsl:if test="@authfilenumber"><xsl:attribute name="uri"><xsl:value-of select="@authfilenumber"/></xsl:attribute></xsl:if>
              <xsl:value-of select="normalize-space(.)"/>
            </subject>
          </xsl:for-each>
        </subjects>
      </xsl:if>
      <xsl:if test="*[local-name()='controlaccess']/*[local-name()='geogname']">
        <places>
          <xsl:for-each select="*[local-name()='controlaccess']/*[local-name()='geogname']">
            <place>
              <xsl:if test="@source"><xsl:attribute name="vocab"><xsl:value-of select="@source"/></xsl:attribute></xsl:if>
              <xsl:value-of select="normalize-space(.)"/>
            </place>
          </xsl:for-each>
        </places>
      </xsl:if>
      <xsl:if test="$did/*[local-name()='physloc']">
        <physicalLocation>
          <xsl:value-of select="normalize-space($did/*[local-name()='physloc'])"/>
        </physicalLocation>
      </xsl:if>
    </archiveProfile>

    <xsl:if test="*[local-name()='userestrict']">
      <accessConditions>
        <xsl:value-of select="normalize-space(*[local-name()='userestrict'])"/>
      </accessConditions>
    </xsl:if>

    <merge>add-sequence</merge>
  </xsl:template>
</xsl:stylesheet>
