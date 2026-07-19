{{--
  marketing::compare.atom - Heratio vs AtoM comparison (SEO/sales).
  Reader-facing content only; JSON-LD graph embedded verbatim in the head.

  @license AGPL-3.0-or-later
--}}
@extends('marketing::layout')

@section('title', 'Heratio vs AtoM: a modern alternative to Access to Memory')
@section('meta_description', 'An honest comparison of Heratio and AtoM (Access to Memory): stack, standards, Records in Contexts, museum and DAM support, digital preservation, and when each is the better choice.')
@section('canonical', 'https://heratio.org/compare/atom')

@section('head_extra')
@verbatim
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@graph": [
    {
      "@type": "SoftwareApplication",
      "@id": "https://heratio.org/#software",
      "name": "Heratio",
      "applicationCategory": "BusinessApplication",
      "applicationSubCategory": "Collections Management System",
      "operatingSystem": "Linux, Docker (self-hosted); browser-based access",
      "softwareVersion": "1.154",
      "description": "Heratio is an open-source (AGPL-3.0) Laravel 12 platform for galleries, libraries, archives, and museums. It unifies archival description, museum collections management, digital asset management, and records management, with Records in Contexts (RiC) as a first-class native capability.",
      "url": "https://heratio.org",
      "downloadUrl": "https://github.com/ArchiveHeritageGroup/heratio",
      "license": "https://www.gnu.org/licenses/agpl-3.0.html",
      "isAccessibleForFree": true,
      "programmingLanguage": "PHP",
      "featureList": [
        "ISAD(G), ISAAR(CPF) and ISDIAH archival description",
        "Records in Contexts (RiC and RiC-O), native",
        "Digital asset management with IIIF deep-zoom and 3D viewing",
        "Digital preservation: OAIS, PREMIS, OCFL, BagIt",
        "Portable dark-archive export with no server or database",
        "Research portal: bookings, reproductions, ODRL rights, API keys",
        "AI-assisted HTR, NER, condition assessment and metadata suggestion",
        "Spectrum-capable museum collections management (supports the Spectrum 5.1 procedures)",
        "Runs standalone (Laravel) or as a fully reversible overlay alongside an existing AtoM installation",
        "REST API v1 and v2",
        "Elasticsearch-powered search and discovery"
      ],
      "offers": {
        "@type": "Offer",
        "price": "0",
        "priceCurrency": "USD",
        "description": "Open source under AGPL-3.0; self-hostable at no licence cost. Hosted and support plans available from The AHG."
      },
      "author": { "@id": "https://theahg.co.za/#organization" },
      "publisher": { "@id": "https://theahg.co.za/#organization" }
    },
    {
      "@type": "Organization",
      "@id": "https://theahg.co.za/#organization",
      "name": "The Archive and Heritage Digital Commons Group (Pty) Ltd",
      "alternateName": "The AHG",
      "url": "https://theahg.co.za",
      "sameAs": [
        "https://github.com/ArchiveHeritageGroup",
        "https://openric.org"
      ],
      "description": "Developer of Heratio, an open-source GLAM and archival management platform, and steward of the OpenRiC ecosystem."
    },
    {
      "@type": "FAQPage",
      "@id": "https://heratio.org/#faq",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "What is Heratio?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Heratio is an open-source, Laravel 12 platform for galleries, libraries, archives, and museums. It combines archival description, museum collections management, digital asset management, and records management in one self-hostable application, with Records in Contexts (RiC) as a first-class native capability."
          }
        },
        {
          "@type": "Question",
          "name": "Is Heratio open source?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Yes. Heratio is licensed under AGPL-3.0-or-later and the source is available on GitHub. It can be self-hosted at no licence cost, or run as a hosted service from The AHG."
          }
        },
        {
          "@type": "Question",
          "name": "Is Heratio an alternative to AtoM or ArchivesSpace?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Yes. Heratio is a modern, self-hostable alternative to legacy archival systems such as AtoM and ArchivesSpace. It supports ISAD(G), ISAAR(CPF) and ISDIAH description and adds native Records in Contexts (RiC) support and integrated digital asset management and preservation."
          }
        },
        {
          "@type": "Question",
          "name": "What standards does Heratio support?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Heratio supports ISAD(G), ISAAR(CPF), ISDIAH, Records in Contexts (RiC and RiC-O), Dublin Core, and digital-preservation standards including OAIS, PREMIS, OCFL and BagIt, with IIIF for image delivery."
          }
        },
        {
          "@type": "Question",
          "name": "Does Heratio support the Spectrum standard for museums?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Heratio is Spectrum-capable: it supports the Collections Trust Spectrum 5.1 museum procedures for object entry, acquisition, location and movement, cataloguing, loans, and object exit, alongside its archival description features."
          }
        },
        {
          "@type": "Question",
          "name": "Can universities use Heratio for research data management?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Yes. Heratio supports universities managing special collections, institutional archives, and research data in one platform, including digital preservation and controlled research access."
          }
        }
      ]
    }
  ]
}
</script>
@endverbatim
@endsection

