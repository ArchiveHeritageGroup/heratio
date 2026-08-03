> Heratio Help Center article. Category: Technical / Import/Export.

# Heratio — Migration Technical Reference

**Version:** 2.8.2
**Last Updated:** February 2026

---

## Overview

The Heratio migration transforms Heratio from a Symfony 1.x/Propel monolith into a standalone Laravel-based platform. The migration is incremental — a kill-switch toggles between modes, and both Symfony and Heratio can serve pages simultaneously. Zero base Heratio modifications required.

---

## Architecture Diagram

<div style="overflow-x:auto;margin:1rem 0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 718 372" style="max-width:100%;height:auto;font-family:ui-monospace,Menlo,Consolas,monospace"><rect x="0.5" y="0.5" width="717" height="371" rx="8" fill="#f7faf9" stroke="#d8e6e3"/><line x1="154.0" y1="50.0" x2="157.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="50.0" x2="161.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="50.0" x2="164.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="50.0" x2="168.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="50.0" x2="272.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="272.8" y1="50.0" x2="276.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="50.0" x2="280.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="280.0" y1="50.0" x2="283.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="348.4" y1="50.0" x2="352.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="352.0" y1="50.0" x2="355.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="50.0" x2="359.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="359.2" y1="50.0" x2="362.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="456.4" y1="50.0" x2="460.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="460.0" y1="50.0" x2="463.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="463.6" y1="50.0" x2="467.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="467.2" y1="50.0" x2="470.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="524.8" y1="58.0" x2="524.8" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="524.8" y1="66.0" x2="524.8" y2="74.0" stroke="#10373E" stroke-width="1.3"/><line x1="416.8" y1="82.0" x2="420.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="416.8" y1="82.0" x2="416.8" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="420.4" y1="82.0" x2="424.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="424.0" y1="82.0" x2="427.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="427.6" y1="82.0" x2="431.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="431.2" y1="82.0" x2="434.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="434.8" y1="82.0" x2="438.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="438.4" y1="82.0" x2="442.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="442.0" y1="82.0" x2="445.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="445.6" y1="82.0" x2="449.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="449.2" y1="82.0" x2="452.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="452.8" y1="82.0" x2="456.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="456.4" y1="82.0" x2="460.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="460.0" y1="82.0" x2="463.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="463.6" y1="82.0" x2="467.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="467.2" y1="82.0" x2="470.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="470.8" y1="82.0" x2="474.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="474.4" y1="82.0" x2="478.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="478.0" y1="82.0" x2="481.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="481.6" y1="82.0" x2="485.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="485.2" y1="82.0" x2="488.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="488.8" y1="82.0" x2="492.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="492.4" y1="82.0" x2="496.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="496.0" y1="82.0" x2="499.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="499.6" y1="82.0" x2="503.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="503.2" y1="82.0" x2="506.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="506.8" y1="82.0" x2="510.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="510.4" y1="82.0" x2="514.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="514.0" y1="82.0" x2="517.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="517.6" y1="82.0" x2="521.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="521.2" y1="82.0" x2="524.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="524.8" y1="82.0" x2="528.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="524.8" y1="74.0" x2="524.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="524.8" y1="82.0" x2="524.8" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="528.4" y1="82.0" x2="532.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="532.0" y1="82.0" x2="535.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="535.6" y1="82.0" x2="539.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="539.2" y1="82.0" x2="542.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="542.8" y1="82.0" x2="546.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="546.4" y1="82.0" x2="550.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="550.0" y1="82.0" x2="553.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="553.6" y1="82.0" x2="557.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="557.2" y1="82.0" x2="560.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="560.8" y1="82.0" x2="564.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="564.4" y1="82.0" x2="568.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="568.0" y1="82.0" x2="571.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="571.6" y1="82.0" x2="575.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="575.2" y1="82.0" x2="578.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="578.8" y1="82.0" x2="582.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="582.4" y1="82.0" x2="586.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="586.0" y1="82.0" x2="589.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="589.6" y1="82.0" x2="593.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="593.2" y1="82.0" x2="596.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="596.8" y1="82.0" x2="600.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="600.4" y1="82.0" x2="604.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="604.0" y1="82.0" x2="607.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="607.6" y1="82.0" x2="611.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="611.2" y1="82.0" x2="614.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="614.8" y1="82.0" x2="618.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="618.4" y1="82.0" x2="622.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="622.0" y1="82.0" x2="625.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="625.6" y1="82.0" x2="629.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="629.2" y1="82.0" x2="632.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="632.8" y1="82.0" x2="632.8" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="416.8" y1="138.0" x2="416.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="416.8" y1="146.0" x2="416.8" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="524.8" y1="138.0" x2="524.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="524.8" y1="146.0" x2="524.8" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="632.8" y1="138.0" x2="632.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="632.8" y1="146.0" x2="632.8" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="416.8" y1="202.0" x2="416.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="416.8" y1="210.0" x2="416.8" y2="218.0" stroke="#10373E" stroke-width="1.3"/><line x1="524.8" y1="202.0" x2="524.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="524.8" y1="210.0" x2="524.8" y2="218.0" stroke="#10373E" stroke-width="1.3"/><line x1="632.8" y1="202.0" x2="632.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="632.8" y1="210.0" x2="632.8" y2="218.0" stroke="#10373E" stroke-width="1.3"/><line x1="416.8" y1="226.0" x2="420.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="416.8" y1="218.0" x2="416.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="420.4" y1="226.0" x2="424.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="424.0" y1="226.0" x2="427.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="427.6" y1="226.0" x2="431.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="431.2" y1="226.0" x2="434.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="434.8" y1="226.0" x2="438.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="438.4" y1="226.0" x2="442.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="442.0" y1="226.0" x2="445.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="445.6" y1="226.0" x2="449.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="449.2" y1="226.0" x2="452.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="452.8" y1="226.0" x2="456.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="456.4" y1="226.0" x2="460.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="460.0" y1="226.0" x2="463.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="463.6" y1="226.0" x2="467.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="467.2" y1="226.0" x2="470.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="470.8" y1="226.0" x2="474.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="474.4" y1="226.0" x2="478.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="478.0" y1="226.0" x2="481.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="481.6" y1="226.0" x2="485.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="485.2" y1="226.0" x2="488.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="488.8" y1="226.0" x2="492.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="492.4" y1="226.0" x2="496.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="496.0" y1="226.0" x2="499.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="499.6" y1="226.0" x2="503.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="503.2" y1="226.0" x2="506.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="506.8" y1="226.0" x2="510.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="510.4" y1="226.0" x2="514.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="514.0" y1="226.0" x2="517.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="517.6" y1="226.0" x2="521.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="521.2" y1="226.0" x2="524.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="524.8" y1="226.0" x2="528.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="524.8" y1="218.0" x2="524.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="528.4" y1="226.0" x2="532.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="532.0" y1="226.0" x2="535.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="535.6" y1="226.0" x2="539.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="539.2" y1="226.0" x2="542.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="542.8" y1="226.0" x2="546.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="546.4" y1="226.0" x2="550.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="550.0" y1="226.0" x2="553.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="553.6" y1="226.0" x2="557.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="557.2" y1="226.0" x2="560.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="560.8" y1="226.0" x2="564.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="564.4" y1="226.0" x2="568.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="568.0" y1="226.0" x2="571.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="571.6" y1="226.0" x2="575.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="575.2" y1="226.0" x2="578.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="578.8" y1="226.0" x2="582.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="582.4" y1="226.0" x2="586.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="586.0" y1="226.0" x2="589.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="589.6" y1="226.0" x2="593.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="593.2" y1="226.0" x2="596.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="596.8" y1="226.0" x2="600.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="600.4" y1="226.0" x2="604.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="604.0" y1="226.0" x2="607.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="607.6" y1="226.0" x2="611.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="611.2" y1="226.0" x2="614.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="614.8" y1="226.0" x2="618.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="618.4" y1="226.0" x2="622.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="622.0" y1="226.0" x2="625.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="625.6" y1="226.0" x2="629.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="629.2" y1="226.0" x2="632.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="632.8" y1="218.0" x2="632.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="524.8" y1="234.0" x2="524.8" y2="242.0" stroke="#10373E" stroke-width="1.3"/><line x1="524.8" y1="242.0" x2="524.8" y2="250.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="306.0" x2="179.2" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="306.0" x2="182.8" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="306.0" x2="186.4" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="306.0" x2="190.0" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="306.0" x2="287.2" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="287.2" y1="306.0" x2="290.8" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="306.0" x2="294.4" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="294.4" y1="306.0" x2="298.0" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="370.0" y1="306.0" x2="373.6" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="373.6" y1="306.0" x2="377.2" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="377.2" y1="306.0" x2="380.8" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="380.8" y1="306.0" x2="384.4" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="354.0" x2="157.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="354.0" x2="161.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="354.0" x2="164.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="354.0" x2="168.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="354.0" x2="265.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="265.6" y1="354.0" x2="269.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="354.0" x2="272.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="272.8" y1="354.0" x2="276.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="348.4" y1="354.0" x2="352.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="352.0" y1="354.0" x2="355.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="354.0" x2="359.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="359.2" y1="354.0" x2="362.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><path d="M167.0 46.0 L174.0 50.0 L167.0 54.0 Z" fill="#10373E"/><path d="M282.2 46.0 L289.2 50.0 L282.2 54.0 Z" fill="#10373E"/><path d="M361.4 46.0 L368.4 50.0 L361.4 54.0 Z" fill="#10373E"/><path d="M469.4 46.0 L476.4 50.0 L469.4 54.0 Z" fill="#10373E"/><path d="M412.8 93.0 L416.8 100.0 L420.8 93.0 Z" fill="#10373E"/><path d="M520.8 93.0 L524.8 100.0 L528.8 93.0 Z" fill="#10373E"/><path d="M628.8 93.0 L632.8 100.0 L636.8 93.0 Z" fill="#10373E"/><path d="M412.8 157.0 L416.8 164.0 L420.8 157.0 Z" fill="#10373E"/><path d="M520.8 157.0 L524.8 164.0 L528.8 157.0 Z" fill="#10373E"/><path d="M628.8 157.0 L632.8 164.0 L636.8 157.0 Z" fill="#10373E"/><path d="M520.8 253.0 L524.8 260.0 L528.8 253.0 Z" fill="#10373E"/><path d="M188.6 302.0 L195.6 306.0 L188.6 310.0 Z" fill="#10373E"/><path d="M296.6 302.0 L303.6 306.0 L296.6 310.0 Z" fill="#10373E"/><path d="M383.0 302.0 L390.0 306.0 L383.0 310.0 Z" fill="#10373E"/><path d="M167.0 350.0 L174.0 354.0 L167.0 358.0 Z" fill="#10373E"/><path d="M275.0 350.0 L282.0 354.0 L275.0 358.0 Z" fill="#10373E"/><path d="M361.4 350.0 L368.4 354.0 L361.4 358.0 Z" fill="#10373E"/><text x="10.0" y="22.0" font-size="11.5" fill="#10373E">WITH</text><text x="46.0" y="22.0" font-size="11.5" fill="#10373E">Heratio</text><text x="103.6" y="22.0" font-size="11.5" fill="#10373E">installed</text><text x="175.6" y="22.0" font-size="11.5" fill="#10373E">(nginx</text><text x="226.0" y="22.0" font-size="11.5" fill="#10373E">includes</text><text x="290.8" y="22.0" font-size="11.5" fill="#10373E">heratio.conf):</text><text x="24.4" y="54.0" font-size="11.5" fill="#10373E">AHG</text><text x="53.2" y="54.0" font-size="11.5" fill="#10373E">plugin</text><text x="103.6" y="54.0" font-size="11.5" fill="#10373E">routes</text><text x="182.8" y="54.0" font-size="11.5" fill="#10373E">heratio.php</text><text x="298.0" y="54.0" font-size="11.5" fill="#10373E">Kernel</text><text x="377.2" y="54.0" font-size="11.5" fill="#10373E">Middleware</text><text x="485.2" y="54.0" font-size="11.5" fill="#10373E">ActionBridge</text><text x="377.2" y="118.0" font-size="11.5" fill="#10373E">AhgController</text><text x="492.4" y="118.0" font-size="11.5" fill="#10373E">AhgActions</text><text x="593.2" y="118.0" font-size="11.5" fill="#10373E">sfActions</text><text x="377.2" y="134.0" font-size="11.5" fill="#10373E">(standalone)</text><text x="492.4" y="134.0" font-size="11.5" fill="#10373E">(Blade)</text><text x="593.2" y="134.0" font-size="11.5" fill="#10373E">(Propel</text><text x="650.8" y="134.0" font-size="11.5" fill="#10373E">Bridge)</text><text x="377.2" y="182.0" font-size="11.5" fill="#10373E">WriteService</text><text x="485.2" y="182.0" font-size="11.5" fill="#10373E">BladeRenderer</text><text x="593.2" y="182.0" font-size="11.5" fill="#10373E">Symfony</text><text x="650.8" y="182.0" font-size="11.5" fill="#10373E">sfView</text><text x="391.6" y="198.0" font-size="11.5" fill="#10373E">Laravel</text><text x="449.2" y="198.0" font-size="11.5" fill="#10373E">DB</text><text x="499.6" y="198.0" font-size="11.5" fill="#10373E">Layout</text><text x="550.0" y="198.0" font-size="11.5" fill="#10373E">wrap</text><text x="607.6" y="198.0" font-size="11.5" fill="#10373E">theme</text><text x="650.8" y="198.0" font-size="11.5" fill="#10373E">partials</text><text x="485.2" y="278.0" font-size="11.5" fill="#10373E">Full</text><text x="521.2" y="278.0" font-size="11.5" fill="#10373E">HTML</text><text x="557.2" y="278.0" font-size="11.5" fill="#10373E">Page</text><text x="24.4" y="310.0" font-size="11.5" fill="#10373E">Base</text><text x="60.4" y="310.0" font-size="11.5" fill="#10373E">Heratio</text><text x="118.0" y="310.0" font-size="11.5" fill="#10373E">routes</text><text x="204.4" y="310.0" font-size="11.5" fill="#10373E">index.php</text><text x="312.4" y="310.0" font-size="11.5" fill="#10373E">Symfony</text><text x="398.8" y="310.0" font-size="11.5" fill="#10373E">Full</text><text x="434.8" y="310.0" font-size="11.5" fill="#10373E">HTML</text><text x="470.8" y="310.0" font-size="11.5" fill="#10373E">page</text><text x="506.8" y="310.0" font-size="11.5" fill="#10373E">(unchanged)</text><text x="10.0" y="342.0" font-size="11.5" fill="#10373E">WITHOUT</text><text x="67.6" y="342.0" font-size="11.5" fill="#10373E">Heratio</text><text x="125.2" y="342.0" font-size="11.5" fill="#10373E">(standard</text><text x="197.2" y="342.0" font-size="11.5" fill="#10373E">Heratio):</text><text x="24.4" y="358.0" font-size="11.5" fill="#10373E">ALL</text><text x="53.2" y="358.0" font-size="11.5" fill="#10373E">routes</text><text x="182.8" y="358.0" font-size="11.5" fill="#10373E">index.php</text><text x="290.8" y="358.0" font-size="11.5" fill="#10373E">Symfony</text><text x="377.2" y="358.0" font-size="11.5" fill="#10373E">Full</text><text x="413.2" y="358.0" font-size="11.5" fill="#10373E">HTML</text><text x="449.2" y="358.0" font-size="11.5" fill="#10373E">page</text><text x="485.2" y="358.0" font-size="11.5" fill="#10373E">(unchanged)</text></svg></div>

