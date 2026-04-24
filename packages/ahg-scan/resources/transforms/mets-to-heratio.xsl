<?xml version="1.0" encoding="UTF-8"?>
<!--
  mets-to-heratio.xsl

  Transforms a METS container (http://www.loc.gov/METS/) into a heratioScan
  sidecar envelope. Handles the common "simple METS" profile used by
  Archivematica AIPs and DSpace AIPs: one dmdSec with descriptive metadata
  inside mdWrap/xmlData, optionally an amdSec with PREMIS / MIX.

  Supported mdWrap MDTYPEs:
    DC    → Dublin Core 1.1 → envelope fields + archiveProfile
    MODS  → selected MODS 3.x fields mapped inline (title, creator, date,
            publisher, subject) — this XSLT doesn't do full MODS crosswalk;
            use mods-to-heratio.xsl for rich MODS records.

  Unsupported dmdSec types (MARC, EAD, DDI, TEI-P5, VRA, DarwinCore): the
  transform still emits a heratioScan shell with the mets:OBJID as
  identifier so operators see the file passed detection — they should
  export the dmdSec as a standalone XML and feed it directly.

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
-->
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:mets="http://www.loc.gov/METS/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:dcterms="http://purl.org/dc/terms/"
    xmlns:mods="http://www.loc.gov/mods/v3"
    xmlns="https://heratio.io/scan/v1"
    exclude-result-prefixes="mets dc dcterms mods">

  <xsl:output method="xml" indent="yes" encoding="UTF-8"/>

  <xsl:template match="/">
    <heratioScan>
      <xsl:apply-templates select="(//*[local-name()='mets'])[1]"/>
    </heratioScan>
  </xsl:template>

  <xsl:template match="*[local-name()='mets']">
    <!-- Pick the first dmdSec with DC or MODS content; fall back to the
         first dmdSec regardless of MDTYPE so we still emit an identifier. -->
    <xsl:variable name="dmdDc"
                  select="(//*[local-name()='dmdSec']
                           [.//*[local-name()='mdWrap'][@MDTYPE='DC']])[1]"/>
    <xsl:variable name="dmdMods"
                  select="(//*[local-name()='dmdSec']
                           [.//*[local-name()='mdWrap'][@MDTYPE='MODS']])[1]"/>
    <xsl:variable name="dmdAny"
                  select="(//*[local-name()='dmdSec'])[1]"/>

    <!-- Identifier: METS OBJID > DC identifier > MODS recordIdentifier > dmdSec/@ID -->
    <xsl:variable name="objid" select="normalize-space(@OBJID)"/>
    <xsl:variable name="dcId" select="normalize-space($dmdDc//*[local-name()='identifier'][1])"/>
    <xsl:variable name="modsId" select="normalize-space($dmdMods//*[local-name()='identifier' and (not(@type) or @type='isbn' or @type='doi' or @type='local')][1])"/>
    <xsl:choose>
      <xsl:when test="$dcId != ''"><identifier><xsl:value-of select="$dcId"/></identifier></xsl:when>
      <xsl:when test="$modsId != ''"><identifier><xsl:value-of select="$modsId"/></identifier></xsl:when>
      <xsl:when test="$objid != ''"><identifier><xsl:value-of select="$objid"/></identifier></xsl:when>
    </xsl:choose>

    <!-- Sector + standard: infer from dmdSec MDTYPE. DC is sector-agnostic; default archive. -->
    <xsl:variable name="mdtype" select="normalize-space(($dmdDc | $dmdMods | $dmdAny)[1]//*[local-name()='mdWrap']/@MDTYPE)"/>
    <sector>archive</sector>
    <standard>
      <xsl:choose>
        <xsl:when test="$mdtype='DC'">dc</xsl:when>
        <xsl:when test="$mdtype='MODS'">mods</xsl:when>
        <xsl:otherwise>dc</xsl:otherwise>
      </xsl:choose>
    </standard>

    <!-- Title: DC title > MODS title -->
    <xsl:variable name="dcTitle" select="normalize-space($dmdDc//*[local-name()='title'][1])"/>
    <xsl:variable name="modsTitle" select="normalize-space($dmdMods//*[local-name()='titleInfo']/*[local-name()='title'][1])"/>
    <xsl:choose>
      <xsl:when test="$dcTitle != ''"><title><xsl:value-of select="$dcTitle"/></title></xsl:when>
      <xsl:when test="$modsTitle != ''"><title><xsl:value-of select="$modsTitle"/></title></xsl:when>
    </xsl:choose>

    <levelOfDescription>item</levelOfDescription>

    <!-- Dates: DC date > MODS dateIssued/dateCreated -->
    <xsl:variable name="dcDate" select="normalize-space($dmdDc//*[local-name()='date'][1])"/>
    <xsl:variable name="modsDateIssued" select="normalize-space($dmdMods//*[local-name()='dateIssued'][1])"/>
    <xsl:variable name="modsDateCreated" select="normalize-space($dmdMods//*[local-name()='dateCreated'][1])"/>
    <xsl:if test="$dcDate != '' or $modsDateIssued != '' or $modsDateCreated != ''">
      <dates>
        <xsl:if test="$dcDate != '' or $modsDateIssued != ''">
          <date type="publication">
            <xsl:variable name="d">
              <xsl:choose>
                <xsl:when test="$dcDate != ''"><xsl:value-of select="$dcDate"/></xsl:when>
                <xsl:otherwise><xsl:value-of select="$modsDateIssued"/></xsl:otherwise>
              </xsl:choose>
            </xsl:variable>
            <xsl:attribute name="start"><xsl:value-of select="$d"/></xsl:attribute>
          </date>
        </xsl:if>
        <xsl:if test="$modsDateCreated != ''">
          <date type="creation" start="{$modsDateCreated}"/>
        </xsl:if>
      </dates>
    </xsl:if>

    <!-- DC description → envelope-level scopeAndContent -->
    <xsl:variable name="dcDesc" select="normalize-space($dmdDc//*[local-name()='description'][1])"/>
    <xsl:variable name="modsAbs" select="normalize-space($dmdMods//*[local-name()='abstract'][1])"/>
    <xsl:choose>
      <xsl:when test="$dcDesc != ''"><scopeAndContent><xsl:value-of select="$dcDesc"/></scopeAndContent></xsl:when>
      <xsl:when test="$modsAbs != ''"><scopeAndContent><xsl:value-of select="$modsAbs"/></scopeAndContent></xsl:when>
    </xsl:choose>

    <!-- Access conditions: DC rights > MODS accessCondition -->
    <xsl:variable name="dcRights" select="normalize-space($dmdDc//*[local-name()='rights'][1])"/>
    <xsl:variable name="modsAc" select="normalize-space($dmdMods//*[local-name()='accessCondition'][1])"/>
    <xsl:choose>
      <xsl:when test="$dcRights != ''"><accessConditions><xsl:value-of select="$dcRights"/></accessConditions></xsl:when>
      <xsl:when test="$modsAc != ''"><accessConditions><xsl:value-of select="$modsAc"/></accessConditions></xsl:when>
    </xsl:choose>

    <archiveProfile>
      <!-- Extent from DC format (Dublin Core uses format for extent too) -->
      <xsl:variable name="dcFmt" select="normalize-space($dmdDc//*[local-name()='format'][1])"/>
      <xsl:variable name="modsExtent" select="normalize-space($dmdMods//*[local-name()='physicalDescription']/*[local-name()='extent'][1])"/>
      <xsl:choose>
        <xsl:when test="$dcFmt != ''"><extentAndMedium><xsl:value-of select="$dcFmt"/></extentAndMedium></xsl:when>
        <xsl:when test="$modsExtent != ''"><extentAndMedium><xsl:value-of select="$modsExtent"/></extentAndMedium></xsl:when>
      </xsl:choose>

      <!-- Acquisition from DC source -->
      <xsl:variable name="dcSource" select="normalize-space($dmdDc//*[local-name()='source'][1])"/>
      <xsl:if test="$dcSource != ''"><acquisition><xsl:value-of select="$dcSource"/></acquisition></xsl:if>

      <!-- Scope inside profile too (the wizard + IO field both live here) -->
      <xsl:if test="$dcDesc != '' or $modsAbs != ''">
        <scopeAndContent>
          <xsl:choose>
            <xsl:when test="$dcDesc != ''"><xsl:value-of select="$dcDesc"/></xsl:when>
            <xsl:otherwise><xsl:value-of select="$modsAbs"/></xsl:otherwise>
          </xsl:choose>
        </scopeAndContent>
      </xsl:if>

      <!-- Creators: DC creator + DC contributor (multiple) + MODS name -->
      <xsl:variable name="dcCreators" select="$dmdDc//*[local-name()='creator' or local-name()='contributor']"/>
      <xsl:variable name="modsNames" select="$dmdMods//*[local-name()='name']"/>
      <xsl:if test="$dcCreators or $modsNames">
        <creators>
          <xsl:for-each select="$dcCreators">
            <xsl:if test="normalize-space(.) != ''">
              <creator><xsl:value-of select="normalize-space(.)"/></creator>
            </xsl:if>
          </xsl:for-each>
          <xsl:for-each select="$modsNames">
            <xsl:variable name="n" select="normalize-space(*[local-name()='namePart'][1])"/>
            <xsl:variable name="np" select="normalize-space(*[local-name()='namePart'])"/>
            <xsl:variable name="name" select="concat($n, substring($np, string-length($n)+1))"/>
            <xsl:variable name="role" select="normalize-space(*[local-name()='role']/*[local-name()='roleTerm'][1])"/>
            <xsl:if test="$n != '' or $np != ''">
              <creator>
                <xsl:if test="normalize-space(@valueURI) != ''">
                  <xsl:attribute name="uri"><xsl:value-of select="normalize-space(@valueURI)"/></xsl:attribute>
                </xsl:if>
                <xsl:if test="normalize-space(@authority) != ''">
                  <xsl:attribute name="vocab"><xsl:value-of select="normalize-space(@authority)"/></xsl:attribute>
                </xsl:if>
                <xsl:if test="$role != ''">
                  <xsl:attribute name="role"><xsl:value-of select="$role"/></xsl:attribute>
                </xsl:if>
                <xsl:choose>
                  <xsl:when test="$n != ''"><xsl:value-of select="$n"/></xsl:when>
                  <xsl:otherwise><xsl:value-of select="$np"/></xsl:otherwise>
                </xsl:choose>
              </creator>
            </xsl:if>
          </xsl:for-each>
        </creators>
      </xsl:if>

      <!-- Subjects: DC subject + MODS subject/topic -->
      <xsl:variable name="dcSubjects" select="$dmdDc//*[local-name()='subject']"/>
      <xsl:variable name="modsSubjects" select="$dmdMods//*[local-name()='subject']/*[local-name()='topic' or local-name()='geographic' or local-name()='temporal']"/>
      <xsl:if test="$dcSubjects or $modsSubjects">
        <subjects>
          <xsl:for-each select="$dcSubjects">
            <xsl:if test="normalize-space(.) != ''">
              <subject><xsl:value-of select="normalize-space(.)"/></subject>
            </xsl:if>
          </xsl:for-each>
          <xsl:for-each select="$modsSubjects">
            <xsl:if test="normalize-space(.) != ''">
              <subject>
                <xsl:if test="normalize-space(../@authority) != ''">
                  <xsl:attribute name="vocab"><xsl:value-of select="normalize-space(../@authority)"/></xsl:attribute>
                </xsl:if>
                <xsl:value-of select="normalize-space(.)"/>
              </subject>
            </xsl:if>
          </xsl:for-each>
        </subjects>
      </xsl:if>

      <!-- Places from DC coverage -->
      <xsl:variable name="dcCov" select="$dmdDc//*[local-name()='coverage']"/>
      <xsl:if test="$dcCov">
        <places>
          <xsl:for-each select="$dcCov">
            <xsl:if test="normalize-space(.) != ''">
              <place><xsl:value-of select="normalize-space(.)"/></place>
            </xsl:if>
          </xsl:for-each>
        </places>
      </xsl:if>
    </archiveProfile>

    <merge>add-sequence</merge>
  </xsl:template>
</xsl:stylesheet>
