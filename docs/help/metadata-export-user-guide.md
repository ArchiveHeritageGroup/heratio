> Heratio Help Center article. Category: Import/Export.

# GLAM Metadata Export

## A Guide for Archivists, Librarians, and Collection Managers

---

## What is GLAM Metadata Export?

The GLAM Metadata Export plugin allows you to export your archival descriptions to international metadata standards used by:

- **Archives** - EAD3, RIC-O
- **Libraries** - MARC21, BIBFRAME
- **Museums** - LIDO
- **Visual Resources** - VRA Core 4
- **Media Collections** - PBCore, EBUCore
- **Digital Preservation** - PREMIS

---

## Export Formats at a Glance

| Format | Sector | Output | Best For |
|--------|--------|--------|----------|
| **EAD3** | Archives | XML | Finding aids, ArchivesSpace, ArchivesHub |
| **RIC-O** | Archives | JSON-LD | Linked data, semantic web |
| **LIDO** | Museums | XML | Europeana, museum aggregators |
| **MARC21** | Libraries | XML | Library catalogs (Koha, Alma) |
| **BIBFRAME** | Libraries | JSON-LD | Library of Congress linked data |
| **VRA Core 4** | Visual | XML | Art/photography collections |
| **PBCore** | Media | XML | Public broadcasting, video archives |
| **EBUCore** | Media | XML | European broadcasters |
| **PREMIS** | Preservation | XML | Digital preservation systems |

---

## Using the Web Interface

### Single Record Export