---

## Kill-Switch Mechanism

| Component | Flag | Purpose |
|-----------|------|---------|
| App-level | `.heratio_enabled` file in root | PHP checks `file_exists()` |
| Nginx-level | `heratio.conf` include | Routes AHG plugin URLs to `heratio.php` |

Both can be toggled instantly without deployment. Removing the flag falls back to standard Heratio.

---

## Completed Foundation (10 Commits)

| Commit | Description | Status |
|--------|-------------|--------|
| C1 | App kill-switch (`.heratio_enabled` flag) | Done |
| C2 | Nginx kill-switch (heratio.conf dual entry) | Done |
| C3 | DB config + boot assertions | Done |
| C4 | WriteService interfaces + adapter skeleton (6 interfaces) | Done |
| C5 | Refactor Settings handlers (14 files) | Done |
| C6 | Refactor ACL permissions handler | Done |
| C7 | Refactor DO edit actions (2 files) | Done |
| C8 | Refactor Term/Accession/Import services | Done |
| C9 | Route modernization (Settings + Display -> routes.php) | Done |
| C10 | Audit scripts + CI guardrails | Done |

---

## Infrastructure Layer (All Complete)

| Component | Status | Details |
|-----------|--------|---------|
| HTTP Kernel | Done | Boot sequence, middleware pipeline, route dispatch |
| Authentication | Done | Login/logout/me, session sharing, SfUserAdapter |
| Menu Service | Done | MPTT tree from DB, culture-aware, static cache |
| Blade Rendering | Done | BladeRenderer, custom directives, 326 templates |
| Symfony Helper Shims | Done | blade_shims.php -- url_for, link_to, slots, partials (403 lines) |
| Master Layout | Done | heratio.blade.php + 8 partials (header, footer, nav, alerts) |
| Middleware Stack | Done | Session, Auth, Settings, CSP, Meta, Limits (7 middleware) |
| Nginx Config | Done | heratio.conf with kill-switch, ~40 plugin route patterns |
| WriteServiceFactory | Done | 12 interfaces, 12 PropelAdapters with Laravel DB fallback |
| Routes.php | Done | 77 native routes (Settings 55 + Display 22) |
| Audit Scripts | Done | bin/audit-propel, audit-propel-baseline, audit-propel-check |

