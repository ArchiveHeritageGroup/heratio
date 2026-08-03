> Heratio Help Center article. Category: Import/Export.

# Metadata Extraction

## User Guide

Automatically extract embedded metadata from uploaded digital objects and populate archival description fields.

---

## Overview
<div style="overflow-x:auto;margin:1rem 0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 495 196" style="max-width:100%;height:auto;font-family:ui-monospace,Menlo,Consolas,monospace"><rect x="0.5" y="0.5" width="494" height="195" rx="8" fill="#f7faf9" stroke="#d8e6e3"/><line x1="13.6" y1="18.0" x2="17.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="18.0" x2="13.6" y2="26.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="18.0" x2="24.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="18.0" x2="31.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="18.0" x2="38.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="18.0" x2="46.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="18.0" x2="53.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="18.0" x2="60.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="18.0" x2="67.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="18.0" x2="74.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="18.0" x2="82.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="18.0" x2="89.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="18.0" x2="96.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="18.0" x2="103.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="18.0" x2="110.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="18.0" x2="118.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="18.0" x2="125.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="18.0" x2="132.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="18.0" x2="139.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="18.0" x2="146.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="18.0" x2="154.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="18.0" x2="161.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="18.0" x2="168.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="18.0" x2="175.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="18.0" x2="182.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="18.0" x2="190.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="18.0" x2="197.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="18.0" x2="204.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="18.0" x2="211.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="18.0" x2="218.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="18.0" x2="226.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="18.0" x2="233.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="18.0" x2="240.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="18.0" x2="247.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="18.0" x2="254.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="18.0" x2="262.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="18.0" x2="269.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="18.0" x2="276.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="18.0" x2="283.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="18.0" x2="290.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="18.0" x2="298.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="18.0" x2="305.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="18.0" x2="312.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="18.0" x2="319.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="18.0" x2="326.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="18.0" x2="334.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="18.0" x2="341.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="18.0" x2="348.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="348.4" y1="18.0" x2="355.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="18.0" x2="362.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="362.8" y1="18.0" x2="370.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="370.0" y1="18.0" x2="377.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="377.2" y1="18.0" x2="384.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="384.4" y1="18.0" x2="391.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="391.6" y1="18.0" x2="398.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="398.8" y1="18.0" x2="406.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="406.0" y1="18.0" x2="413.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="413.2" y1="18.0" x2="420.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="420.4" y1="18.0" x2="427.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="427.6" y1="18.0" x2="434.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="434.8" y1="18.0" x2="442.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="442.0" y1="18.0" x2="449.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="449.2" y1="18.0" x2="456.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="456.4" y1="18.0" x2="460.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="26.0" x2="13.6" y2="42.0" stroke="#10373E" stroke-width="1.3"/><line x1="467.2" y1="26.0" x2="467.2" y2="42.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="50.0" x2="17.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="42.0" x2="13.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="50.0" x2="13.6" y2="58.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="50.0" x2="24.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="50.0" x2="31.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="50.0" x2="38.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="50.0" x2="46.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="50.0" x2="53.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="50.0" x2="60.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="50.0" x2="67.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="50.0" x2="74.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="50.0" x2="82.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="50.0" x2="89.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="50.0" x2="96.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="50.0" x2="103.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="50.0" x2="110.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="50.0" x2="118.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="50.0" x2="125.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="50.0" x2="132.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="50.0" x2="139.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="50.0" x2="146.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="50.0" x2="154.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="50.0" x2="161.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="50.0" x2="168.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="50.0" x2="175.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="50.0" x2="182.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="50.0" x2="190.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="50.0" x2="197.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="50.0" x2="204.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="50.0" x2="211.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="50.0" x2="218.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="50.0" x2="226.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="50.0" x2="233.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="50.0" x2="240.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="50.0" x2="247.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="50.0" x2="254.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="50.0" x2="262.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="50.0" x2="269.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="50.0" x2="276.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="50.0" x2="283.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="50.0" x2="290.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="50.0" x2="298.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="50.0" x2="305.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="50.0" x2="312.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="50.0" x2="319.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="50.0" x2="326.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="50.0" x2="334.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="50.0" x2="341.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="50.0" x2="348.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="348.4" y1="50.0" x2="355.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="50.0" x2="362.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="362.8" y1="50.0" x2="370.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="370.0" y1="50.0" x2="377.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="377.2" y1="50.0" x2="384.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="384.4" y1="50.0" x2="391.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="391.6" y1="50.0" x2="398.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="398.8" y1="50.0" x2="406.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="406.0" y1="50.0" x2="413.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="413.2" y1="50.0" x2="420.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="420.4" y1="50.0" x2="427.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="427.6" y1="50.0" x2="434.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="434.8" y1="50.0" x2="442.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="442.0" y1="50.0" x2="449.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="449.2" y1="50.0" x2="456.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="456.4" y1="50.0" x2="460.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="460.0" y1="50.0" x2="460.0" y2="58.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="58.0" x2="13.6" y2="74.0" stroke="#10373E" stroke-width="1.3"/><line x1="460.0" y1="58.0" x2="460.0" y2="74.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="74.0" x2="13.6" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="460.0" y1="74.0" x2="460.0" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="90.0" x2="13.6" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="90.0" x2="56.8" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="90.0" x2="172.0" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="294.4" y1="90.0" x2="294.4" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="409.6" y1="90.0" x2="409.6" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="460.0" y1="90.0" x2="460.0" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="106.0" x2="13.6" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="460.0" y1="106.0" x2="460.0" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="122.0" x2="13.6" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="130.0" x2="103.6" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="130.0" x2="110.8" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="130.0" x2="233.2" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="130.0" x2="240.4" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="348.4" y1="130.0" x2="355.6" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="130.0" x2="362.8" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="460.0" y1="122.0" x2="460.0" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="138.0" x2="13.6" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="481.6" y1="138.0" x2="481.6" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="154.0" x2="13.6" y2="170.0" stroke="#10373E" stroke-width="1.3"/><line x1="460.0" y1="154.0" x2="460.0" y2="170.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="178.0" x2="17.2" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="170.0" x2="13.6" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="178.0" x2="24.4" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="178.0" x2="31.6" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="178.0" x2="38.8" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="178.0" x2="46.0" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="178.0" x2="53.2" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="178.0" x2="60.4" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="178.0" x2="67.6" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="178.0" x2="74.8" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="178.0" x2="82.0" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="178.0" x2="89.2" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="178.0" x2="96.4" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="178.0" x2="103.6" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="178.0" x2="110.8" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="178.0" x2="118.0" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="178.0" x2="125.2" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="178.0" x2="132.4" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="178.0" x2="139.6" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="178.0" x2="146.8" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="178.0" x2="154.0" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="178.0" x2="161.2" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="178.0" x2="168.4" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="178.0" x2="175.6" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="178.0" x2="182.8" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="178.0" x2="190.0" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="178.0" x2="197.2" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="178.0" x2="204.4" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="178.0" x2="211.6" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="178.0" x2="218.8" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="178.0" x2="226.0" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="178.0" x2="233.2" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="178.0" x2="240.4" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="178.0" x2="247.6" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="178.0" x2="254.8" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="178.0" x2="262.0" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="178.0" x2="269.2" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="178.0" x2="276.4" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="178.0" x2="283.6" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="178.0" x2="290.8" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="178.0" x2="298.0" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="178.0" x2="305.2" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="178.0" x2="312.4" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="178.0" x2="319.6" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="178.0" x2="326.8" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="178.0" x2="334.0" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="178.0" x2="341.2" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="178.0" x2="348.4" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="348.4" y1="178.0" x2="355.6" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="178.0" x2="362.8" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="362.8" y1="178.0" x2="370.0" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="370.0" y1="178.0" x2="377.2" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="377.2" y1="178.0" x2="384.4" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="384.4" y1="178.0" x2="391.6" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="391.6" y1="178.0" x2="398.8" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="398.8" y1="178.0" x2="406.0" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="406.0" y1="178.0" x2="413.2" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="413.2" y1="178.0" x2="420.4" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="420.4" y1="178.0" x2="427.6" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="427.6" y1="178.0" x2="434.8" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="434.8" y1="178.0" x2="442.0" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="442.0" y1="178.0" x2="449.2" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="449.2" y1="178.0" x2="456.4" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="456.4" y1="178.0" x2="460.0" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="460.0" y1="170.0" x2="460.0" y2="178.0" stroke="#10373E" stroke-width="1.3"/><path d="M168.0 109.0 L172.0 116.0 L176.0 109.0 Z" fill="#10373E"/><path d="M105.8 126.0 L112.8 130.0 L105.8 134.0 Z" fill="#10373E"/><path d="M235.4 126.0 L242.4 130.0 L235.4 134.0 Z" fill="#10373E"/><path d="M357.8 126.0 L364.8 130.0 L357.8 134.0 Z" fill="#10373E"/><text x="154.0" y="38.0" font-size="11.5" fill="#10373E">METADATA</text><text x="218.8" y="38.0" font-size="11.5" fill="#10373E">EXTRACTION</text><text x="38.8" y="86.0" font-size="11.5" fill="#10373E">UPLOAD</text><text x="154.0" y="86.0" font-size="11.5" fill="#10373E">EXTRACT</text><text x="283.6" y="86.0" font-size="11.5" fill="#10373E">MAP</text><text x="391.6" y="86.0" font-size="11.5" fill="#10373E">APPLY</text><text x="53.2" y="118.0" font-size="11.5" fill="#10373E">v</text><text x="290.8" y="118.0" font-size="11.5" fill="#10373E">v</text><text x="406.0" y="118.0" font-size="11.5" fill="#10373E">v</text><text x="38.8" y="134.0" font-size="11.5" fill="#10373E">File</text><text x="139.6" y="134.0" font-size="11.5" fill="#10373E">Read</text><text x="175.6" y="134.0" font-size="11.5" fill="#10373E">EXIF</text><text x="269.2" y="134.0" font-size="11.5" fill="#10373E">Match</text><text x="312.4" y="134.0" font-size="11.5" fill="#10373E">to</text><text x="384.4" y="134.0" font-size="11.5" fill="#10373E">Update</text><text x="38.8" y="150.0" font-size="11.5" fill="#10373E">Added</text><text x="139.6" y="150.0" font-size="11.5" fill="#10373E">IPTC/XMP</text><text x="269.2" y="150.0" font-size="11.5" fill="#10373E">Heratio</text><text x="326.8" y="150.0" font-size="11.5" fill="#10373E">Fields</text><text x="406.0" y="150.0" font-size="11.5" fill="#10373E">Record</text></svg></div>