<div style="overflow-x:auto;margin:1rem 0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 222 548" style="max-width:100%;height:auto;font-family:ui-monospace,Menlo,Consolas,monospace"><rect x="0.5" y="0.5" width="221" height="547" rx="8" fill="#f7faf9" stroke="#d8e6e3"/><line x1="13.6" y1="18.0" x2="17.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="18.0" x2="13.6" y2="26.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="18.0" x2="20.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="20.8" y1="18.0" x2="24.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="18.0" x2="28.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="18.0" x2="31.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="18.0" x2="35.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="18.0" x2="38.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="18.0" x2="42.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="18.0" x2="46.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="18.0" x2="49.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="18.0" x2="53.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="18.0" x2="56.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="18.0" x2="60.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="18.0" x2="64.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="64.0" y1="18.0" x2="67.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="18.0" x2="71.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="18.0" x2="74.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="18.0" x2="78.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="18.0" x2="82.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="18.0" x2="85.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="85.6" y1="18.0" x2="89.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="18.0" x2="92.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="92.8" y1="18.0" x2="96.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="18.0" x2="100.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="18.0" x2="103.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="18.0" x2="107.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="107.2" y1="18.0" x2="110.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="18.0" x2="114.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="114.4" y1="18.0" x2="118.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="18.0" x2="121.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="18.0" x2="125.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="18.0" x2="128.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="128.8" y1="18.0" x2="132.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="18.0" x2="136.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="18.0" x2="139.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="18.0" x2="143.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="143.2" y1="18.0" x2="146.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="18.0" x2="150.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="150.4" y1="18.0" x2="154.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="18.0" x2="157.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="18.0" x2="161.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="18.0" x2="164.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="18.0" x2="168.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="18.0" x2="172.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="18.0" x2="175.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="18.0" x2="179.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="18.0" x2="182.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="18.0" x2="186.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="18.0" x2="190.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="18.0" x2="193.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="18.0" x2="197.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="18.0" x2="200.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="18.0" x2="204.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="18.0" x2="208.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="18.0" x2="208.0" y2="26.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="26.0" x2="13.6" y2="34.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="34.0" x2="13.6" y2="42.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="26.0" x2="208.0" y2="34.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="34.0" x2="208.0" y2="42.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="50.0" x2="17.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="42.0" x2="13.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="50.0" x2="20.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="20.8" y1="50.0" x2="24.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="50.0" x2="28.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="50.0" x2="31.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="50.0" x2="35.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="50.0" x2="38.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="50.0" x2="42.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="50.0" x2="46.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="50.0" x2="49.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="50.0" x2="53.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="50.0" x2="56.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="50.0" x2="60.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="50.0" x2="64.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="64.0" y1="50.0" x2="67.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="50.0" x2="71.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="50.0" x2="74.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="50.0" x2="78.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="50.0" x2="82.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="50.0" x2="85.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="85.6" y1="50.0" x2="89.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="50.0" x2="92.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="92.8" y1="50.0" x2="96.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="50.0" x2="100.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="50.0" x2="103.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="50.0" x2="100.0" y2="58.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="50.0" x2="107.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="107.2" y1="50.0" x2="110.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="50.0" x2="114.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="114.4" y1="50.0" x2="118.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="50.0" x2="121.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="50.0" x2="125.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="50.0" x2="128.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="128.8" y1="50.0" x2="132.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="50.0" x2="136.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="50.0" x2="139.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="50.0" x2="143.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="143.2" y1="50.0" x2="146.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="50.0" x2="150.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="150.4" y1="50.0" x2="154.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="50.0" x2="157.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="50.0" x2="161.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="50.0" x2="164.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="50.0" x2="168.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="50.0" x2="172.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="50.0" x2="175.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="50.0" x2="179.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="50.0" x2="182.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="50.0" x2="186.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="50.0" x2="190.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="50.0" x2="193.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="50.0" x2="197.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="50.0" x2="200.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="50.0" x2="204.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="50.0" x2="208.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="42.0" x2="208.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="58.0" x2="100.0" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="66.0" x2="100.0" y2="74.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="98.0" x2="17.2" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="98.0" x2="13.6" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="98.0" x2="20.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="20.8" y1="98.0" x2="24.4" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="98.0" x2="28.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="98.0" x2="31.6" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="98.0" x2="35.2" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="98.0" x2="38.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="98.0" x2="42.4" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="98.0" x2="46.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="98.0" x2="49.6" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="98.0" x2="53.2" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="98.0" x2="56.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="98.0" x2="60.4" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="98.0" x2="64.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="64.0" y1="98.0" x2="67.6" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="98.0" x2="71.2" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="98.0" x2="74.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="98.0" x2="78.4" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="98.0" x2="82.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="98.0" x2="85.6" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="85.6" y1="98.0" x2="89.2" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="98.0" x2="92.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="92.8" y1="98.0" x2="96.4" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="98.0" x2="100.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="98.0" x2="103.6" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="98.0" x2="107.2" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="107.2" y1="98.0" x2="110.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="98.0" x2="114.4" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="114.4" y1="98.0" x2="118.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="98.0" x2="121.6" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="98.0" x2="125.2" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="98.0" x2="128.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="128.8" y1="98.0" x2="132.4" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="98.0" x2="136.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="98.0" x2="139.6" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="98.0" x2="143.2" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="143.2" y1="98.0" x2="146.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="98.0" x2="150.4" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="150.4" y1="98.0" x2="154.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="98.0" x2="157.6" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="98.0" x2="161.2" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="98.0" x2="164.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="98.0" x2="168.4" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="98.0" x2="172.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="98.0" x2="175.6" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="98.0" x2="179.2" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="98.0" x2="182.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="98.0" x2="186.4" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="98.0" x2="190.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="98.0" x2="193.6" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="98.0" x2="197.2" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="98.0" x2="200.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="98.0" x2="204.4" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="98.0" x2="208.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="98.0" x2="208.0" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="106.0" x2="13.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="114.0" x2="13.6" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="106.0" x2="208.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="114.0" x2="208.0" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="122.0" x2="13.6" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="130.0" x2="13.6" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="122.0" x2="208.0" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="130.0" x2="208.0" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="146.0" x2="17.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="138.0" x2="13.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="146.0" x2="20.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="20.8" y1="146.0" x2="24.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="146.0" x2="28.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="146.0" x2="31.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="146.0" x2="35.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="146.0" x2="38.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="146.0" x2="42.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="146.0" x2="46.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="146.0" x2="49.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="146.0" x2="53.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="146.0" x2="56.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="146.0" x2="60.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="146.0" x2="64.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="64.0" y1="146.0" x2="67.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="146.0" x2="71.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="146.0" x2="74.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="146.0" x2="78.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="146.0" x2="82.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="146.0" x2="85.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="85.6" y1="146.0" x2="89.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="146.0" x2="92.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="92.8" y1="146.0" x2="96.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="146.0" x2="100.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="146.0" x2="103.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="146.0" x2="100.0" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="146.0" x2="107.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="107.2" y1="146.0" x2="110.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="146.0" x2="114.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="114.4" y1="146.0" x2="118.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="146.0" x2="121.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="146.0" x2="125.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="146.0" x2="128.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="128.8" y1="146.0" x2="132.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="146.0" x2="136.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="146.0" x2="139.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="146.0" x2="143.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="143.2" y1="146.0" x2="146.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="146.0" x2="150.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="150.4" y1="146.0" x2="154.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="146.0" x2="157.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="146.0" x2="161.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="146.0" x2="164.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="146.0" x2="168.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="146.0" x2="172.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="146.0" x2="175.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="146.0" x2="179.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="146.0" x2="182.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="146.0" x2="186.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="146.0" x2="190.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="146.0" x2="193.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="146.0" x2="197.2" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="146.0" x2="200.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="146.0" x2="204.4" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="146.0" x2="208.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="138.0" x2="208.0" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="154.0" x2="100.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="162.0" x2="100.0" y2="170.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="194.0" x2="17.2" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="194.0" x2="13.6" y2="202.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="194.0" x2="20.8" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="20.8" y1="194.0" x2="24.4" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="194.0" x2="28.0" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="194.0" x2="31.6" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="194.0" x2="35.2" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="194.0" x2="38.8" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="194.0" x2="42.4" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="194.0" x2="46.0" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="194.0" x2="49.6" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="194.0" x2="53.2" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="194.0" x2="56.8" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="194.0" x2="60.4" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="194.0" x2="64.0" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="64.0" y1="194.0" x2="67.6" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="194.0" x2="71.2" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="194.0" x2="74.8" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="194.0" x2="78.4" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="194.0" x2="82.0" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="194.0" x2="85.6" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="85.6" y1="194.0" x2="89.2" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="194.0" x2="92.8" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="92.8" y1="194.0" x2="96.4" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="194.0" x2="100.0" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="194.0" x2="103.6" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="194.0" x2="107.2" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="107.2" y1="194.0" x2="110.8" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="194.0" x2="114.4" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="114.4" y1="194.0" x2="118.0" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="194.0" x2="121.6" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="194.0" x2="125.2" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="194.0" x2="128.8" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="128.8" y1="194.0" x2="132.4" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="194.0" x2="136.0" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="194.0" x2="139.6" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="194.0" x2="143.2" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="143.2" y1="194.0" x2="146.8" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="194.0" x2="150.4" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="150.4" y1="194.0" x2="154.0" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="194.0" x2="157.6" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="194.0" x2="161.2" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="194.0" x2="164.8" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="194.0" x2="168.4" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="194.0" x2="172.0" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="194.0" x2="175.6" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="194.0" x2="179.2" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="194.0" x2="182.8" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="194.0" x2="186.4" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="194.0" x2="190.0" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="194.0" x2="193.6" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="194.0" x2="197.2" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="194.0" x2="200.8" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="194.0" x2="204.4" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="194.0" x2="208.0" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="194.0" x2="208.0" y2="202.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="202.0" x2="13.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="210.0" x2="13.6" y2="218.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="202.0" x2="208.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="210.0" x2="208.0" y2="218.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="218.0" x2="13.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="226.0" x2="13.6" y2="234.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="218.0" x2="208.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="226.0" x2="208.0" y2="234.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="234.0" x2="13.6" y2="242.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="242.0" x2="13.6" y2="250.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="234.0" x2="208.0" y2="242.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="242.0" x2="208.0" y2="250.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="250.0" x2="13.6" y2="258.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="258.0" x2="13.6" y2="266.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="250.0" x2="208.0" y2="258.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="258.0" x2="208.0" y2="266.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="266.0" x2="13.6" y2="274.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="274.0" x2="13.6" y2="282.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="266.0" x2="208.0" y2="274.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="274.0" x2="208.0" y2="282.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="282.0" x2="13.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="290.0" x2="13.6" y2="298.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="282.0" x2="208.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="290.0" x2="208.0" y2="298.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="298.0" x2="13.6" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="306.0" x2="13.6" y2="314.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="298.0" x2="208.0" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="306.0" x2="208.0" y2="314.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="322.0" x2="17.2" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="314.0" x2="13.6" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="322.0" x2="20.8" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="20.8" y1="322.0" x2="24.4" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="322.0" x2="28.0" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="322.0" x2="31.6" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="322.0" x2="35.2" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="322.0" x2="38.8" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="322.0" x2="42.4" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="322.0" x2="46.0" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="322.0" x2="49.6" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="322.0" x2="53.2" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="322.0" x2="56.8" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="322.0" x2="60.4" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="322.0" x2="64.0" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="64.0" y1="322.0" x2="67.6" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="322.0" x2="71.2" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="322.0" x2="74.8" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="322.0" x2="78.4" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="322.0" x2="82.0" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="322.0" x2="85.6" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="85.6" y1="322.0" x2="89.2" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="322.0" x2="92.8" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="92.8" y1="322.0" x2="96.4" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="322.0" x2="100.0" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="322.0" x2="103.6" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="322.0" x2="100.0" y2="330.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="322.0" x2="107.2" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="107.2" y1="322.0" x2="110.8" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="322.0" x2="114.4" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="114.4" y1="322.0" x2="118.0" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="322.0" x2="121.6" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="322.0" x2="125.2" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="322.0" x2="128.8" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="128.8" y1="322.0" x2="132.4" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="322.0" x2="136.0" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="322.0" x2="139.6" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="322.0" x2="143.2" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="143.2" y1="322.0" x2="146.8" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="322.0" x2="150.4" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="150.4" y1="322.0" x2="154.0" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="322.0" x2="157.6" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="322.0" x2="161.2" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="322.0" x2="164.8" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="322.0" x2="168.4" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="322.0" x2="172.0" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="322.0" x2="175.6" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="322.0" x2="179.2" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="322.0" x2="182.8" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="322.0" x2="186.4" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="322.0" x2="190.0" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="322.0" x2="193.6" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="322.0" x2="197.2" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="322.0" x2="200.8" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="322.0" x2="204.4" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="322.0" x2="208.0" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="314.0" x2="208.0" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="330.0" x2="100.0" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="338.0" x2="100.0" y2="346.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="370.0" x2="17.2" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="370.0" x2="13.6" y2="378.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="370.0" x2="20.8" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="20.8" y1="370.0" x2="24.4" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="370.0" x2="28.0" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="370.0" x2="31.6" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="370.0" x2="35.2" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="370.0" x2="38.8" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="370.0" x2="42.4" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="370.0" x2="46.0" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="370.0" x2="49.6" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="370.0" x2="53.2" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="370.0" x2="56.8" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="370.0" x2="60.4" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="370.0" x2="64.0" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="64.0" y1="370.0" x2="67.6" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="370.0" x2="71.2" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="370.0" x2="74.8" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="370.0" x2="78.4" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="370.0" x2="82.0" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="370.0" x2="85.6" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="85.6" y1="370.0" x2="89.2" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="370.0" x2="92.8" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="92.8" y1="370.0" x2="96.4" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="370.0" x2="100.0" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="370.0" x2="103.6" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="370.0" x2="107.2" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="107.2" y1="370.0" x2="110.8" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="370.0" x2="114.4" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="114.4" y1="370.0" x2="118.0" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="370.0" x2="121.6" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="370.0" x2="125.2" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="370.0" x2="128.8" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="128.8" y1="370.0" x2="132.4" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="370.0" x2="136.0" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="370.0" x2="139.6" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="370.0" x2="143.2" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="143.2" y1="370.0" x2="146.8" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="370.0" x2="150.4" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="150.4" y1="370.0" x2="154.0" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="370.0" x2="157.6" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="370.0" x2="161.2" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="370.0" x2="164.8" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="370.0" x2="168.4" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="370.0" x2="172.0" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="370.0" x2="175.6" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="370.0" x2="179.2" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="370.0" x2="182.8" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="370.0" x2="186.4" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="370.0" x2="190.0" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="370.0" x2="193.6" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="370.0" x2="197.2" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="370.0" x2="200.8" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="370.0" x2="204.4" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="370.0" x2="208.0" y2="370.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="370.0" x2="208.0" y2="378.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="378.0" x2="13.6" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="386.0" x2="13.6" y2="394.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="378.0" x2="208.0" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="386.0" x2="208.0" y2="394.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="394.0" x2="13.6" y2="402.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="402.0" x2="13.6" y2="410.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="394.0" x2="208.0" y2="402.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="402.0" x2="208.0" y2="410.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="410.0" x2="13.6" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="418.0" x2="13.6" y2="426.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="410.0" x2="208.0" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="418.0" x2="208.0" y2="426.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="426.0" x2="13.6" y2="434.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="434.0" x2="13.6" y2="442.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="426.0" x2="208.0" y2="434.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="434.0" x2="208.0" y2="442.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="450.0" x2="17.2" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="442.0" x2="13.6" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="450.0" x2="20.8" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="20.8" y1="450.0" x2="24.4" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="450.0" x2="28.0" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="450.0" x2="31.6" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="450.0" x2="35.2" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="450.0" x2="38.8" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="450.0" x2="42.4" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="450.0" x2="46.0" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="450.0" x2="49.6" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="450.0" x2="53.2" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="450.0" x2="56.8" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="450.0" x2="60.4" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="450.0" x2="64.0" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="64.0" y1="450.0" x2="67.6" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="450.0" x2="71.2" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="450.0" x2="74.8" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="450.0" x2="78.4" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="450.0" x2="82.0" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="450.0" x2="85.6" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="85.6" y1="450.0" x2="89.2" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="450.0" x2="92.8" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="92.8" y1="450.0" x2="96.4" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="450.0" x2="100.0" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="450.0" x2="103.6" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="450.0" x2="100.0" y2="458.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="450.0" x2="107.2" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="107.2" y1="450.0" x2="110.8" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="450.0" x2="114.4" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="114.4" y1="450.0" x2="118.0" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="450.0" x2="121.6" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="450.0" x2="125.2" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="450.0" x2="128.8" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="128.8" y1="450.0" x2="132.4" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="450.0" x2="136.0" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="450.0" x2="139.6" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="450.0" x2="143.2" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="143.2" y1="450.0" x2="146.8" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="450.0" x2="150.4" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="150.4" y1="450.0" x2="154.0" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="450.0" x2="157.6" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="450.0" x2="161.2" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="450.0" x2="164.8" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="450.0" x2="168.4" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="450.0" x2="172.0" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="450.0" x2="175.6" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="450.0" x2="179.2" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="450.0" x2="182.8" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="450.0" x2="186.4" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="450.0" x2="190.0" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="450.0" x2="193.6" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="450.0" x2="197.2" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="450.0" x2="200.8" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="450.0" x2="204.4" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="450.0" x2="208.0" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="442.0" x2="208.0" y2="450.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="458.0" x2="100.0" y2="466.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="466.0" x2="100.0" y2="474.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="498.0" x2="17.2" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="498.0" x2="13.6" y2="506.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="498.0" x2="20.8" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="20.8" y1="498.0" x2="24.4" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="498.0" x2="28.0" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="498.0" x2="31.6" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="498.0" x2="35.2" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="498.0" x2="38.8" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="498.0" x2="42.4" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="498.0" x2="46.0" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="498.0" x2="49.6" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="498.0" x2="53.2" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="498.0" x2="56.8" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="498.0" x2="60.4" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="498.0" x2="64.0" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="64.0" y1="498.0" x2="67.6" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="498.0" x2="71.2" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="498.0" x2="74.8" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="498.0" x2="78.4" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="498.0" x2="82.0" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="498.0" x2="85.6" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="85.6" y1="498.0" x2="89.2" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="498.0" x2="92.8" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="92.8" y1="498.0" x2="96.4" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="498.0" x2="100.0" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="498.0" x2="103.6" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="498.0" x2="107.2" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="107.2" y1="498.0" x2="110.8" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="498.0" x2="114.4" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="114.4" y1="498.0" x2="118.0" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="498.0" x2="121.6" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="498.0" x2="125.2" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="498.0" x2="128.8" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="128.8" y1="498.0" x2="132.4" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="498.0" x2="136.0" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="498.0" x2="139.6" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="498.0" x2="143.2" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="143.2" y1="498.0" x2="146.8" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="498.0" x2="150.4" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="150.4" y1="498.0" x2="154.0" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="498.0" x2="157.6" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="498.0" x2="161.2" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="498.0" x2="164.8" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="498.0" x2="168.4" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="498.0" x2="172.0" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="498.0" x2="175.6" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="498.0" x2="179.2" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="498.0" x2="182.8" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="498.0" x2="186.4" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="498.0" x2="190.0" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="498.0" x2="193.6" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="498.0" x2="197.2" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="498.0" x2="200.8" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="498.0" x2="204.4" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="498.0" x2="208.0" y2="498.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="498.0" x2="208.0" y2="506.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="506.0" x2="13.6" y2="514.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="514.0" x2="13.6" y2="522.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="506.0" x2="208.0" y2="514.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="514.0" x2="208.0" y2="522.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="530.0" x2="17.2" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="522.0" x2="13.6" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="530.0" x2="20.8" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="20.8" y1="530.0" x2="24.4" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="530.0" x2="28.0" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="530.0" x2="31.6" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="530.0" x2="35.2" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="530.0" x2="38.8" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="530.0" x2="42.4" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="530.0" x2="46.0" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="530.0" x2="49.6" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="530.0" x2="53.2" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="530.0" x2="56.8" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="530.0" x2="60.4" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="530.0" x2="64.0" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="64.0" y1="530.0" x2="67.6" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="530.0" x2="71.2" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="530.0" x2="74.8" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="530.0" x2="78.4" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="530.0" x2="82.0" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="530.0" x2="85.6" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="85.6" y1="530.0" x2="89.2" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="530.0" x2="92.8" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="92.8" y1="530.0" x2="96.4" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="530.0" x2="100.0" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="530.0" x2="103.6" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="530.0" x2="107.2" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="107.2" y1="530.0" x2="110.8" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="530.0" x2="114.4" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="114.4" y1="530.0" x2="118.0" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="530.0" x2="121.6" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="530.0" x2="125.2" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="530.0" x2="128.8" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="128.8" y1="530.0" x2="132.4" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="530.0" x2="136.0" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="530.0" x2="139.6" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="530.0" x2="143.2" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="143.2" y1="530.0" x2="146.8" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="530.0" x2="150.4" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="150.4" y1="530.0" x2="154.0" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="530.0" x2="157.6" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="530.0" x2="161.2" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="530.0" x2="164.8" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="530.0" x2="168.4" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="530.0" x2="172.0" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="530.0" x2="175.6" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="530.0" x2="179.2" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="530.0" x2="182.8" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="530.0" x2="186.4" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="530.0" x2="190.0" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="530.0" x2="193.6" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="530.0" x2="197.2" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="530.0" x2="200.8" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="530.0" x2="204.4" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="530.0" x2="208.0" y2="530.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="522.0" x2="208.0" y2="530.0" stroke="#10373E" stroke-width="1.3"/><path d="M96.0 77.0 L100.0 84.0 L104.0 77.0 Z" fill="#10373E"/><path d="M96.0 173.0 L100.0 180.0 L104.0 173.0 Z" fill="#10373E"/><path d="M96.0 349.0 L100.0 356.0 L104.0 349.0 Z" fill="#10373E"/><path d="M96.0 477.0 L100.0 484.0 L104.0 477.0 Z" fill="#10373E"/><text x="31.6" y="38.0" font-size="9.5" fill="#10373E">View</text><text x="67.6" y="38.0" font-size="9.5" fill="#10373E">any</text><text x="96.4" y="38.0" font-size="9.5" fill="#10373E">record</text><text x="31.6" y="118.0" font-size="9.5" fill="#10373E">Click</text><text x="74.8" y="118.0" font-size="9.5" fill="#10373E">&quot;Export&quot;</text><text x="139.6" y="118.0" font-size="9.5" fill="#10373E">button</text><text x="31.6" y="134.0" font-size="9.5" fill="#10373E">(top</text><text x="67.6" y="134.0" font-size="9.5" fill="#10373E">right</text><text x="110.8" y="134.0" font-size="9.5" fill="#10373E">area)</text><text x="31.6" y="214.0" font-size="9.5" fill="#10373E">Select</text><text x="82.0" y="214.0" font-size="9.5" fill="#10373E">format:</text><text x="31.6" y="230.0" font-size="9.5" fill="#10373E">•</text><text x="46.0" y="230.0" font-size="9.5" fill="#10373E">EAD3</text><text x="31.6" y="246.0" font-size="9.5" fill="#10373E">•</text><text x="46.0" y="246.0" font-size="9.5" fill="#10373E">RIC-O</text><text x="89.2" y="246.0" font-size="9.5" fill="#10373E">(JSON-LD)</text><text x="31.6" y="262.0" font-size="9.5" fill="#10373E">•</text><text x="46.0" y="262.0" font-size="9.5" fill="#10373E">LIDO</text><text x="31.6" y="278.0" font-size="9.5" fill="#10373E">•</text><text x="46.0" y="278.0" font-size="9.5" fill="#10373E">MARC21</text><text x="31.6" y="294.0" font-size="9.5" fill="#10373E">•</text><text x="46.0" y="294.0" font-size="9.5" fill="#10373E">PREMIS</text><text x="31.6" y="310.0" font-size="9.5" fill="#10373E">•</text><text x="46.0" y="310.0" font-size="9.5" fill="#10373E">(more...)</text><text x="31.6" y="390.0" font-size="9.5" fill="#10373E">Configure</text><text x="103.6" y="390.0" font-size="9.5" fill="#10373E">options:</text><text x="31.6" y="406.0" font-size="9.5" fill="#10373E">☑</text><text x="46.0" y="406.0" font-size="9.5" fill="#10373E">Include</text><text x="103.6" y="406.0" font-size="9.5" fill="#10373E">children</text><text x="31.6" y="422.0" font-size="9.5" fill="#10373E">☑</text><text x="46.0" y="422.0" font-size="9.5" fill="#10373E">Include</text><text x="103.6" y="422.0" font-size="9.5" fill="#10373E">digital</text><text x="161.2" y="422.0" font-size="9.5" fill="#10373E">obj</text><text x="31.6" y="438.0" font-size="9.5" fill="#10373E">☐</text><text x="46.0" y="438.0" font-size="9.5" fill="#10373E">Include</text><text x="103.6" y="438.0" font-size="9.5" fill="#10373E">drafts</text><text x="31.6" y="518.0" font-size="9.5" fill="#10373E">Download</text><text x="96.4" y="518.0" font-size="9.5" fill="#10373E">file</text></svg></div>