---

## Phase 1: Read Services (Complete)

| Component | Details |
|-----------|---------|
| PaginationService (WP11) | SimplePager + PaginationService (universal, replaces per-plugin pagers) |
| EntityQueryService (WP12) | Slug resolution, entity loading, MPTT traversal, i18n (837 lines) |
| SearchService (WP13) | Standalone ES via HTTP curl, DB LIKE fallback, faceted search |
| LightweightResource | Magic wrapper for template compatibility (`__get`, `__isset`, `__toString`) |

---

## Phase 2: Entity CRUD Services (Complete)

| Component | Details |
|-----------|---------|
| UserWriteService (WP14) | createUser, updatePassword, savePasswordResetToken (6 files refactored) |
| ActorWriteService (WP15) | createActor, updateActor, createRelation, saveActor (AI plugin refactored) |
| PhysicalObjectWriteService (WP16) | newPhysicalObject, create/update/save (4 files refactored) |
| FeedbackWriteService (WP17) | createFeedback (ThemeB5 editFeedback refactored) |
| RequestToPublishWriteService (WP17) | createRequest (Display + ThemeB5 refactored) |
| JobWriteService (WP17) | createJob (DataMigration queueJob refactored) |
| Settings/Themes (WP17) | Remaining save() patterns in Settings + ThemeB5 refactored |