@section('content')
    <h1>Heratio vs AtoM: a modern alternative to Access to Memory</h1>

    <p class="lede">AtoM (Access to Memory) is one of the most widely used open-source archival description systems in the world. Heratio is a newer, Laravel-based platform that covers the same archival ground and extends it to museum collections, digital asset management, digital preservation, and Records in Contexts. This page compares the two honestly, including where AtoM remains the better choice.</p>

    <p>Both are open source under AGPL-3.0 and both are self-hostable, so licensing cost is not a point of difference. The real differences are the technology stack, the breadth of functionality, and how actively each is evolving. And, importantly, adopting Heratio need not mean leaving AtoM at all - see the two deployment editions below.</p>

    <blockquote class="callout">
        <p><strong>Considering a move from AtoM?</strong> <a href="/migration/assessment">Book a free AtoM migration assessment</a> - we will review your AtoM instance, map the migration, and show you the result in Heratio, with no obligation.</p>
    </blockquote>

    <h2>You do not have to choose: two ways to adopt Heratio</h2>
    <p>Heratio ships in two deployment editions, so moving to Heratio is not necessarily a migration decision at all:</p>
    <ul>
        <li><strong>Heratio (standalone)</strong> - the pure <strong>Laravel 12</strong> platform on its own stack. Best for new deployments, or once you have migrated off AtoM. This is the edition compared in the table below.</li>
        <li><strong>AtoM / Heratio (overlay)</strong> - Heratio's <strong>Laravel</strong> modules run <strong>alongside your existing AtoM</strong> (Symfony) installation, over the same AtoM database. Your original AtoM stays intact and fully functional, and the overlay is <strong>fully reversible</strong>: remove it and you are back to stock AtoM. So an existing AtoM site can add Heratio's modern capabilities - Records in Contexts, digital asset management, museum and Spectrum-capable workflows, AI-assisted description, and digital preservation - with <strong>no data migration and no lock-in</strong>.</li>
    </ul>
    <p>The practical upshot: keep AtoM and augment it reversibly with the AtoM / Heratio overlay, or adopt the standalone Laravel platform outright. Many sites start with the overlay to evaluate Heratio on their live collection, then move to standalone later if and when it suits them.</p>

    <h2>At a glance</h2>
    <div class="table-scroll">
        <table>
            <thead>
                <tr>
                    <th></th>
                    <th>Heratio</th>
                    <th>AtoM (Access to Memory)</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Framework / stack</td><td>Laravel 12, PHP 8.3</td><td>Symfony 1.4, PHP (legacy framework, EOL 2012)</td></tr>
                <tr><td>Database / search</td><td>MySQL 8, Elasticsearch 8</td><td>MySQL, Elasticsearch</td></tr>
                <tr><td>Licence</td><td>AGPL-3.0</td><td>AGPL-3.0</td></tr>
                <tr><td>Self-hostable</td><td>Yes</td><td>Yes</td></tr>
                <tr><td>Deployment</td><td>Standalone (Laravel), or reversible overlay alongside AtoM (Symfony + Laravel)</td><td>Standalone</td></tr>
                <tr><td>Archival description</td><td>ISAD(G), ISAAR(CPF), ISDIAH</td><td>ISAD(G), RAD, DACS, ISAAR(CPF), ISDIAH, Dublin Core</td></tr>
                <tr><td>EAD / EAC export</td><td>EAD 2002, EAD3, and EAC-CPF serialization</td><td>EAD 2002 and EAC-CPF export</td></tr>
                <tr><td>EAD / EAC import</td><td>Native EAD 2002 and EAD3 XML import, round-trip safe (preview + commit)</td><td>Mature EAD 2002 and EAC-CPF import</td></tr>
                <tr><td>OAI-PMH</td><td>Serve and harvest (OAI-PMH provider and harvester)</td><td>OAI-PMH provider</td></tr>
                <tr><td>Finding aids</td><td>Generated PDF finding aids</td><td>PDF / RTF finding aid generation</td></tr>
                <tr><td>Records in Contexts (RiC)</td><td>Native, first-class (traditional + RiC view per entity)</td><td>Not native (community and roadmap interest)</td></tr>
                <tr><td>Museum collections</td><td>Spectrum-capable (Spectrum 5.1 procedures)</td><td>Not a museum collections system</td></tr>
                <tr><td>Digital asset management</td><td>Built-in: IIIF deep-zoom, 3D viewing, media at scale</td><td>Basic digital object handling</td></tr>
                <tr><td>Digital preservation</td><td>OCFL, BagIt, OAIS/PREMIS, portable dark-archive export</td><td>Via integration with Archivematica</td></tr>
                <tr><td>Archivematica integration</td><td>Connector (pulls DIPs)</td><td>Native (same steward, Artefactual)</td></tr>
                <tr><td>Research / reading-room portal</td><td>Built-in: bookings, reproductions, ODRL rights, API keys</td><td>Not included</td></tr>
                <tr><td>AI-assisted workflows</td><td>Built-in: HTR, NER, condition assessment, metadata suggestion</td><td>Not included</td></tr>
                <tr><td>Multilingual</td><td>66 locale scaffolds; English and Afrikaans complete, others in progress</td><td>Yes, very strong: ~50 community-maintained locales</td></tr>
                <tr><td>REST API</td><td>v1 and v2 (key auth, OpenAPI)</td><td>Available (older)</td></tr>
                <tr><td>Maturity / install base</td><td>Newer, actively developed, growing</td><td>Mature, very large global install base</td></tr>
                <tr><td>Steward</td><td>The Archive and Heritage Digital Commons Group (The AHG)</td><td>Artefactual Systems + AtoM Foundation</td></tr>
            </tbody>
        </table>
    </div>

    <h2>When AtoM is the right choice</h2>
    <p>AtoM is an excellent, proven system and the better fit when:</p>
    <ul>
        <li>You need a very large <strong>multilingual</strong> deployment with mature, community-maintained translations across many locales today (Heratio's locale completeness is still catching up outside English and Afrikaans).</li>
        <li>You are standardising on the <strong>Artefactual stack</strong> and want AtoM's native, first-party <strong>Archivematica</strong> integration for digital preservation.</li>
        <li>You want the reassurance of a very large global community and install base, and a long track record at national-archive scale.</li>
        <li>Your remit is purely <strong>archival description and access</strong>, with no museum, DAM, or records-management requirement.</li>
    </ul>
    <p>If that describes you, AtoM is a sound, respected choice and Heratio does not claim to replace its multilingual depth or its community size today.</p>

    <h2>When Heratio is the better fit</h2>
    <p>Heratio is the stronger option when:</p>
    <ul>
        <li>You want a <strong>current technology stack</strong>. AtoM runs on Symfony 1.4, a framework that reached end-of-life in 2012; Heratio is built on Laravel 12 and PHP 8.3, which keeps security patching, hiring, and extension realistic for the next decade.</li>
        <li>You need <strong>Records in Contexts (RiC)</strong> as a working capability now, not a roadmap item. Heratio gives every major entity both a traditional archival view and a RiC contextual graph view over the same data, permissions, and identifiers.</li>
        <li>You manage <strong>more than archives</strong>: museum collections (Spectrum-capable), digital assets with IIIF deep-zoom and 3D, and records management, on one platform and data model instead of several integrated products.</li>
        <li>You want <strong>digital preservation built in</strong> (OCFL, BagIt, OAIS/PREMIS) plus a portable dark-archive export that reconstructs a browsable collection with no server or database.</li>
        <li>You want a <strong>research and reading-room portal</strong>, <strong>AI-assisted description</strong> (HTR, NER), and a modern <strong>REST API</strong> without bolting on separate tools.</li>
        <li>You are a <strong>university</strong> needing special collections, institutional archives, and research data managed together.</li>
    </ul>

    <h2>Migrating from AtoM to Heratio</h2>
    <p>Migration is only one path - the AtoM / Heratio overlay above lets you adopt Heratio with no migration at all. If you do choose to migrate to the standalone platform, Heratio imports native EAD 2002 and EAD3 finding aids directly (with a preview step before commit), alongside CSV and authority-record import. A typical migration preserves your ISAD(G) hierarchy, authority (ISAAR) and repository (ISDIAH) records, and digital object links, and can layer a RiC contextual view over the migrated data. Round-trip is safe across the core ISAD(G) fields (title, identifier, scope and content, extent and medium, archival history, acquisition, access and reproduction conditions, arrangement, appraisal).</p>

    <h2>Frequently asked questions</h2>

    <h3>Is Heratio a drop-in replacement for AtoM?</h3>
    <p>Heratio covers the same archival description standards (ISAD(G), ISAAR(CPF), ISDIAH) and adds museum, DAM, preservation, and RiC capabilities. It is a modern alternative rather than a code-compatible fork; migration tooling moves your data across.</p>

    <h3>Is Heratio open source like AtoM?</h3>
    <p>Yes. Both Heratio and AtoM are licensed under AGPL-3.0 and can be self-hosted at no licence cost. Heratio also offers hosted and support plans from The AHG.</p>

    <h3>What is the main technical difference?</h3>
    <p>Stack age and breadth. AtoM is built on Symfony 1.4 (end-of-life 2012) and focuses on archival description; Heratio is built on Laravel 12 and spans archives, museums, DAM, preservation, and records management, with native Records in Contexts.</p>

    <h3>Does Heratio support Records in Contexts (RiC)?</h3>
    <p>Yes, natively. RiC and RiC-O are first-class in Heratio, backed by the OpenRiC ecosystem. AtoM does not provide native RiC today.</p>

    <h3>Can I add Heratio without leaving AtoM?</h3>
    <p>Yes. The AtoM / Heratio overlay edition runs Heratio's Laravel modules alongside your existing AtoM (Symfony) installation, over the same database. The original AtoM stays fully functional and the overlay is fully reversible, so you can add Records in Contexts, DAM, museum and AI capabilities with no migration and no lock-in, and remove it cleanly if you choose. It is the lowest-risk way to evaluate Heratio on your live collection.</p>

    <h3>Should I stay on AtoM?</h3>
    <p>You can have both. Keep AtoM and add the AtoM / Heratio overlay reversibly, or move to the standalone Laravel platform. AtoM remains a strong standalone choice if you need its multilingual depth, native Archivematica integration, or very large community and your remit is archival description only. If you want a current stack, native RiC, and museum/DAM/preservation in one platform, Heratio is the stronger fit - and the overlay lets you get there without a leap.</p>

    <div class="cta-block">
        <h2>Ready to compare on your own data?</h2>
        <p><a href="/migration/assessment">Book a free AtoM migration assessment</a>. We will review your current AtoM instance, plan the migration (EAD/CSV import, authority and repository records, digital objects), and show you your collection running in Heratio - with no obligation.</p>
        <p class="form-actions"><a class="btn" href="/migration/assessment">Book a free AtoM migration assessment</a></p>
    </div>
@endsection