### Steps

1. Navigate to any archival description
2. Look for the **Export** dropdown or button
3. Select your desired format
4. Configure export options
5. Click **Export** or **Download**

---

## Using the Command Line

The CLI is ideal for bulk exports and automation.

### Basic Commands

```bash
# List all available formats
php artisan ahg:metadata-export --list

# Export single record to EAD3
php artisan ahg:metadata-export --format=ead3 --slug=my-fonds

# Export to all formats at once
php artisan ahg:metadata-export --format=all --slug=my-fonds --output=/exports/
```

### Export Options

| Option | Description |
|--------|-------------|
| `--format=FORMAT` | Export format (ead3, rico, lido, marc21, etc.) |
| `--slug=SLUG` | Record slug to export |
| `--repository=SLUG` | Export all records from a repository (by repository slug) |
| `--output=PATH` | Output directory (default: /tmp) |
| `--include-children` | Include child records |
| `--include-digital-objects` | Include digital object metadata |
| `--include-drafts` | Include unpublished records |

### Examples

**Export a finding aid to EAD3:**
```bash
php artisan ahg:metadata-export --format=ead3 --slug=smith-family-papers --output=/exports/
```

**Export entire repository to RIC-O linked data:**
```bash
php artisan ahg:metadata-export --format=rico --repository=my-repository --include-children --output=/exports/rico/
```