**WriteServiceFactory: 12 services total:**
settings, acl, digitalObject, term, accession, import, user, actor, physicalObject, feedback, requestToPublish, job

---

## PaginationService Integration (Complete)

Wired into 12 action files as dual-mode fallback (`class_exists('QubitPager')` branch):

| Plugin | File | Method |
|--------|------|--------|
| ahgStorageManagePlugin | physicalobject/autocompleteAction | execute() |
| ahgStorageManagePlugin | physicalobject/actions | executeAutocomplete() |
| ahgStorageManagePlugin | storageManage/actions | executeAutocomplete() |
| ahgRightsHolderManagePlugin | rightsholder/autocompleteAction | execute() |
| ahgRightsHolderManagePlugin | rightsholder/listAction | execute() |
| ahgDonorManagePlugin | donor/autocompleteAction | execute() |
| ahgDonorManagePlugin | donor/listAction | execute() |
| ahgRequestToPublishPlugin | requesttopublish/browseAction | execute() |
| ahgRequestToPublishPlugin | requesttopublish/receiptAction | execute() |
| ahgSearchPlugin | descriptionUpdatesAction | doAuditLogSearch() |
| ahgSearchPlugin | globalReplaceAction | AhgSearchPager -> SimplePager |
| ahgReportsPlugin | reportTaxomomyAction | doSearch() |

