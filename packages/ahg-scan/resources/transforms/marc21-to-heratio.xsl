<?xml version="1.0" encoding="UTF-8"?>
<!--
  marc21-to-heratio.xsl

  Transforms a MARC21 slim-XML <record> into a heratioScan sidecar envelope.
  Handles both individual <record> documents and <collection><record/> wrappers
  (first record wins in the collection case — one sidecar describes one IO).

  Common MARC21 fields mapped:
    001         → identifier (as fallback when 020/022 absent)
    010 $a      → LCCN
    020 $a      → ISBN (digits-only cleanup)
    022 $a      → ISSN
    035 $a      → OCLC number when prefixed with (OCoLC)
    050 $a+$b   → Library of Congress call number
    082 $a      → Dewey Decimal
    100/110/111 → primary creator with role
    245 $a+$b   → title + subtitle (trailing "/" ":" "," stripped)
    250 $a      → edition
    260/264     → publisher / place / date (RDA 264 ind2=1 preferred)
    300         → extent / dimensions
    490 $a      → series title
    520 $a      → scope / summary (abstract)
    650 / 651 / 655 → subjects / places / genres
    700 / 710   → added entry creators

  Copyright (C) 2026 Johan Pieterse, Plain Sailing Information Systems
  Licensed under the GNU AGPL v3.