---

## Supported File Types
**Supported Formats**

- IMAGES        - JPEG, PNG, TIFF, WebP, GIF, BMP
- (EXIF, IPTC, XMP metadata)
- DOCUMENTS     - PDF (title, author, keywords)
- - DOCX, XLSX, PPTX (Open XML)
- - DOC, XLS, PPT (Legacy Office)
- VIDEO         - MP4, WebM, MKV, MOV, AVI, OGG
- (duration, resolution, codec)
- AUDIO         - MP3, WAV, FLAC, OGG, AAC, M4A
- (ID3 tags, duration, bitrate)

---

## How It Works

### Automatic Extraction

When you upload a digital object, the plugin automatically:

1. Detects the file type
2. Extracts embedded metadata
3. Maps metadata to Heratio fields
4. Populates the archival description

<div style="overflow-x:auto;margin:1rem 0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 394 436" style="max-width:100%;height:auto;font-family:ui-monospace,Menlo,Consolas,monospace"><rect x="0.5" y="0.5" width="393" height="435" rx="8" fill="#f7faf9" stroke="#d8e6e3"/><line x1="56.8" y1="26.0" x2="56.8" y2="42.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="66.0" x2="31.6" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="66.0" x2="28.0" y2="74.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="66.0" x2="38.8" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="66.0" x2="46.0" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="66.0" x2="53.2" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="66.0" x2="60.4" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="66.0" x2="67.6" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="66.0" x2="74.8" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="66.0" x2="82.0" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="66.0" x2="89.2" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="66.0" x2="96.4" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="66.0" x2="103.6" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="66.0" x2="110.8" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="66.0" x2="118.0" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="66.0" x2="125.2" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="66.0" x2="132.4" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="66.0" x2="139.6" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="66.0" x2="146.8" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="66.0" x2="154.0" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="66.0" x2="161.2" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="66.0" x2="168.4" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="66.0" x2="172.0" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="66.0" x2="172.0" y2="74.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="74.0" x2="28.0" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="74.0" x2="172.0" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="98.0" x2="31.6" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="90.0" x2="28.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="98.0" x2="38.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="98.0" x2="46.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="98.0" x2="53.2" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="98.0" x2="60.4" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="98.0" x2="67.6" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="98.0" x2="74.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="98.0" x2="82.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="98.0" x2="89.2" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="98.0" x2="96.4" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="98.0" x2="103.6" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="98.0" x2="110.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="98.0" x2="118.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="98.0" x2="125.2" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="98.0" x2="132.4" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="98.0" x2="139.6" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="98.0" x2="146.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="98.0" x2="154.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="98.0" x2="161.2" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="98.0" x2="168.4" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="98.0" x2="172.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="90.0" x2="172.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="106.0" x2="56.8" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="130.0" x2="60.4" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="122.0" x2="56.8" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="130.0" x2="56.8" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="130.0" x2="67.6" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="130.0" x2="74.8" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="130.0" x2="82.0" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="130.0" x2="154.0" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="130.0" x2="161.2" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="130.0" x2="168.4" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="138.0" x2="56.8" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="162.0" x2="60.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="154.0" x2="56.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="162.0" x2="56.8" y2="170.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="162.0" x2="67.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="162.0" x2="74.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="162.0" x2="82.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="162.0" x2="139.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="162.0" x2="146.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="162.0" x2="154.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="162.0" x2="161.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="162.0" x2="168.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="170.0" x2="56.8" y2="186.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="194.0" x2="60.4" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="186.0" x2="56.8" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="194.0" x2="56.8" y2="202.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="194.0" x2="67.6" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="194.0" x2="74.8" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="194.0" x2="82.0" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="194.0" x2="161.2" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="194.0" x2="168.4" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="202.0" x2="56.8" y2="218.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="226.0" x2="60.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="218.0" x2="56.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="226.0" x2="56.8" y2="234.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="226.0" x2="67.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="226.0" x2="74.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="226.0" x2="82.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="226.0" x2="154.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="226.0" x2="161.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="226.0" x2="168.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="234.0" x2="56.8" y2="250.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="258.0" x2="60.4" y2="258.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="250.0" x2="56.8" y2="258.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="258.0" x2="56.8" y2="266.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="258.0" x2="67.6" y2="258.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="258.0" x2="74.8" y2="258.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="258.0" x2="82.0" y2="258.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="258.0" x2="154.0" y2="258.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="258.0" x2="161.2" y2="258.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="258.0" x2="168.4" y2="258.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="266.0" x2="56.8" y2="282.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="306.0" x2="31.6" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="306.0" x2="28.0" y2="314.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="306.0" x2="38.8" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="306.0" x2="46.0" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="306.0" x2="53.2" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="306.0" x2="60.4" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="306.0" x2="67.6" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="306.0" x2="74.8" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="306.0" x2="82.0" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="306.0" x2="89.2" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="306.0" x2="96.4" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="306.0" x2="103.6" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="306.0" x2="110.8" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="306.0" x2="118.0" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="306.0" x2="125.2" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="306.0" x2="132.4" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="306.0" x2="139.6" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="306.0" x2="146.8" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="306.0" x2="154.0" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="306.0" x2="161.2" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="306.0" x2="168.4" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="306.0" x2="172.0" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="314.0" x2="28.0" y2="330.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="314.0" x2="193.6" y2="330.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="338.0" x2="31.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="330.0" x2="28.0" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="338.0" x2="38.8" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="338.0" x2="46.0" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="338.0" x2="53.2" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="338.0" x2="60.4" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="338.0" x2="67.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="338.0" x2="74.8" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="338.0" x2="82.0" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="338.0" x2="89.2" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="338.0" x2="96.4" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="338.0" x2="103.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="338.0" x2="110.8" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="338.0" x2="118.0" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="338.0" x2="125.2" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="338.0" x2="132.4" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="338.0" x2="139.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="338.0" x2="146.8" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="338.0" x2="154.0" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="338.0" x2="161.2" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="338.0" x2="168.4" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="338.0" x2="172.0" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="346.0" x2="56.8" y2="362.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="386.0" x2="31.6" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="386.0" x2="28.0" y2="394.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="386.0" x2="38.8" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="386.0" x2="46.0" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="386.0" x2="53.2" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="386.0" x2="60.4" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="386.0" x2="67.6" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="386.0" x2="74.8" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="386.0" x2="82.0" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="386.0" x2="89.2" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="386.0" x2="96.4" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="386.0" x2="103.6" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="386.0" x2="110.8" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="386.0" x2="118.0" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="386.0" x2="125.2" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="386.0" x2="132.4" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="386.0" x2="139.6" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="386.0" x2="146.8" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="386.0" x2="154.0" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="386.0" x2="161.2" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="386.0" x2="168.4" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="386.0" x2="172.0" y2="386.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="386.0" x2="172.0" y2="394.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="394.0" x2="28.0" y2="410.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="394.0" x2="172.0" y2="410.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="418.0" x2="31.6" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="410.0" x2="28.0" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="418.0" x2="38.8" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="418.0" x2="46.0" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="418.0" x2="53.2" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="418.0" x2="60.4" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="418.0" x2="67.6" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="418.0" x2="74.8" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="418.0" x2="82.0" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="418.0" x2="89.2" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="418.0" x2="96.4" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="418.0" x2="103.6" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="418.0" x2="110.8" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="418.0" x2="118.0" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="418.0" x2="125.2" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="418.0" x2="132.4" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="418.0" x2="139.6" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="418.0" x2="146.8" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="418.0" x2="154.0" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="418.0" x2="161.2" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="418.0" x2="168.4" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="418.0" x2="172.0" y2="418.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="410.0" x2="172.0" y2="418.0" stroke="#10373E" stroke-width="1.3"/><path d="M77.0 126.0 L84.0 130.0 L77.0 134.0 Z" fill="#10373E"/><path d="M163.4 126.0 L170.4 130.0 L163.4 134.0 Z" fill="#10373E"/><path d="M77.0 158.0 L84.0 162.0 L77.0 166.0 Z" fill="#10373E"/><path d="M163.4 158.0 L170.4 162.0 L163.4 166.0 Z" fill="#10373E"/><path d="M77.0 190.0 L84.0 194.0 L77.0 198.0 Z" fill="#10373E"/><path d="M163.4 190.0 L170.4 194.0 L163.4 198.0 Z" fill="#10373E"/><path d="M77.0 222.0 L84.0 226.0 L77.0 230.0 Z" fill="#10373E"/><path d="M163.4 222.0 L170.4 226.0 L163.4 230.0 Z" fill="#10373E"/><path d="M77.0 254.0 L84.0 258.0 L77.0 262.0 Z" fill="#10373E"/><path d="M163.4 254.0 L170.4 258.0 L163.4 262.0 Z" fill="#10373E"/><text x="24.4" y="22.0" font-size="11.5" fill="#10373E">Upload</text><text x="74.8" y="22.0" font-size="11.5" fill="#10373E">File</text><text x="53.2" y="54.0" font-size="11.5" fill="#10373E">v</text><text x="38.8" y="86.0" font-size="11.5" fill="#10373E">Detect</text><text x="89.2" y="86.0" font-size="11.5" fill="#10373E">File</text><text x="125.2" y="86.0" font-size="11.5" fill="#10373E">Type</text><text x="96.4" y="134.0" font-size="11.5" fill="#10373E">Image?</text><text x="182.8" y="134.0" font-size="11.5" fill="#10373E">Extract</text><text x="240.4" y="134.0" font-size="11.5" fill="#10373E">EXIF/IPTC/XMP</text><text x="96.4" y="166.0" font-size="11.5" fill="#10373E">PDF?</text><text x="182.8" y="166.0" font-size="11.5" fill="#10373E">Extract</text><text x="240.4" y="166.0" font-size="11.5" fill="#10373E">Document</text><text x="305.2" y="166.0" font-size="11.5" fill="#10373E">Info</text><text x="96.4" y="198.0" font-size="11.5" fill="#10373E">Office?</text><text x="182.8" y="198.0" font-size="11.5" fill="#10373E">Extract</text><text x="240.4" y="198.0" font-size="11.5" fill="#10373E">Open</text><text x="276.4" y="198.0" font-size="11.5" fill="#10373E">XML</text><text x="305.2" y="198.0" font-size="11.5" fill="#10373E">Properties</text><text x="96.4" y="230.0" font-size="11.5" fill="#10373E">Video?</text><text x="182.8" y="230.0" font-size="11.5" fill="#10373E">Extract</text><text x="240.4" y="230.0" font-size="11.5" fill="#10373E">Media</text><text x="283.6" y="230.0" font-size="11.5" fill="#10373E">Info</text><text x="319.6" y="230.0" font-size="11.5" fill="#10373E">(FFprobe)</text><text x="96.4" y="262.0" font-size="11.5" fill="#10373E">Audio?</text><text x="182.8" y="262.0" font-size="11.5" fill="#10373E">Extract</text><text x="240.4" y="262.0" font-size="11.5" fill="#10373E">ID3</text><text x="269.2" y="262.0" font-size="11.5" fill="#10373E">Tags</text><text x="53.2" y="294.0" font-size="11.5" fill="#10373E">v</text><text x="38.8" y="326.0" font-size="11.5" fill="#10373E">Map</text><text x="67.6" y="326.0" font-size="11.5" fill="#10373E">to</text><text x="89.2" y="326.0" font-size="11.5" fill="#10373E">Heratio</text><text x="146.8" y="326.0" font-size="11.5" fill="#10373E">Fields</text><text x="53.2" y="374.0" font-size="11.5" fill="#10373E">v</text><text x="38.8" y="406.0" font-size="11.5" fill="#10373E">Update</text><text x="89.2" y="406.0" font-size="11.5" fill="#10373E">Record</text></svg></div>