---

## Current Propel Coupling Baseline

```
->save()          : 42    (was 53, -11 via WP14-17)
new Qubit*        : 53    (was 68, -15 via WP14-17)
->delete()        : 128   (unchanged)
->setValue(       : 0     (was 2, -2)
QubitQuery        : 0     (unchanged)
Total coupling    : 223   (was 251, -28)
```

### Classification of Remaining `new Qubit*` References (53)

| Category | Count | Action |
|----------|-------|--------|
| READ-ONLY | 32 | Leave -- validators, pagers, helpers (never saved) |
| WIDGET | 5 | Leave -- form formatters, input widgets |
| DEFERRED | 11 | Leave -- addDigitalObject/multiFileUpload (complex Propel asset pipeline) |
| **WRITE** | **5** | **Remaining wrappable patterns** |

### Classification of Remaining `->save()` Calls (42)

Most are form-bound `$this->resource->save()` patterns (resource loaded from Propel, mutated by sfForm, saved):

- `$this->resource->save()` in edit actions (sfIsaarPlugin, termTaxonomy, library, etc.)
- `$findingAid->save()` in rename actions (Display, Library)
- `->save()` in addDigitalObject/multiFileUpload (Propel asset pipeline -- DEFERRED)
- `->save()` in requestToPublish editAction (form-bound -- RequestToPublishPlugin)

### Classification of Remaining `->delete()` Calls (128)

Mostly in dedicated `deleteAction.class.php` files -- legitimate entity deletions using Propel's cascade mechanism. These work through Propel's cascade chain (`object -> actor -> user`, etc.) and are hard to abstract without replicating the full cascade.

---

## Outstanding Phases

### Phase 3: Delete Services (Low Priority)

128 `->delete()` calls across ~35 delete action files. These use Propel's cascade mechanism.

**Proposed WP18: EntityDeleteService**

```php
class EntityDeleteService
{
    public static function delete(int $objectId): bool;
    // Handles: object -> actor -> user/donor/repository cascade
    // Handles: object -> information_object -> digital_object cascade
    // Handles: property, note, relation, event cleanup
}
```

**Risk:** HIGH -- incorrect cascade can leave orphaned rows or violate FK constraints.
**Recommendation:** Keep using Propel for deletes. Only implement when Propel fully removed.

### Phase 4: Form Framework (Low Priority)

Replace sfForm with Laravel `Illuminate\Validation`.

```php
class FormService
{
    public static function validate(Request $request, array $rules): array;
    // Returns validated data or throws ValidationException
}
```

Not needed while PropelBridge loads Symfony core. sfForm is available even in Heratio mode.

### Phase 5: Propel Bridge Removal (Future)

Replace `Qubit*` Propel models with PHP value objects + repositories:

- `QubitInformationObject` -> `InformationObject` + `InformationObjectRepository`
- `QubitActor` -> `Actor` + `ActorRepository`
- `QubitDigitalObject` -> `DigitalObject` + `DigitalObjectRepository`

Very large effort. Only after all other phases stable.

---

## Propel Coupling by Plugin