-->
<xsl:stylesheet version="1.0"
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
    xmlns:marc="http://www.loc.gov/MARC21/slim"
    xmlns="https://heratio.io/scan/v1"
    exclude-result-prefixes="marc">

  <xsl:output method="xml" indent="yes" encoding="UTF-8"/>

  <!-- Trim common MARC21 punctuation endings: "/ " " :" " ;" " ," " ." -->
  <xsl:template name="tidy">
    <xsl:param name="s"/>
    <xsl:variable name="t" select="normalize-space($s)"/>
    <xsl:choose>
      <xsl:when test="substring($t, string-length($t)) = '/' or
                      substring($t, string-length($t)) = ':' or
                      substring($t, string-length($t)) = ';' or
                      substring($t, string-length($t)) = ','">
        <xsl:value-of select="normalize-space(substring($t, 1, string-length($t) - 1))"/>
      </xsl:when>
      <xsl:when test="substring($t, string-length($t) - 1) = ' /'">
        <xsl:value-of select="normalize-space(substring($t, 1, string-length($t) - 2))"/>
      </xsl:when>
      <xsl:otherwise>
        <xsl:value-of select="$t"/>
      </xsl:otherwise>
    </xsl:choose>
  </xsl:template>

  <!-- Subfield lookup: returns first matching $code subfield's text content. -->
  <xsl:template name="sub">
    <xsl:param name="field"/>
    <xsl:param name="code"/>
    <xsl:value-of select="normalize-space($field/*[local-name()='subfield' and @code=$code][1])"/>
  </xsl:template>

  <xsl:template match="/">
    <heratioScan>
      <sector>library</sector>
      <standard>marc21</standard>
      <xsl:apply-templates select="(//*[local-name()='record'])[1]"/>
    </heratioScan>
  </xsl:template>

  <xsl:template match="*[local-name()='record']">
    <!-- Control number / fallback identifier -->
    <xsl:variable name="f001" select="*[local-name()='controlfield' and @tag='001'][1]"/>
    <!-- Preferred identifier: ISBN > ISSN > OCLC > control number -->
    <xsl:variable name="isbn">
      <xsl:call-template name="sub">
        <xsl:with-param name="field" select="*[local-name()='datafield' and @tag='020'][1]"/>
        <xsl:with-param name="code" select="'a'"/>
      </xsl:call-template>
    </xsl:variable>
    <xsl:variable name="issn">
      <xsl:call-template name="sub">
        <xsl:with-param name="field" select="*[local-name()='datafield' and @tag='022'][1]"/>
        <xsl:with-param name="code" select="'a'"/>
      </xsl:call-template>
    </xsl:variable>
    <xsl:variable name="oclc035">
      <xsl:call-template name="sub">
        <xsl:with-param name="field" select="*[local-name()='datafield' and @tag='035'][1]"/>
        <xsl:with-param name="code" select="'a'"/>
      </xsl:call-template>
    </xsl:variable>
    <xsl:variable name="controlId" select="normalize-space($f001)"/>

    <xsl:choose>
      <xsl:when test="$isbn != ''"><identifier><xsl:value-of select="$isbn"/></identifier></xsl:when>
      <xsl:when test="$issn != ''"><identifier><xsl:value-of select="$issn"/></identifier></xsl:when>
      <xsl:when test="$controlId != ''"><identifier><xsl:value-of select="$controlId"/></identifier></xsl:when>
    </xsl:choose>

    <!-- Title from 245 $a + $b -->
    <xsl:variable name="t245" select="*[local-name()='datafield' and @tag='245'][1]"/>
    <xsl:if test="$t245">
      <xsl:variable name="t245a">
        <xsl:call-template name="sub">
          <xsl:with-param name="field" select="$t245"/>
          <xsl:with-param name="code" select="'a'"/>
        </xsl:call-template>
      </xsl:variable>
      <xsl:variable name="t245b">
        <xsl:call-template name="sub">
          <xsl:with-param name="field" select="$t245"/>
          <xsl:with-param name="code" select="'b'"/>
        </xsl:call-template>
      </xsl:variable>
      <title>
        <xsl:choose>
          <xsl:when test="$t245b != ''">
            <xsl:call-template name="tidy"><xsl:with-param name="s" select="concat($t245a, ' ', $t245b)"/></xsl:call-template>
          </xsl:when>
          <xsl:otherwise>
            <xsl:call-template name="tidy"><xsl:with-param name="s" select="$t245a"/></xsl:call-template>
          </xsl:otherwise>
        </xsl:choose>
      </title>
    </xsl:if>

    <levelOfDescription>item</levelOfDescription>

    <!-- Dates: 264 ind2="1" preferred (RDA); fallback 260 $c -->
    <xsl:variable name="f264pub" select="*[local-name()='datafield' and @tag='264' and @ind2='1'][1]"/>
    <xsl:variable name="f260" select="*[local-name()='datafield' and @tag='260'][1]"/>
    <xsl:variable name="dateStr">
      <xsl:choose>
        <xsl:when test="$f264pub">
          <xsl:call-template name="sub">
            <xsl:with-param name="field" select="$f264pub"/>
            <xsl:with-param name="code" select="'c'"/>
          </xsl:call-template>
        </xsl:when>
        <xsl:when test="$f260">
          <xsl:call-template name="sub">
            <xsl:with-param name="field" select="$f260"/>
            <xsl:with-param name="code" select="'c'"/>
          </xsl:call-template>
        </xsl:when>
      </xsl:choose>
    </xsl:variable>
    <xsl:if test="$dateStr != ''">
      <dates>
        <date type="publication">
          <!-- Extract the first 4-digit year from the string as the start date -->
          <xsl:if test="string-length(translate(translate(translate(translate($dateStr,'[',''),']',''),'c',''),'.','')) &gt;= 4">
            <xsl:variable name="cleaned" select="translate(translate(translate(translate($dateStr,'[',''),']',''),'c',''),'.','')"/>
            <xsl:attribute name="start">
              <xsl:value-of select="substring(translate(normalize-space($cleaned),' ',''),1,4)"/>
            </xsl:attribute>
          </xsl:if>
        </date>
      </dates>
    </xsl:if>

    <!-- Access conditions from 506 -->
    <xsl:variable name="f506" select="*[local-name()='datafield' and @tag='506'][1]"/>
    <xsl:if test="$f506">
      <accessConditions>
        <xsl:call-template name="sub">
          <xsl:with-param name="field" select="$f506"/>
          <xsl:with-param name="code" select="'a'"/>
        </xsl:call-template>
      </accessConditions>
    </xsl:if>

    <libraryProfile>
      <xsl:if test="$isbn != ''"><isbn><xsl:value-of select="$isbn"/></isbn></xsl:if>
      <xsl:if test="$issn != ''"><issn><xsl:value-of select="$issn"/></issn></xsl:if>

      <!-- LCCN from 010 $a -->
      <xsl:variable name="lccn">
        <xsl:call-template name="sub">
          <xsl:with-param name="field" select="*[local-name()='datafield' and @tag='010'][1]"/>
          <xsl:with-param name="code" select="'a'"/>
        </xsl:call-template>
      </xsl:variable>
      <xsl:if test="$lccn != ''"><lccn><xsl:value-of select="$lccn"/></lccn></xsl:if>

      <!-- OCLC number (strip (OCoLC) prefix) -->
      <xsl:if test="starts-with($oclc035, '(OCoLC)')">
        <oclc><xsl:value-of select="substring-after($oclc035, '(OCoLC)')"/></oclc>
      </xsl:if>

      <!-- LCC from 050 $a + $b -->
      <xsl:variable name="f050" select="*[local-name()='datafield' and @tag='050'][1]"/>
      <xsl:if test="$f050">
        <xsl:variable name="lccA">
          <xsl:call-template name="sub">
            <xsl:with-param name="field" select="$f050"/>
            <xsl:with-param name="code" select="'a'"/>
          </xsl:call-template>
        </xsl:variable>
        <xsl:variable name="lccB">
          <xsl:call-template name="sub">
            <xsl:with-param name="field" select="$f050"/>
            <xsl:with-param name="code" select="'b'"/>
          </xsl:call-template>
        </xsl:variable>
        <callNumber>
          <xsl:value-of select="normalize-space(concat($lccA, ' ', $lccB))"/>
        </callNumber>
      </xsl:if>

      <!-- Dewey Decimal 082 $a -->
      <xsl:variable name="dewey">
        <xsl:call-template name="sub">
          <xsl:with-param name="field" select="*[local-name()='datafield' and @tag='082'][1]"/>
          <xsl:with-param name="code" select="'a'"/>
        </xsl:call-template>
      </xsl:variable>
      <xsl:if test="$dewey != ''"><deweyDecimal><xsl:value-of select="$dewey"/></deweyDecimal></xsl:if>

      <!-- Edition from 250 $a -->
      <xsl:variable name="ed">
        <xsl:call-template name="sub">
          <xsl:with-param name="field" select="*[local-name()='datafield' and @tag='250'][1]"/>
          <xsl:with-param name="code" select="'a'"/>
        </xsl:call-template>
      </xsl:variable>
      <xsl:if test="$ed != ''">
        <edition>
          <xsl:call-template name="tidy"><xsl:with-param name="s" select="$ed"/></xsl:call-template>
        </edition>
      </xsl:if>

      <!-- Publisher / place / date -->
      <xsl:variable name="pubField" select="($f264pub | $f260)[1]"/>
      <xsl:if test="$pubField">
        <xsl:variable name="placeRaw">
          <xsl:call-template name="sub">
            <xsl:with-param name="field" select="$pubField"/>
            <xsl:with-param name="code" select="'a'"/>
          </xsl:call-template>
        </xsl:variable>
        <xsl:variable name="pubRaw">
          <xsl:call-template name="sub">
            <xsl:with-param name="field" select="$pubField"/>
            <xsl:with-param name="code" select="'b'"/>
          </xsl:call-template>
        </xsl:variable>
        <xsl:if test="$placeRaw != ''">
          <placeOfPublication>
            <xsl:call-template name="tidy"><xsl:with-param name="s" select="$placeRaw"/></xsl:call-template>
          </placeOfPublication>
        </xsl:if>
        <xsl:if test="$pubRaw != ''">
          <publisher>
            <xsl:call-template name="tidy"><xsl:with-param name="s" select="$pubRaw"/></xsl:call-template>
          </publisher>
        </xsl:if>
        <xsl:if test="$dateStr != ''">
          <yearOfPublication>
            <xsl:call-template name="tidy"><xsl:with-param name="s" select="$dateStr"/></xsl:call-template>
          </yearOfPublication>
        </xsl:if>
      </xsl:if>

      <!-- Extent from 300 $a; dimensions from 300 $c -->
      <xsl:variable name="f300" select="*[local-name()='datafield' and @tag='300'][1]"/>
      <xsl:if test="$f300">
        <xsl:variable name="pag">
          <xsl:call-template name="sub">
            <xsl:with-param name="field" select="$f300"/>
            <xsl:with-param name="code" select="'a'"/>
          </xsl:call-template>
        </xsl:variable>
        <xsl:variable name="dim">
          <xsl:call-template name="sub">
            <xsl:with-param name="field" select="$f300"/>
            <xsl:with-param name="code" select="'c'"/>
          </xsl:call-template>
        </xsl:variable>
        <xsl:if test="$pag != ''">
          <pagination>
            <xsl:call-template name="tidy"><xsl:with-param name="s" select="$pag"/></xsl:call-template>
          </pagination>
        </xsl:if>
        <xsl:if test="$dim != ''">
          <dimensions>
            <xsl:call-template name="tidy"><xsl:with-param name="s" select="$dim"/></xsl:call-template>
          </dimensions>
        </xsl:if>
      </xsl:if>

      <!-- Series title from 490 $a -->
      <xsl:variable name="series">
        <xsl:call-template name="sub">
          <xsl:with-param name="field" select="*[local-name()='datafield' and @tag='490'][1]"/>
          <xsl:with-param name="code" select="'a'"/>
        </xsl:call-template>
      </xsl:variable>
      <xsl:if test="$series != ''">
        <seriesTitle>
          <xsl:call-template name="tidy"><xsl:with-param name="s" select="$series"/></xsl:call-template>
        </seriesTitle>
      </xsl:if>

      <!-- Language from 008 char positions 35-37 is fiddly; skip for v1. -->

      <!-- Creators: primary (100/110/111) + added (700/710/711) -->
      <xsl:variable name="mainCreators"
                    select="*[local-name()='datafield' and (@tag='100' or @tag='110' or @tag='111')]"/>
      <xsl:variable name="addedCreators"
                    select="*[local-name()='datafield' and (@tag='700' or @tag='710' or @tag='711')]"/>
      <xsl:if test="$mainCreators or $addedCreators">
        <creators>
          <xsl:for-each select="$mainCreators | $addedCreators">
            <xsl:variable name="nameA">
              <xsl:call-template name="sub">
                <xsl:with-param name="field" select="."/>
                <xsl:with-param name="code" select="'a'"/>
              </xsl:call-template>
            </xsl:variable>
            <xsl:variable name="nameD">
              <xsl:call-template name="sub">
                <xsl:with-param name="field" select="."/>
                <xsl:with-param name="code" select="'d'"/>
              </xsl:call-template>
            </xsl:variable>
            <xsl:variable name="role">
              <xsl:call-template name="sub">
                <xsl:with-param name="field" select="."/>
                <xsl:with-param name="code" select="'e'"/>
              </xsl:call-template>
            </xsl:variable>
            <xsl:if test="$nameA != ''">
              <creator>
                <xsl:attribute name="vocab">lcnaf</xsl:attribute>
                <xsl:if test="$role != ''">
                  <xsl:attribute name="role">
                    <xsl:call-template name="tidy"><xsl:with-param name="s" select="$role"/></xsl:call-template>
                  </xsl:attribute>
                </xsl:if>
                <xsl:choose>
                  <xsl:when test="$nameD != ''">
                    <xsl:call-template name="tidy">
                      <xsl:with-param name="s" select="concat($nameA, ' ', $nameD)"/>
                    </xsl:call-template>
                  </xsl:when>
                  <xsl:otherwise>
                    <xsl:call-template name="tidy"><xsl:with-param name="s" select="$nameA"/></xsl:call-template>
                  </xsl:otherwise>
                </xsl:choose>
              </creator>
            </xsl:if>
          </xsl:for-each>
        </creators>
      </xsl:if>

      <!-- Subjects: 650 (topical) / 651 (geographic, we emit as subject too) / 655 (genre) -->
      <xsl:variable name="subj650" select="*[local-name()='datafield' and (@tag='600' or @tag='610' or @tag='611' or @tag='630' or @tag='650' or @tag='651')]"/>
      <xsl:if test="$subj650">
        <subjects>
          <xsl:for-each select="$subj650">
            <xsl:variable name="heading">
              <xsl:call-template name="sub">
                <xsl:with-param name="field" select="."/>
                <xsl:with-param name="code" select="'a'"/>
              </xsl:call-template>
            </xsl:variable>
            <xsl:variable name="v">
              <xsl:call-template name="sub">
                <xsl:with-param name="field" select="."/>
                <xsl:with-param name="code" select="'v'"/>
              </xsl:call-template>
            </xsl:variable>
            <xsl:variable name="x">
              <xsl:call-template name="sub">
                <xsl:with-param name="field" select="."/>
                <xsl:with-param name="code" select="'x'"/>
              </xsl:call-template>
            </xsl:variable>
            <xsl:if test="$heading != ''">
              <subject>
                <xsl:attribute name="vocab">lcsh</xsl:attribute>
                <xsl:call-template name="tidy">
                  <xsl:with-param name="s" select="concat($heading,
                    substring('--', 1, 2 * (string-length($v) &gt; 0)), $v,
                    substring('--', 1, 2 * (string-length($x) &gt; 0)), $x)"/>
                </xsl:call-template>
              </subject>
            </xsl:if>
          </xsl:for-each>
        </subjects>
      </xsl:if>

      <!-- Genre from 655 $a -->
      <xsl:variable name="f655" select="*[local-name()='datafield' and @tag='655']"/>
      <xsl:if test="$f655">
        <genres>
          <xsl:for-each select="$f655">
            <xsl:variable name="genre">
              <xsl:call-template name="sub">
                <xsl:with-param name="field" select="."/>
                <xsl:with-param name="code" select="'a'"/>
              </xsl:call-template>
            </xsl:variable>
            <xsl:if test="$genre != ''">
              <genre vocab="lcsh">
                <xsl:call-template name="tidy"><xsl:with-param name="s" select="$genre"/></xsl:call-template>
              </genre>
            </xsl:if>
          </xsl:for-each>
        </genres>
      </xsl:if>
    </libraryProfile>

    <merge>add-sequence</merge>
  </xsl:template>
</xsl:stylesheet>