**Export museum objects to LIDO:**
```bash
php artisan ahg:metadata-export --format=lido --repository=my-repository --include-digital-objects --output=/exports/lido/
```

**Export all formats for a record:**
```bash
php artisan ahg:metadata-export --format=all --slug=my-record --output=/exports/multi-format/
```

---

## Understanding the Formats

### For Archives

#### EAD3 (Encoded Archival Description 3)

The latest version of the standard finding aid format.

```xml
<ead xmlns="http://ead3.archivists.org/schema/">
  <control>
    <recordid>F001</recordid>
  </control>
  <archdesc level="fonds">
    <did>
      <unittitle>Smith Papers</unittitle>
      <unitdate>1920-1950</unitdate>
    </did>
    <scopecontent>
      <p>Personal papers of John Smith...</p>
    </scopecontent>
  </archdesc>
</ead>
```

**Use for:** ArchivesSpace, ArchivesHub, Library of Congress

#### RIC-O (Records in Contexts - Ontology)

Linked data format from the International Council on Archives.

```json
{
  "@context": "https://www.ica.org/standards/RiC/ontology#",
  "@type": "rico:RecordSet",
  "rico:identifier": "F001",
  "rico:title": "Smith Papers",
  "rico:hasOrHadCreator": {
    "@type": "rico:Person",
    "rico:name": "John Smith"
  }
}
```

