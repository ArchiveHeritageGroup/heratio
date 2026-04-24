<?xml version="1.0" encoding="UTF-8"?>
<!--
  lido-to-heratio.xsl

  Transforms a LIDO 1.0/1.1 <lido> record into a heratioScan sidecar envelope
  with sector=gallery (LIDO is most common in art-museum / gallery catalogues
  feeding Europeana). Museums using LIDO for ethnographic or natural-history
  collections can override sector in the ingest_session — the transformer
  only sets sector at the sidecar level.

  Common LIDO elements mapped:
    lidoRecID                      → identifier
    titleSet/appellationValue      → title
    objectWorkType/term            → galleryProfile/workType
    classification/term            → museumProfile-like classification (kept
                                     as subject for gallery sector)
    objectMeasurementsSet          → dimensions
    inscriptionsWrap               → inscription
    materialsTech                  → materials / techniques
    eventSet[eventType='Creation'] → artist + creation dates + places
    subjectWrap/subjectSet         → subjects
    objectDescriptionSet/descriptiveNoteValue → scopeAndContent

  Namespaces: LIDO sometimes declares `xmlns:lido="http://www.lido-schema.org"`
  with prefix, other times as default namespace. We use local-name() matching
  to handle both, at the cost of being slightly verbose.

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
-->
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns="https://heratio.io/scan/v1">

  <xsl:output method="xml" indent="yes" encoding="UTF-8"/>

  <xsl:template match="/">
    <heratioScan>
      <sector>gallery</sector>
      <standard>lido</standard>
      <xsl:apply-templates select="(//*[local-name()='lido'])[1]"/>
    </heratioScan>
  </xsl:template>

  <xsl:template match="*[local-name()='lido']">
    <!-- Identifier -->
    <xsl:variable name="lidoId" select="normalize-space(*[local-name()='lidoRecID'][1])"/>
    <xsl:if test="$lidoId != ''"><identifier><xsl:value-of select="$lidoId"/></identifier></xsl:if>

    <!-- Title from the first titleSet appellationValue in the first descriptiveMetadata -->
    <xsl:variable name="titleApp"
                  select="normalize-space((//*[local-name()='titleSet']/*[local-name()='appellationValue'])[1])"/>
    <xsl:if test="$titleApp != ''"><title><xsl:value-of select="$titleApp"/></title></xsl:if>

    <levelOfDescription>item</levelOfDescription>

    <!-- Creation event date(s) -->
    <xsl:variable name="creationEvent"
                  select="(//*[local-name()='event'][
                            *[local-name()='eventType']/*[local-name()='term']='Creation'
                            or *[local-name()='eventType']/*[local-name()='term']='Production'
                            or *[local-name()='eventType']/*[local-name()='term']='creation'])[1]"/>
    <xsl:variable name="earliest"
                  select="normalize-space($creationEvent//*[local-name()='earliestDate'][1])"/>
    <xsl:variable name="latest"
                  select="normalize-space($creationEvent//*[local-name()='latestDate'][1])"/>
    <xsl:if test="$earliest != '' or $latest != ''">
      <dates>
        <date type="creation">
          <xsl:if test="$earliest != ''"><xsl:attribute name="start"><xsl:value-of select="$earliest"/></xsl:attribute></xsl:if>
          <xsl:if test="$latest   != ''"><xsl:attribute name="end"><xsl:value-of select="$latest"/></xsl:attribute></xsl:if>
        </date>
      </dates>
    </xsl:if>

    <!-- Envelope-level scope from descriptiveNoteValue -->
    <xsl:variable name="desc"
                  select="normalize-space((//*[local-name()='objectDescriptionSet']/*[local-name()='descriptiveNoteValue'])[1])"/>
    <xsl:if test="$desc != ''"><scopeAndContent><xsl:value-of select="$desc"/></scopeAndContent></xsl:if>

    <!-- Rights: first rightsWorkSet/rightsType/term -->
    <xsl:variable name="rightsStmt"
                  select="normalize-space((//*[local-name()='rightsWorkSet']/*[local-name()='rightsType']/*[local-name()='term'])[1])"/>
    <xsl:if test="$rightsStmt != ''">
      <!-- LIDO rights are free-text; we keep them via accessConditions rather
           than fabricating a rightsStatements.org URI. -->
      <accessConditions><xsl:value-of select="$rightsStmt"/></accessConditions>
    </xsl:if>

    <galleryProfile>
      <!-- Artist(s) from Creation event's eventActor -->
      <xsl:for-each select="$creationEvent//*[local-name()='actorInRole']">
        <xsl:variable name="actorName"
                      select="normalize-space(*[local-name()='actor']/*[local-name()='nameActorSet']/*[local-name()='appellationValue'][1])"/>
        <xsl:variable name="actorUri"
                      select="normalize-space(*[local-name()='actor']/*[local-name()='actorID'][@type='local' or not(@type)][1])"/>
        <xsl:variable name="roleTerm"
                      select="normalize-space(*[local-name()='roleActor']/*[local-name()='term'][1])"/>
        <xsl:if test="$actorName != ''">
          <artist>
            <xsl:attribute name="displayName"><xsl:value-of select="$actorName"/></xsl:attribute>
            <xsl:if test="$actorUri != ''"><xsl:attribute name="uri"><xsl:value-of select="$actorUri"/></xsl:attribute></xsl:if>
            <xsl:attribute name="role">
              <xsl:choose>
                <xsl:when test="$roleTerm != ''"><xsl:value-of select="$roleTerm"/></xsl:when>
                <xsl:otherwise>artist</xsl:otherwise>
              </xsl:choose>
            </xsl:attribute>
          </artist>
        </xsl:if>
      </xsl:for-each>

      <!-- Work type from objectWorkType/term -->
      <xsl:variable name="workTypeTerm"
                    select="normalize-space((//*[local-name()='objectWorkType']/*[local-name()='term'])[1])"/>
      <xsl:if test="$workTypeTerm != ''">
        <workType>
          <xsl:attribute name="vocab">aat</xsl:attribute>
          <xsl:value-of select="$workTypeTerm"/>
        </workType>
      </xsl:if>

      <!-- Creation date (LIDO uses date range at event level; mirror it here) -->
      <xsl:if test="$earliest != '' or $latest != ''">
        <creationDate>
          <xsl:if test="$earliest != ''"><xsl:attribute name="start"><xsl:value-of select="$earliest"/></xsl:attribute></xsl:if>
          <xsl:if test="$latest   != ''"><xsl:attribute name="end"><xsl:value-of select="$latest"/></xsl:attribute></xsl:if>
          <xsl:attribute name="type">creation</xsl:attribute>
        </creationDate>
      </xsl:if>

      <!-- Materials + techniques from eventMaterialsTech -->
      <xsl:variable name="matTermList"
                    select="$creationEvent//*[local-name()='materialsTech']//*[local-name()='termMaterialsTech']"/>
      <xsl:if test="$matTermList">
        <materials>
          <xsl:for-each select="$matTermList">
            <xsl:variable name="mt" select="normalize-space(*[local-name()='term'][1])"/>
            <xsl:if test="$mt != ''">
              <material vocab="aat"><xsl:value-of select="$mt"/></material>
            </xsl:if>
          </xsl:for-each>
        </materials>
      </xsl:if>

      <!-- Medium from displayMaterialsTech (free-text summary) -->
      <xsl:variable name="medium"
                    select="normalize-space((//*[local-name()='displayMaterialsTech'])[1])"/>
      <xsl:if test="$medium != ''"><medium><xsl:value-of select="$medium"/></medium></xsl:if>

      <!-- Dimensions from objectMeasurementsSet → measurementsSet/measurement -->
      <xsl:variable name="measList"
                    select="//*[local-name()='objectMeasurements']//*[local-name()='measurementsSet']"/>
      <xsl:if test="$measList">
        <dimensions>
          <xsl:for-each select="$measList">
            <xsl:variable name="mType" select="normalize-space(*[local-name()='measurementType'][1])"/>
            <xsl:variable name="mValue" select="normalize-space(*[local-name()='measurementValue'][1])"/>
            <xsl:variable name="mUnit" select="normalize-space(*[local-name()='measurementUnit'][1])"/>
            <xsl:if test="$mType != '' and $mValue != ''">
              <dimension>
                <xsl:attribute name="type"><xsl:value-of select="$mType"/></xsl:attribute>
                <xsl:attribute name="value"><xsl:value-of select="$mValue"/></xsl:attribute>
                <xsl:if test="$mUnit != ''"><xsl:attribute name="unit"><xsl:value-of select="$mUnit"/></xsl:attribute></xsl:if>
              </dimension>
            </xsl:if>
          </xsl:for-each>
        </dimensions>
      </xsl:if>

      <!-- Inscription(s) from inscriptionsWrap -->
      <xsl:variable name="inscr"
                    select="normalize-space((//*[local-name()='inscriptions']/*[local-name()='inscriptionTranscription'])[1])"/>
      <xsl:if test="$inscr != ''"><inscription><xsl:value-of select="$inscr"/></inscription></xsl:if>
    </galleryProfile>

    <merge>add-sequence</merge>
  </xsl:template>
</xsl:stylesheet>