---

## What Gets Extracted

### Images (EXIF/IPTC/XMP)
- IMAGE METADATA
- DESCRIPTIVE
- - Title / Object Name
- - Description / Caption
- - Keywords / Tags
- - Creator / Photographer
- - Copyright Notice
- - Date Taken
- LOCATION
- - GPS Coordinates
- - City, State, Country
- - Altitude
- TECHNICAL
- - Camera Make/Model
- - Exposure Settings
- - Image Dimensions
- - Color Space

### PDF Documents
- PDF METADATA
- - Title
- - Author
- - Subject
- - Keywords
- - Creator Application
- - Producer
- - Creation Date
- - Modification Date
- - Page Count

### Office Documents (DOCX, XLSX, PPTX)
- OFFICE METADATA
- CORE PROPERTIES
- - Title
- - Creator / Author
- - Subject
- - Description
- - Keywords
- - Category
- APPLICATION PROPERTIES
- - Application Name & Version
- - Company
- - Manager
- - Total Editing Time
- - Page/Word/Character Counts
- - Slide Count (PPTX)
- CUSTOM PROPERTIES
- - Any custom metadata fields defined in the document

### Video Files
- VIDEO METADATA
- GENERAL
- - Title
- - Artist/Creator
- - Date Created
- - Comment
- TECHNICAL
- - Duration (HH:MM:SS)
- - Resolution (width x height)
- - Frame Rate (fps)
- - Video Codec
- - Audio Codec
- - Bitrate
- - Container Format