| Plugin | save | new | delete | Total | Priority |
|--------|------|-----|--------|-------|----------|
| ahgThemeB5Plugin | 12 | 10 | 8 | 30 | P2 -- locked |
| ahgDisplayPlugin | 9 | 7 | 6 | 22 | P2 -- locked |
| ahgAPIPlugin | 0 | 0 | 14 | 14 | P3 -- delete-only |
| ahgSettingsPlugin | 0 | 7 | 6 | 13 | P2 |
| ahg3DModelPlugin | 0 | 0 | 11 | 11 | P3 -- delete-only |
| ahgTermTaxonomyPlugin | 3 | 2 | 4 | 9 | P2 -- locked |
| ahgLibraryPlugin | 5 | 3 | 1 | 9 | P2 -- locked |
| ahgAccessionManagePlugin | 1 | 2 | 6 | 9 | P2 -- locked |
| ahgDAMPlugin | 2 | 1 | 6 | 9 | P2 |
| ahgResearchPlugin | 0 | 0 | 8 | 8 | P3 -- delete-only |
| ahgExtendedRightsPlugin | 0 | 0 | 8 | 8 | P3 -- delete-only |
| ahgStorageManagePlugin | 2 | 3 | 2 | 7 | P2 -- locked |
| ahgDonorAgreementPlugin | 0 | 0 | 7 | 7 | P3 -- delete-only |
| ahgRightsHolderManagePlugin | 1 | 3 | 2 | 6 | P2 -- locked |
| ahgRequestToPublishPlugin | 2 | 2 | 1 | 5 | P2 |
| ahgReportsPlugin | 0 | 5 | 0 | 5 | P3 -- read-only |
| ahgCorePlugin | 2 | 2 | 0 | 4 | P2 -- locked |
| ahgVendorPlugin | 0 | 0 | 4 | 4 | P3 -- delete-only |
| ahgUiOverridesPlugin | 2 | 2 | 0 | 4 | P2 -- locked |
| ahgICIPPlugin | 0 | 0 | 4 | 4 | P3 -- delete-only |
| ahgActorManagePlugin | 0 | 2 | 1 | 3 | P3 -- read-only |
| ahgMetadataExtractionPlugin | 0 | 0 | 3 | 3 | P3 -- delete-only |
| ahgIiifPlugin | 0 | 0 | 3 | 3 | P3 -- delete-only |
| ahgSearchPlugin | 1 | 1 | 0 | 2 | P2 |
| ahgDonorManagePlugin | 0 | 2 | 0 | 2 | P3 -- read-only |
| ahgSecurityClearancePlugin | 0 | 0 | 2 | 2 | P3 -- locked |
| ahgPrivacyPlugin | 0 | 0 | 2 | 2 | P3 -- delete-only |
| ahgSpectrumPlugin | 0 | 0 | 2 | 2 | P3 -- delete-only |
| ahgHeritageAccountingPlugin | 0 | 0 | 2 | 2 | P3 -- delete-only |
| ahgReportBuilderPlugin | 0 | 0 | 2 | 2 | P3 -- delete-only |
| ahgProvenancePlugin | 0 | 0 | 2 | 2 | P3 -- delete-only |
| ahgMuseumPlugin | 0 | 0 | 2 | 2 | P3 -- delete-only |
| ahgRepositoryManagePlugin | 0 | 1 | 1 | 2 | P3 -- read-only |
| ahgDataMigrationPlugin | 0 | 0 | 1 | 1 | P2 |
| ahgInformationObjectManagePlugin | 1 | 0 | 0 | 1 | P2 |
| ahgAIPlugin | 0 | 0 | 1 | 1 | P3 -- delete-only |
| ahgDedupePlugin | 0 | 0 | 1 | 1 | P3 -- delete-only |
| ahgFederationPlugin | 0 | 0 | 1 | 1 | P3 -- future |
| ahgFeedbackPlugin | 0 | 0 | 1 | 1 | P3 -- delete-only |
| ahgFormsPlugin | 0 | 0 | 1 | 1 | P3 -- delete-only |
| ahgGalleryPlugin | 0 | 0 | 1 | 1 | P3 -- delete-only |
| ahgHeritagePlugin | 0 | 0 | 1 | 1 | P3 -- delete-only |
| ahgSemanticSearchPlugin | 0 | 0 | 1 | 1 | P3 -- delete-only |

### By Category

| Category | Files | Total Coupling | Strategy |
|----------|-------|----------------|----------|
| Write (save + new) | ~20 | 95 | Form-bound Propel (edit actions) + DEFERRED (DO upload) |
| Delete only | ~35 | 128 | Keep Propel (Phase 3) |
| Read only (pagers, validators) | ~15 | 37 | Can use PaginationService/EntityQueryService |
| Widgets | ~5 | 5 | Leave (UI components) |

---

## Framework Service Inventory