**Use for:** Semantic web, linked data publishing, knowledge graphs

---

### For Libraries

#### MARC21

Standard library catalog format.

```xml
<record>
  <leader>00000npc a2200000 u 4500</leader>
  <controlfield tag="001">F001</controlfield>
  <datafield tag="245" ind1="1" ind2="0">
    <subfield code="a">Smith Papers</subfield>
  </datafield>
  <datafield tag="520">
    <subfield code="a">Personal papers of John Smith...</subfield>
  </datafield>
</record>
```

**Use for:** Koha, Evergreen, Alma, WorldCat

#### BIBFRAME

Library of Congress linked data format.

```json
{
  "@context": "http://id.loc.gov/ontologies/bibframe/",
  "@type": "bf:Work",
  "bf:title": {
    "@type": "bf:Title",
    "bf:mainTitle": "Smith Papers"
  }
}
```

**Use for:** Library linked data, id.loc.gov

---

### For Museums

#### LIDO (Lightweight Information Describing Objects)

Standard for museum object metadata.

```xml
<lido:lido>
  <lido:lidoRecID>OBJ-001</lido:lidoRecID>
  <lido:descriptiveMetadata>
    <lido:objectIdentificationWrap>
      <lido:titleWrap>
        <lido:titleSet>
          <lido:appellationValue>Portrait of a Lady</lido:appellationValue>
        </lido:titleSet>
      </lido:titleWrap>
    </lido:objectIdentificationWrap>
  </lido:descriptiveMetadata>
</lido:lido>
```