### Audio Files
- AUDIO METADATA
- ID3 TAGS
- - Title
- - Artist
- - Album
- - Year
- - Genre
- - Track Number
- - Composer
- - Publisher
- - Copyright
- TECHNICAL
- - Duration
- - Bitrate
- - Sample Rate
- - Channels
- - Audio Codec

---

## Heratio Field Mapping

Extracted metadata is mapped to Heratio's archival description fields:

| Extracted Field | Heratio Field |
|---|---|
| Title | Title (if empty) |
| Description/Caption | Scope and Content |
| Creator/Artist | Name Access Point (Creator) |
| Keywords | Subject Access Points |
| Date Created | Event Date (Creation) |
| Copyright | Access Conditions |
| GPS Coordinates | Scope and Content (appended) |
| Technical Summary | Physical Characteristics |

---

## Configuration Settings

### How to Access Settings
<div style="overflow-x:auto;margin:1rem 0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 423 356" style="max-width:100%;height:auto;font-family:ui-monospace,Menlo,Consolas,monospace"><rect x="0.5" y="0.5" width="422" height="355" rx="8" fill="#f7faf9" stroke="#d8e6e3"/><line x1="56.8" y1="26.0" x2="56.8" y2="42.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="74.0" x2="56.8" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="122.0" x2="56.8" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="162.0" x2="182.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="162.0" x2="190.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="162.0" x2="197.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="162.0" x2="204.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="162.0" x2="211.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="162.0" x2="218.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="162.0" x2="226.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="162.0" x2="233.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="162.0" x2="240.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="162.0" x2="247.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="162.0" x2="254.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="162.0" x2="262.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="162.0" x2="269.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="162.0" x2="276.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="162.0" x2="283.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="162.0" x2="290.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="162.0" x2="298.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="162.0" x2="305.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="162.0" x2="312.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="162.0" x2="319.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="162.0" x2="326.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="162.0" x2="334.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="162.0" x2="341.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="162.0" x2="348.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="348.4" y1="162.0" x2="355.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="162.0" x2="362.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="362.8" y1="162.0" x2="370.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="370.0" y1="162.0" x2="377.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="377.2" y1="162.0" x2="384.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="384.4" y1="162.0" x2="391.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="391.6" y1="162.0" x2="398.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="398.8" y1="162.0" x2="406.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="406.0" y1="162.0" x2="409.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="409.6" y1="162.0" x2="409.6" y2="170.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="170.0" x2="56.8" y2="186.0" stroke="#10373E" stroke-width="1.3"/><line x1="409.6" y1="170.0" x2="409.6" y2="186.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="194.0" x2="60.4" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="186.0" x2="56.8" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="194.0" x2="56.8" y2="202.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="194.0" x2="67.6" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="194.0" x2="74.8" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="194.0" x2="82.0" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="409.6" y1="186.0" x2="409.6" y2="202.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="202.0" x2="56.8" y2="218.0" stroke="#10373E" stroke-width="1.3"/><line x1="409.6" y1="202.0" x2="409.6" y2="218.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="226.0" x2="60.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="218.0" x2="56.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="226.0" x2="56.8" y2="234.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="226.0" x2="67.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="226.0" x2="74.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="226.0" x2="82.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="409.6" y1="218.0" x2="409.6" y2="234.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="234.0" x2="56.8" y2="250.0" stroke="#10373E" stroke-width="1.3"/><line x1="409.6" y1="234.0" x2="409.6" y2="250.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="250.0" x2="56.8" y2="266.0" stroke="#10373E" stroke-width="1.3"/><line x1="409.6" y1="250.0" x2="409.6" y2="266.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="274.0" x2="60.4" y2="274.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="266.0" x2="56.8" y2="274.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="274.0" x2="56.8" y2="282.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="274.0" x2="67.6" y2="274.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="274.0" x2="74.8" y2="274.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="274.0" x2="82.0" y2="274.0" stroke="#10373E" stroke-width="1.3"/><line x1="409.6" y1="266.0" x2="409.6" y2="282.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="282.0" x2="56.8" y2="298.0" stroke="#10373E" stroke-width="1.3"/><line x1="409.6" y1="282.0" x2="409.6" y2="298.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="306.0" x2="60.4" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="298.0" x2="56.8" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="306.0" x2="67.6" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="306.0" x2="74.8" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="306.0" x2="82.0" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="409.6" y1="298.0" x2="409.6" y2="314.0" stroke="#10373E" stroke-width="1.3"/><line x1="409.6" y1="314.0" x2="409.6" y2="330.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="338.0" x2="17.2" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="338.0" x2="24.4" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="338.0" x2="31.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="338.0" x2="38.8" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="338.0" x2="46.0" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="338.0" x2="53.2" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="338.0" x2="60.4" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="338.0" x2="67.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="338.0" x2="74.8" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="338.0" x2="82.0" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="338.0" x2="89.2" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="338.0" x2="96.4" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="338.0" x2="103.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="338.0" x2="110.8" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="338.0" x2="118.0" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="338.0" x2="125.2" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="338.0" x2="132.4" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="338.0" x2="139.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="338.0" x2="146.8" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="338.0" x2="154.0" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="338.0" x2="161.2" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="338.0" x2="168.4" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="338.0" x2="175.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="338.0" x2="182.8" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="338.0" x2="190.0" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="338.0" x2="197.2" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="338.0" x2="204.4" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="338.0" x2="211.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="338.0" x2="218.8" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="338.0" x2="226.0" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="338.0" x2="233.2" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="338.0" x2="240.4" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="338.0" x2="247.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="338.0" x2="254.8" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="338.0" x2="262.0" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="338.0" x2="269.2" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="338.0" x2="276.4" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="338.0" x2="283.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="338.0" x2="290.8" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="338.0" x2="298.0" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="338.0" x2="305.2" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="338.0" x2="312.4" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="338.0" x2="319.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="338.0" x2="326.8" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="338.0" x2="334.0" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="338.0" x2="341.2" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="338.0" x2="348.4" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="348.4" y1="338.0" x2="355.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="338.0" x2="362.8" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="362.8" y1="338.0" x2="370.0" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="370.0" y1="338.0" x2="377.2" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="377.2" y1="338.0" x2="384.4" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="384.4" y1="338.0" x2="391.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="391.6" y1="338.0" x2="398.8" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="398.8" y1="338.0" x2="406.0" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="406.0" y1="338.0" x2="409.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="409.6" y1="330.0" x2="409.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><path d="M52.8 93.0 L56.8 100.0 L60.8 93.0 Z" fill="#10373E"/><path d="M77.0 190.0 L84.0 194.0 L77.0 198.0 Z" fill="#10373E"/><path d="M77.0 222.0 L84.0 226.0 L77.0 230.0 Z" fill="#10373E"/><path d="M77.0 270.0 L84.0 274.0 L77.0 278.0 Z" fill="#10373E"/><path d="M77.0 302.0 L84.0 306.0 L77.0 310.0 Z" fill="#10373E"/><text x="24.4" y="22.0" font-size="11.5" fill="#10373E">Main</text><text x="60.4" y="22.0" font-size="11.5" fill="#10373E">Menu</text><text x="53.2" y="54.0" font-size="11.5" fill="#10373E">v</text><text x="31.6" y="70.0" font-size="11.5" fill="#10373E">Admin</text><text x="31.6" y="118.0" font-size="11.5" fill="#10373E">AHG</text><text x="60.4" y="118.0" font-size="11.5" fill="#10373E">Settings</text><text x="53.2" y="150.0" font-size="11.5" fill="#10373E">v</text><text x="31.6" y="166.0" font-size="11.5" fill="#10373E">Metadata</text><text x="96.4" y="166.0" font-size="11.5" fill="#10373E">Extraction</text><text x="96.4" y="198.0" font-size="11.5" fill="#10373E">Enable/Disable</text><text x="204.4" y="198.0" font-size="11.5" fill="#10373E">Extraction</text><text x="96.4" y="230.0" font-size="11.5" fill="#10373E">Select</text><text x="146.8" y="230.0" font-size="11.5" fill="#10373E">Metadata</text><text x="211.6" y="230.0" font-size="11.5" fill="#10373E">Types</text><text x="96.4" y="246.0" font-size="11.5" fill="#10373E">(EXIF,</text><text x="146.8" y="246.0" font-size="11.5" fill="#10373E">IPTC,</text><text x="190.0" y="246.0" font-size="11.5" fill="#10373E">XMP)</text><text x="96.4" y="278.0" font-size="11.5" fill="#10373E">Field</text><text x="139.6" y="278.0" font-size="11.5" fill="#10373E">Mapping</text><text x="197.2" y="278.0" font-size="11.5" fill="#10373E">Options</text><text x="96.4" y="310.0" font-size="11.5" fill="#10373E">Technical</text><text x="168.4" y="310.0" font-size="11.5" fill="#10373E">Metadata</text><text x="233.2" y="310.0" font-size="11.5" fill="#10373E">Location</text></svg></div>