| File | Lines | Purpose |
|------|-------|---------|
| Pagination/SimplePager.php | ~150 | Universal pager compatible with _pager.php partial |
| Pagination/PaginationService.php | ~530 | High-level paginate() with entity-aware JOINs |
| EntityQueryService.php | ~837 | Slug resolution, entity loading, MPTT, i18n, relations |
| LightweightResource.php | ~58 | Magic wrapper for template compatibility |
| Search/SearchService.php | ~350 | Standalone ES search, DB fallback, facets |
| MenuService.php | ~100 | MPTT menu tree from database |
| Write/WriteServiceFactory.php | ~291 | 12-service singleton factory |
| Write/*Interface.php (12 files) | ~30 ea | Service contracts |
| Write/Propel*.php (12 files) | ~100 ea | Dual-mode adapters (Propel + Laravel DB) |

---

## Audit Tools

| File | Purpose |
|------|---------|
| audit-propel | Main coupling audit (5 patterns, per-file detail) |
| audit-propel-baseline | Saves JSON baseline to .propel-baseline.json |
| audit-propel-check | CI guardrail -- exit 1 on regression |

---

## Route Classification

| Type | Count | Notes |
|------|-------|-------|
| Native (routes.php) | 2 plugins | ahgSettingsPlugin, ahgDisplayPlugin |
| Bridged (routing.yml) | 39 plugins | Converted by RouteCollector at runtime |
| No routes | 39 plugins | Background/service plugins |

---

## Priority Matrix

<div style="overflow-x:auto;margin:1rem 0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 495 388" style="max-width:100%;height:auto;font-family:ui-monospace,Menlo,Consolas,monospace"><rect x="0.5" y="0.5" width="494" height="387" rx="8" fill="#f7faf9" stroke="#d8e6e3"/><line x1="211.6" y1="18.0" x2="215.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="215.2" y1="18.0" x2="218.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="18.0" x2="222.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="222.4" y1="18.0" x2="226.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="18.0" x2="229.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="229.6" y1="18.0" x2="233.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="98.0" x2="215.2" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="215.2" y1="98.0" x2="218.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="98.0" x2="222.4" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="222.4" y1="98.0" x2="226.0" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="98.0" x2="229.6" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="229.6" y1="98.0" x2="233.2" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="194.0" x2="222.4" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="222.4" y1="194.0" x2="226.0" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="194.0" x2="229.6" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="229.6" y1="194.0" x2="233.2" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="194.0" x2="236.8" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="236.8" y1="194.0" x2="240.4" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="258.0" x2="215.2" y2="258.0" stroke="#10373E" stroke-width="1.3"/><line x1="215.2" y1="258.0" x2="218.8" y2="258.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="258.0" x2="222.4" y2="258.0" stroke="#10373E" stroke-width="1.3"/><line x1="222.4" y1="258.0" x2="226.0" y2="258.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="258.0" x2="229.6" y2="258.0" stroke="#10373E" stroke-width="1.3"/><line x1="229.6" y1="258.0" x2="233.2" y2="258.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="306.0" x2="215.2" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="215.2" y1="306.0" x2="218.8" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="306.0" x2="222.4" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="222.4" y1="306.0" x2="226.0" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="306.0" x2="229.6" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="229.6" y1="306.0" x2="233.2" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="354.0" x2="215.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="215.2" y1="354.0" x2="218.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="354.0" x2="222.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="222.4" y1="354.0" x2="226.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="354.0" x2="229.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="229.6" y1="354.0" x2="233.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><text x="10.0" y="22.0" font-size="11.5" fill="#10373E">PHASE</text><text x="53.2" y="22.0" font-size="11.5" fill="#10373E">1</text><text x="67.6" y="22.0" font-size="11.5" fill="#10373E">(Read</text><text x="110.8" y="22.0" font-size="11.5" fill="#10373E">Services)</text><text x="240.4" y="22.0" font-size="11.5" fill="#10373E">DONE</text><text x="24.4" y="38.0" font-size="11.5" fill="#10373E">WP11:</text><text x="67.6" y="38.0" font-size="11.5" fill="#10373E">PaginationService</text><text x="240.4" y="38.0" font-size="11.5" fill="#10373E">✓</text><text x="24.4" y="54.0" font-size="11.5" fill="#10373E">WP12:</text><text x="67.6" y="54.0" font-size="11.5" fill="#10373E">EntityQueryService</text><text x="247.6" y="54.0" font-size="11.5" fill="#10373E">✓</text><text x="24.4" y="70.0" font-size="11.5" fill="#10373E">WP13:</text><text x="67.6" y="70.0" font-size="11.5" fill="#10373E">SearchService</text><text x="247.6" y="70.0" font-size="11.5" fill="#10373E">✓</text><text x="10.0" y="102.0" font-size="11.5" fill="#10373E">PHASE</text><text x="53.2" y="102.0" font-size="11.5" fill="#10373E">2</text><text x="67.6" y="102.0" font-size="11.5" fill="#10373E">(Entity</text><text x="125.2" y="102.0" font-size="11.5" fill="#10373E">CRUD)</text><text x="240.4" y="102.0" font-size="11.5" fill="#10373E">DONE</text><text x="24.4" y="118.0" font-size="11.5" fill="#10373E">WP14:</text><text x="67.6" y="118.0" font-size="11.5" fill="#10373E">UserWriteService</text><text x="247.6" y="118.0" font-size="11.5" fill="#10373E">✓</text><text x="24.4" y="134.0" font-size="11.5" fill="#10373E">WP15:</text><text x="67.6" y="134.0" font-size="11.5" fill="#10373E">ActorWriteService</text><text x="247.6" y="134.0" font-size="11.5" fill="#10373E">✓</text><text x="24.4" y="150.0" font-size="11.5" fill="#10373E">WP16:</text><text x="67.6" y="150.0" font-size="11.5" fill="#10373E">PhysicalObjectWriteService</text><text x="262.0" y="150.0" font-size="11.5" fill="#10373E">✓</text><text x="24.4" y="166.0" font-size="11.5" fill="#10373E">WP17:</text><text x="67.6" y="166.0" font-size="11.5" fill="#10373E">MiscWriteServices</text><text x="247.6" y="166.0" font-size="11.5" fill="#10373E">✓</text><text x="10.0" y="198.0" font-size="11.5" fill="#10373E">INTEGRATION</text><text x="247.6" y="198.0" font-size="11.5" fill="#10373E">DONE</text><text x="24.4" y="214.0" font-size="11.5" fill="#10373E">PaginationService</text><text x="154.0" y="214.0" font-size="11.5" fill="#10373E">wired</text><text x="197.2" y="214.0" font-size="11.5" fill="#10373E">into</text><text x="233.2" y="214.0" font-size="11.5" fill="#10373E">12</text><text x="254.8" y="214.0" font-size="11.5" fill="#10373E">action</text><text x="305.2" y="214.0" font-size="11.5" fill="#10373E">files</text><text x="355.6" y="214.0" font-size="11.5" fill="#10373E">✓</text><text x="24.4" y="230.0" font-size="11.5" fill="#10373E">AhgSearchPager</text><text x="132.4" y="230.0" font-size="11.5" fill="#10373E">replaced</text><text x="197.2" y="230.0" font-size="11.5" fill="#10373E">with</text><text x="233.2" y="230.0" font-size="11.5" fill="#10373E">SimplePager</text><text x="362.8" y="230.0" font-size="11.5" fill="#10373E">✓</text><text x="10.0" y="262.0" font-size="11.5" fill="#10373E">PHASE</text><text x="53.2" y="262.0" font-size="11.5" fill="#10373E">3</text><text x="67.6" y="262.0" font-size="11.5" fill="#10373E">(Delete</text><text x="125.2" y="262.0" font-size="11.5" fill="#10373E">Services)</text><text x="240.4" y="262.0" font-size="11.5" fill="#10373E">LOW:</text><text x="276.4" y="262.0" font-size="11.5" fill="#10373E">Keep</text><text x="312.4" y="262.0" font-size="11.5" fill="#10373E">Propel</text><text x="362.8" y="262.0" font-size="11.5" fill="#10373E">for</text><text x="391.6" y="262.0" font-size="11.5" fill="#10373E">now</text><text x="24.4" y="278.0" font-size="11.5" fill="#10373E">WP18:</text><text x="67.6" y="278.0" font-size="11.5" fill="#10373E">EntityDeleteService</text><text x="10.0" y="310.0" font-size="11.5" fill="#10373E">PHASE</text><text x="53.2" y="310.0" font-size="11.5" fill="#10373E">4</text><text x="67.6" y="310.0" font-size="11.5" fill="#10373E">(Form</text><text x="110.8" y="310.0" font-size="11.5" fill="#10373E">Framework)</text><text x="240.4" y="310.0" font-size="11.5" fill="#10373E">LOW:</text><text x="276.4" y="310.0" font-size="11.5" fill="#10373E">sfForm</text><text x="326.8" y="310.0" font-size="11.5" fill="#10373E">works</text><text x="370.0" y="310.0" font-size="11.5" fill="#10373E">via</text><text x="398.8" y="310.0" font-size="11.5" fill="#10373E">PropelBridge</text><text x="24.4" y="326.0" font-size="11.5" fill="#10373E">WP19:</text><text x="67.6" y="326.0" font-size="11.5" fill="#10373E">FormService</text><text x="10.0" y="358.0" font-size="11.5" fill="#10373E">PHASE</text><text x="53.2" y="358.0" font-size="11.5" fill="#10373E">5</text><text x="67.6" y="358.0" font-size="11.5" fill="#10373E">(Full</text><text x="110.8" y="358.0" font-size="11.5" fill="#10373E">Replacement)</text><text x="240.4" y="358.0" font-size="11.5" fill="#10373E">FUTURE:</text><text x="298.0" y="358.0" font-size="11.5" fill="#10373E">Remove</text><text x="348.4" y="358.0" font-size="11.5" fill="#10373E">Propel</text><text x="398.8" y="358.0" font-size="11.5" fill="#10373E">entirely</text><text x="24.4" y="374.0" font-size="11.5" fill="#10373E">WP20:</text><text x="67.6" y="374.0" font-size="11.5" fill="#10373E">Model</text><text x="110.8" y="374.0" font-size="11.5" fill="#10373E">Layer</text></svg></div>

---

## Success Criteria

| # | Criterion | Status |
|---|-----------|--------|
| 1 | Settings pages render fully standalone (no Propel) | DONE |
| 2 | Browse pages render standalone with PaginationService | DONE |
| 3 | Search pages render standalone with ES direct | DONE |
| 4 | CRUD pages work standalone with WriteServices | DONE |
| 5 | Delete operations work standalone | PENDING (WP18) |
| 6 | Kill-switch toggles instantly between modes | DONE |
| 7 | Zero base Heratio modifications | DONE |
| 8 | Audit baseline prevents regression | DONE |

---

*Part of the Heratio Framework*