**Use for:** Europeana, museum aggregators, CollectiveAccess

---

### For Visual Resources

#### VRA Core 4

Visual Resources Association standard.

```xml
<vra:vra>
  <vra:work>
    <vra:titleSet>
      <vra:title>Photograph of Main Street</vra:title>
    </vra:titleSet>
    <vra:dateSet>
      <vra:date type="creation">1945</vra:date>
    </vra:dateSet>
  </vra:work>
  <vra:image>
    <vra:measurementsSet>
      <vra:measurements>1024x768 pixels</vra:measurements>
    </vra:measurementsSet>
  </vra:image>
</vra:vra>
```

**Use for:** Art libraries, image repositories

---

### For Media Collections

#### PBCore (Public Broadcasting Core)

Metadata for audiovisual content.

```xml
<pbcoreDescriptionDocument>
  <pbcoreIdentifier>VID-001</pbcoreIdentifier>
  <pbcoreTitle>Interview with John Smith</pbcoreTitle>
  <pbcoreDescription>Oral history interview...</pbcoreDescription>
</pbcoreDescriptionDocument>
```

**Use for:** PBS, public broadcasters, video archives

#### EBUCore

European Broadcasting Union standard.

**Use for:** European broadcasters, media archives

---

### For Digital Preservation

#### PREMIS