### Available Settings
- EXTRACTION SETTINGS
- [x] Enable metadata extraction
- METADATA TYPES
- [x] Extract EXIF data
- [x] Extract IPTC data
- [x] Extract XMP data
- FIELD BEHAVIOR
- [ ] Overwrite existing title
- [ ] Overwrite existing description
- [x] Auto-generate keywords from metadata
- [x] Extract GPS coordinates
- TECHNICAL METADATA
- [x] Add technical metadata summary
- Target field: [Physical Characteristics    v]
- [Save Settings]

### Setting Descriptions

| Setting | Default | Description |
|---------|---------|-------------|
| Enable extraction | On | Master switch for metadata extraction |
| Extract EXIF | On | Extract camera/technical data from images |
| Extract IPTC | On | Extract editorial metadata from images |
| Extract XMP | On | Extract Adobe XMP metadata |
| Overwrite title | Off | Replace existing title with extracted title |
| Overwrite description | Off | Replace existing description |
| Auto-generate keywords | On | Create subject access points from keywords |
| Extract GPS | On | Extract and store GPS coordinates |
| Add technical metadata | On | Add technical summary to record |
| Target field | Physical Characteristics | Where to store technical metadata |

---

## Viewing Extracted Metadata

After upload, extracted metadata appears in the record:

### Title and Description
- ARCHIVAL DESCRIPTION
- Title: [Extracted from metadata if record was untitled]
- Scope and Content:
- [Original description]
- [Extracted description/caption if available]

### Physical Characteristics (Technical Metadata)
- PHYSICAL CHARACTERISTICS
- === FILE INFO ===
- Name: photograph_001.jpg
- Size: 2.4 MB
- Type: image/jpeg
- === IMAGE ===
- Dimensions: 4032 x 3024 pixels
- Camera: Canon EOS 5D Mark IV
- Date: 2025-06-15
- === GPS ===
- Coordinates: -33.918861, 18.423300
- Altitude: 42m

### Access Points
- ACCESS POINTS
- Name Access Points:
- - John Smith (Creator)
- Subject Access Points:
- - Architecture
- - Historical Buildings
- - Cape Town

---

## Metadata Extraction Dashboard

### Accessing the Dashboard

Navigate to `/metadataExtraction` to access the metadata extraction management interface.

```
┌──────────────────────────────────────────────────────────────┐
│                METADATA EXTRACTION DASHBOARD                  │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│   [Status]  [Batch Extract]                                  │
│                                                              │
│   FILTERS                                                    │
│   MIME Type: [All types       v]                            │
│   Has Metadata: [All          v]   [Filter] [Clear]         │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│   ID  │ File Name   │ MIME Type  │ Size │ Record  │ Actions │
│───────┼─────────────┼────────────┼──────┼─────────┼─────────│
│   1   │ photo.jpg   │ image/jpeg │ 2MB  │ Title   │ [View]  │
│   2   │ doc.pdf     │ app/pdf    │ 1MB  │ Title   │ [View]  │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### Dashboard Features

| Feature | Description |
|---------|-------------|
| **MIME Type Filter** | Filter by file type (image, PDF, video, etc.) |
| **Extraction Status Filter** | Show only extracted/not extracted objects |
| **Batch Extract** | Process up to 50 unextracted objects at once |
| **Status Page** | View ExifTool status and extraction statistics |
| **View Metadata** | See all extracted fields for any object |

### Status Page

The status page (`/metadataExtraction/status`) shows:

```
┌──────────────────────────────────────────────────────────────┐
│                     SYSTEM STATUS                             │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│   ExifTool Status                                            │
│   ├── Installed: [✓ Yes]                                    │
│   └── Version: 12.70                                        │
│                                                              │
│   Extraction Statistics                                      │
│   ├── Total Digital Objects: 1,234                          │
│   ├── Objects with Metadata: 890 (72%)                      │
│   ├── Total Metadata Fields: 45,678                         │
│   └── Average Fields per Object: 51                         │
│                                                              │
│   MIME Type Breakdown                                        │
│   ├── image/jpeg: 800 [Supported ✓]                         │
│   ├── application/pdf: 300 [Supported ✓]                    │
│   └── video/mp4: 134 [Supported ✓]                          │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