Preservation Metadata Implementation Strategies.

```xml
<premis:premis>
  <premis:object xsi:type="premis:file">
    <premis:objectIdentifier>
      <premis:objectIdentifierType>local</premis:objectIdentifierType>
      <premis:objectIdentifierValue>DO-001</premis:objectIdentifierValue>
    </premis:objectIdentifier>
    <premis:objectCharacteristics>
      <premis:format>
        <premis:formatDesignation>
          <premis:formatName>image/tiff</premis:formatName>
        </premis:formatDesignation>
      </premis:format>
    </premis:objectCharacteristics>
  </premis:object>
  <premis:event>
    <premis:eventType>ingestion</premis:eventType>
  </premis:event>
</premis:premis>
```

**Use for:** Archivematica, Preservica, Rosetta, digital preservation workflows

---

## DOI Integration

### What is a DOI?

A **Digital Object Identifier (DOI)** is a persistent identifier used to uniquely identify digital objects. DOIs are widely used in academic publishing and increasingly in cultural heritage to provide permanent, citable links to archival records.

Example DOI: `10.12345/archive.2025.001`
Resolves to: `https://doi.org/10.12345/archive.2025.001`

### DOI in Exports

When you export records, any existing DOIs are automatically included in the appropriate metadata element for each format:

| Format | DOI Location in Export |
|--------|----------------------|
| **EAD3** | `<otherrecordid localtype="doi">` |
| **RIC-O** | `rico:Identifier` with type DOI |
| **LIDO** | `<objectPublishedID lido:type="doi">` |
| **MARC21** | Field `024` with `$2=doi` |
| **BIBFRAME** | `bf:identifiedBy` → `bf:Doi` |
| **VRA Core** | `<refid type="doi">` |
| **PBCore** | `<pbcoreIdentifier source="DOI">` |
| **EBUCore** | `<identifier typeLabel="DOI">` |
| **PREMIS** | `<objectIdentifierType>DOI` |