---

## Manual Extraction

### From the Dashboard

1. Navigate to `/metadataExtraction`
2. Find the digital object in the list
3. Click the **Extract** button (download icon)
4. Metadata is extracted and saved automatically

### From the Detail View

1. Navigate to `/metadataExtraction/view/:id`
2. Click **Extract Metadata** button
3. View extracted metadata grouped by category (EXIF, IPTC, XMP, etc.)

### Batch Extraction

1. Navigate to `/metadataExtraction`
2. Click **Batch Extract** button
3. Up to 50 unextracted objects are processed
4. Repeat if more objects remain

---

## Viewing Extracted Metadata

### Metadata Detail View

Navigate to `/metadataExtraction/view/:id` to see all extracted metadata:

```
┌──────────────────────────────────────────────────────────────┐
│                    EXTRACTED METADATA                         │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│   Digital Object Details                                     │
│   ├── ID: 123                                               │
│   ├── File Name: photograph_001.jpg                         │
│   ├── MIME Type: image/jpeg                                 │
│   ├── Size: 2.4 MB                                          │
│   └── Parent Record: Smith Family Papers                    │
│                                                              │
│   [Extract Metadata]  [Delete Metadata]                      │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│   ▼ EXIF (32 fields)                                        │
│     ├── Make: Canon                                         │
│     ├── Model: EOS 5D Mark IV                               │
│     ├── ExposureTime: 1/250                                 │
│     └── ...                                                 │
│                                                              │
│   ▶ IPTC (8 fields)                                         │
│   ▶ XMP (12 fields)                                         │
│   ▶ File (5 fields)                                         │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

Metadata is organized into collapsible sections by source (EXIF, IPTC, XMP, File, etc.).

---

## Troubleshooting

### Common Issues

- Issue
- No metadata
- extracted
- Missing EXIF
- Video metadata
- not extracting
- ExifTool errors
- GPS not appearing
- Keywords not
- created

### Checking System Requirements

```bash
# Check PHP EXIF extension
php -m | grep exif