### Example: DOI in EAD3

```xml
<ead xmlns="http://ead3.archivists.org/schema/">
  <control>
    <recordid>F001</recordid>
    <otherrecordid localtype="doi">10.12345/archive.2025.001</otherrecordid>
  </control>
  <!-- ... -->
</ead>
```

### Example: DOI in RIC-O

```json
{
  "@type": "rico:RecordSet",
  "rico:identifier": [
    {
      "@type": "rico:Identifier",
      "rico:textualValue": "F001"
    },
    {
      "@type": "rico:Identifier",
      "rico:identifierType": "DOI",
      "rico:textualValue": "10.12345/archive.2025.001"
    }
  ],
  "owl:sameAs": {"@id": "https://doi.org/10.12345/archive.2025.001"}
}
```

### Example: DOI in MARC21

```xml
<datafield tag="024" ind1="7" ind2=" ">
  <subfield code="a">10.12345/archive.2025.001</subfield>
  <subfield code="2">doi</subfield>
</datafield>
```

### DOI Handling in Exports

Any existing DOIs are included automatically in every export - there are no extra
flags to enable this. To mint DOIs for records that do not yet have one, use the
DOI Management command (`php artisan ahg:doi-mint`) before running the export.

### CLI Examples with DOI

**Export records (existing DOIs are embedded automatically):**
```bash
php artisan ahg:metadata-export --format=ead3 --repository=my-repository --output=/exports/
```

**Mint a DOI first, then export the record:**
```bash
# Mint by information-object ID (ahg:doi-mint --repository takes a numeric ID)
php artisan ahg:doi-mint --object-id=456
# Export that record by its slug
php artisan ahg:metadata-export --format=rico --slug=my-fonds --output=/exports/
```

### Benefits of DOI in Exports

- **Persistent citation** - Recipients can cite your records with a permanent link
- **Interoperability** - DOIs are recognized by academic databases and discovery systems
- **Linked data** - DOIs provide a stable URI for semantic web applications
- **Tracking** - DataCite provides usage statistics for your DOIs

### Requirements

- DOIs are managed via the **DOI Management** plugin
- DOI minting requires DataCite credentials configured in Admin > DOI Settings
- Existing DOIs are included automatically; no configuration needed

---

## Scheduling Automated Exports

### Cron Job Examples

Set up regular exports for data synchronization:

**Weekly EAD3 export (Sundays at 2am):**
```bash
0 2 * * 0 cd /usr/share/nginx/heratio && php artisan ahg:metadata-export --format=ead3 --repository=my-repository --output=/exports/ead3 >> storage/logs/ead3-export.log 2>&1
```

**Daily PREMIS export for preservation (4am):**
```bash
0 4 * * * cd /usr/share/nginx/heratio && php artisan ahg:metadata-export --format=premis --output=/exports/premis >> storage/logs/premis-export.log 2>&1
```

**Monthly all-format export (1st of month, 3am):**
```bash
0 3 1 * * cd /usr/share/nginx/heratio && php artisan ahg:metadata-export --format=all --repository=my-repository --output=/exports/monthly >> storage/logs/monthly-export.log 2>&1
```

---

## Tips for Successful Exports

### Before Exporting

- Know what format your recipient needs
- Check if they need hierarchical (children) data
- Verify access permissions on records
- Test with a single record first

### For Archives (EAD3, RIC-O)

- EAD3 is best for traditional finding aid systems
- RIC-O is best for linked data / semantic web projects
- Include children for complete hierarchies

### For Libraries (MARC21, BIBFRAME)

- MARC21 for traditional ILS systems
- BIBFRAME for modern linked data catalogs
- Check field mappings match your catalog's needs

### For Museums (LIDO)

- Include digital objects for image metadata
- LIDO is required for Europeana submission
- Check object type mappings

### For Preservation (PREMIS)

- Include digital objects for full preservation metadata
- PREMIS captures fixity, events, and rights
- Essential for AIP/SIP package creation

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Export is empty | Check the slug/repository ID is correct |
| Missing fields | Some fields may not map to the target format |
| File won't validate | Check the exported XML with an online validator |
| JSON-LD won't parse | Verify UTF-8 encoding |
| Export too slow | Use smaller batches or schedule overnight |
| Permission denied | Check output directory is writable |

---

## Finding System Info

View available export formats and plugin status:

1. Go to **Admin > AHG Settings > System Info**
2. Scroll to **GLAM Metadata Export Formats** section
3. View all 9 formats with status indicators

View cron job examples:

1. Go to **Admin > AHG Settings > Cron Jobs**
2. Scroll to **Metadata Export** section
3. Copy example commands for scheduling

---

*For technical support, contact your system administrator or The Archive and Heritage Group.*