# Check ExifTool
which exiftool
exiftool -ver

# Check FFprobe (for video/audio)
which ffprobe
ffprobe -version
```

---

## Best Practices

### Before Upload
| DO | DON'T |
|---|---|
| Embed metadata in files | Strip metadata before upload |
| before uploading |  |
| Use standard metadata | Use proprietary metadata |
| formats (EXIF, IPTC, XMP) | formats |
| Include descriptive titles | Leave metadata fields empty |
| and keywords |  |
| Add copyright information | Assume copyright will be |
|  | added later |

### Setting Up
- Configure extraction settings before bulk uploads
- Test with sample files to verify field mapping
- Decide whether to overwrite existing fields

### Regular Use
- Review extracted metadata after upload
- Supplement with additional description as needed
- Check GPS coordinates for sensitive locations

---

## Privacy Considerations

### GPS Data
- GPS PRIVACY WARNING
- Photos from mobile devices often contain GPS coordinates.
- This can reveal:
- - Home addresses
- - Work locations
- - Travel patterns
- RECOMMENDATION: Review GPS data before publishing records.
- Disable GPS extraction if location data is sensitive.

### Personal Information
- Creator names may identify individuals
- Keywords may contain sensitive terms
- Document properties may reveal organization details

---

## Use Cases

### Photograph Collections
- Automatically capture photographer name
- Extract camera and exposure information
- Map GPS coordinates to locate image subjects
- Populate keywords from IPTC tags

### Document Archives
- Extract author and title information
- Capture creation and modification dates
- Identify creating application

### Audio/Visual Archives
- Record duration and technical specifications
- Extract title and artist information
- Document codec and quality settings

---

## Need Help?

Contact your system administrator if you experience issues with metadata extraction.

---

*Part of the Heratio AHG Framework*
