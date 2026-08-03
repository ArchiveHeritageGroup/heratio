> Heratio Help Center article. Category: Admin & Settings.

# Multi-Tenant System User Guide

**Version:** 1.2.0

Manage multiple organizations (tenants) with isolated user access, domain routing, status control, and custom branding.

---

## Overview

The Multi-Tenant System allows a single Heratio installation to serve multiple organizations with:

- **Isolated Access**: Users only see data from their assigned tenants
- **Status Control**: Tenants can be Active, on Trial, or Suspended
- **Custom Branding**: Per-tenant colors, logos, and CSS
- **Role-Based Access**: Owner, Super User, Editor, Contributor, Viewer roles

<div style="overflow-x:auto;margin:1rem 0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 531 244" style="max-width:100%;height:auto;font-family:ui-monospace,Menlo,Consolas,monospace"><rect x="0.5" y="0.5" width="530" height="243" rx="8" fill="#f7faf9" stroke="#d8e6e3"/><line x1="13.6" y1="18.0" x2="17.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="18.0" x2="13.6" y2="26.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="18.0" x2="24.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="18.0" x2="31.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="18.0" x2="38.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="18.0" x2="46.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="18.0" x2="53.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="18.0" x2="60.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="18.0" x2="67.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="18.0" x2="74.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="18.0" x2="82.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="18.0" x2="89.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="18.0" x2="96.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="18.0" x2="103.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="18.0" x2="110.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="18.0" x2="118.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="18.0" x2="125.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="18.0" x2="132.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="18.0" x2="139.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="18.0" x2="146.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="18.0" x2="154.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="18.0" x2="161.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="18.0" x2="168.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="18.0" x2="175.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="18.0" x2="182.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="18.0" x2="190.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="18.0" x2="197.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="18.0" x2="204.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="18.0" x2="211.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="18.0" x2="218.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="18.0" x2="226.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="18.0" x2="233.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="18.0" x2="240.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="18.0" x2="247.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="18.0" x2="254.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="18.0" x2="262.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="18.0" x2="269.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="18.0" x2="276.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="18.0" x2="283.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="18.0" x2="290.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="18.0" x2="298.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="18.0" x2="305.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="18.0" x2="312.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="18.0" x2="319.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="18.0" x2="326.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="18.0" x2="334.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="18.0" x2="341.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="18.0" x2="348.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="348.4" y1="18.0" x2="355.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="18.0" x2="362.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="362.8" y1="18.0" x2="370.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="370.0" y1="18.0" x2="377.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="377.2" y1="18.0" x2="384.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="384.4" y1="18.0" x2="391.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="391.6" y1="18.0" x2="398.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="398.8" y1="18.0" x2="406.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="406.0" y1="18.0" x2="413.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="413.2" y1="18.0" x2="420.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="420.4" y1="18.0" x2="427.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="427.6" y1="18.0" x2="434.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="434.8" y1="18.0" x2="442.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="442.0" y1="18.0" x2="449.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="449.2" y1="18.0" x2="456.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="456.4" y1="18.0" x2="463.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="463.6" y1="18.0" x2="470.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="470.8" y1="18.0" x2="478.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="478.0" y1="18.0" x2="485.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="485.2" y1="18.0" x2="492.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="492.4" y1="18.0" x2="499.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="499.6" y1="18.0" x2="506.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="506.8" y1="18.0" x2="514.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="514.0" y1="18.0" x2="517.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="517.6" y1="18.0" x2="517.6" y2="26.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="26.0" x2="13.6" y2="42.0" stroke="#10373E" stroke-width="1.3"/><line x1="517.6" y1="26.0" x2="517.6" y2="42.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="50.0" x2="17.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="42.0" x2="13.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="50.0" x2="13.6" y2="58.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="50.0" x2="24.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="50.0" x2="31.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="50.0" x2="38.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="50.0" x2="46.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="50.0" x2="53.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="50.0" x2="60.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="50.0" x2="67.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="50.0" x2="74.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="50.0" x2="82.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="50.0" x2="89.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="50.0" x2="96.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="50.0" x2="103.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="50.0" x2="110.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="50.0" x2="118.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="50.0" x2="125.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="50.0" x2="132.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="50.0" x2="139.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="50.0" x2="146.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="50.0" x2="154.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="50.0" x2="161.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="50.0" x2="168.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="50.0" x2="175.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="50.0" x2="182.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="50.0" x2="190.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="50.0" x2="197.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="50.0" x2="204.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="50.0" x2="211.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="50.0" x2="218.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="50.0" x2="226.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="50.0" x2="233.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="50.0" x2="240.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="50.0" x2="247.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="50.0" x2="254.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="50.0" x2="262.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="50.0" x2="269.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="50.0" x2="276.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="50.0" x2="283.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="50.0" x2="290.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="50.0" x2="298.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="50.0" x2="305.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="50.0" x2="312.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="50.0" x2="319.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="50.0" x2="326.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="50.0" x2="334.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="50.0" x2="341.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="50.0" x2="348.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="348.4" y1="50.0" x2="355.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="50.0" x2="362.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="362.8" y1="50.0" x2="370.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="370.0" y1="50.0" x2="377.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="377.2" y1="50.0" x2="384.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="384.4" y1="50.0" x2="391.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="391.6" y1="50.0" x2="398.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="398.8" y1="50.0" x2="406.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="406.0" y1="50.0" x2="413.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="413.2" y1="50.0" x2="420.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="420.4" y1="50.0" x2="427.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="427.6" y1="50.0" x2="434.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="434.8" y1="50.0" x2="442.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="442.0" y1="50.0" x2="449.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="449.2" y1="50.0" x2="456.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="456.4" y1="50.0" x2="463.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="463.6" y1="50.0" x2="470.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="470.8" y1="50.0" x2="478.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="478.0" y1="50.0" x2="485.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="485.2" y1="50.0" x2="492.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="492.4" y1="50.0" x2="499.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="499.6" y1="50.0" x2="506.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="506.8" y1="50.0" x2="514.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="514.0" y1="50.0" x2="517.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="517.6" y1="42.0" x2="517.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="517.6" y1="50.0" x2="517.6" y2="58.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="58.0" x2="13.6" y2="74.0" stroke="#10373E" stroke-width="1.3"/><line x1="517.6" y1="58.0" x2="517.6" y2="74.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="74.0" x2="13.6" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="82.0" x2="38.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="82.0" x2="35.2" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="82.0" x2="46.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="82.0" x2="53.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="82.0" x2="60.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="82.0" x2="67.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="82.0" x2="74.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="82.0" x2="82.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="82.0" x2="89.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="82.0" x2="96.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="82.0" x2="103.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="82.0" x2="110.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="82.0" x2="118.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="82.0" x2="125.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="82.0" x2="132.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="82.0" x2="136.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="82.0" x2="136.0" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="82.0" x2="190.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="82.0" x2="186.4" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="82.0" x2="197.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="82.0" x2="204.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="82.0" x2="211.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="82.0" x2="218.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="82.0" x2="226.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="82.0" x2="233.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="82.0" x2="240.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="82.0" x2="247.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="82.0" x2="254.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="82.0" x2="262.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="82.0" x2="269.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="82.0" x2="276.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="82.0" x2="283.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="82.0" x2="290.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="82.0" x2="298.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="82.0" x2="301.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="301.6" y1="82.0" x2="301.6" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="352.0" y1="82.0" x2="355.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="352.0" y1="82.0" x2="352.0" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="82.0" x2="362.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="362.8" y1="82.0" x2="370.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="370.0" y1="82.0" x2="377.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="377.2" y1="82.0" x2="384.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="384.4" y1="82.0" x2="391.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="391.6" y1="82.0" x2="398.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="398.8" y1="82.0" x2="406.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="406.0" y1="82.0" x2="413.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="413.2" y1="82.0" x2="420.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="420.4" y1="82.0" x2="427.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="427.6" y1="82.0" x2="434.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="434.8" y1="82.0" x2="442.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="442.0" y1="82.0" x2="449.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="449.2" y1="82.0" x2="456.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="456.4" y1="82.0" x2="463.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="463.6" y1="82.0" x2="470.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="470.8" y1="82.0" x2="478.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="478.0" y1="82.0" x2="481.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="481.6" y1="82.0" x2="481.6" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="517.6" y1="74.0" x2="517.6" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="90.0" x2="13.6" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="90.0" x2="35.2" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="90.0" x2="136.0" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="90.0" x2="186.4" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="301.6" y1="90.0" x2="301.6" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="352.0" y1="90.0" x2="352.0" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="481.6" y1="90.0" x2="481.6" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="517.6" y1="90.0" x2="517.6" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="106.0" x2="13.6" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="114.0" x2="38.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="106.0" x2="35.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="114.0" x2="46.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="114.0" x2="53.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="114.0" x2="60.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="114.0" x2="67.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="114.0" x2="74.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="114.0" x2="82.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="114.0" x2="89.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="114.0" x2="96.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="114.0" x2="103.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="114.0" x2="110.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="114.0" x2="118.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="114.0" x2="125.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="114.0" x2="132.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="114.0" x2="136.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="106.0" x2="136.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="114.0" x2="190.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="106.0" x2="186.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="114.0" x2="197.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="114.0" x2="204.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="114.0" x2="211.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="114.0" x2="218.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="114.0" x2="226.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="114.0" x2="233.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="114.0" x2="240.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="114.0" x2="247.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="114.0" x2="254.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="114.0" x2="262.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="114.0" x2="269.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="114.0" x2="276.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="114.0" x2="283.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="114.0" x2="290.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="114.0" x2="298.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="114.0" x2="301.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="301.6" y1="106.0" x2="301.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="352.0" y1="114.0" x2="355.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="352.0" y1="106.0" x2="352.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="114.0" x2="362.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="362.8" y1="114.0" x2="370.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="370.0" y1="114.0" x2="377.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="377.2" y1="114.0" x2="384.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="384.4" y1="114.0" x2="391.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="391.6" y1="114.0" x2="398.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="398.8" y1="114.0" x2="406.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="406.0" y1="114.0" x2="413.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="413.2" y1="114.0" x2="420.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="420.4" y1="114.0" x2="427.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="427.6" y1="114.0" x2="434.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="434.8" y1="114.0" x2="442.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="442.0" y1="114.0" x2="449.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="449.2" y1="114.0" x2="456.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="456.4" y1="114.0" x2="463.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="463.6" y1="114.0" x2="470.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="470.8" y1="114.0" x2="478.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="478.0" y1="114.0" x2="481.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="481.6" y1="106.0" x2="481.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="517.6" y1="106.0" x2="517.6" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="122.0" x2="13.6" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="122.0" x2="78.4" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="236.8" y1="122.0" x2="236.8" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="409.6" y1="122.0" x2="409.6" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="517.6" y1="122.0" x2="517.6" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="138.0" x2="13.6" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="517.6" y1="138.0" x2="517.6" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="154.0" x2="13.6" y2="170.0" stroke="#10373E" stroke-width="1.3"/><line x1="510.4" y1="154.0" x2="510.4" y2="170.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="170.0" x2="13.6" y2="186.0" stroke="#10373E" stroke-width="1.3"/><line x1="510.4" y1="170.0" x2="510.4" y2="186.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="186.0" x2="13.6" y2="202.0" stroke="#10373E" stroke-width="1.3"/><line x1="510.4" y1="186.0" x2="510.4" y2="202.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="202.0" x2="13.6" y2="218.0" stroke="#10373E" stroke-width="1.3"/><line x1="517.6" y1="202.0" x2="517.6" y2="218.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="226.0" x2="17.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="218.0" x2="13.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="226.0" x2="24.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="226.0" x2="31.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="226.0" x2="38.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="226.0" x2="46.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="226.0" x2="53.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="226.0" x2="60.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="226.0" x2="67.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="226.0" x2="74.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="226.0" x2="82.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="226.0" x2="89.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="226.0" x2="96.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="226.0" x2="103.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="226.0" x2="110.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="226.0" x2="118.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="226.0" x2="125.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="226.0" x2="132.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="226.0" x2="139.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="226.0" x2="146.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="226.0" x2="154.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="226.0" x2="161.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="226.0" x2="168.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="226.0" x2="175.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="226.0" x2="182.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="226.0" x2="190.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="226.0" x2="197.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="226.0" x2="204.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="226.0" x2="211.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="226.0" x2="218.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="226.0" x2="226.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="226.0" x2="233.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="226.0" x2="240.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="226.0" x2="247.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="226.0" x2="254.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="226.0" x2="262.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="226.0" x2="269.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="226.0" x2="276.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="226.0" x2="283.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="226.0" x2="290.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="226.0" x2="298.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="226.0" x2="305.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="226.0" x2="312.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="226.0" x2="319.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="226.0" x2="326.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="226.0" x2="334.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="226.0" x2="341.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="226.0" x2="348.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="348.4" y1="226.0" x2="355.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="226.0" x2="362.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="362.8" y1="226.0" x2="370.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="370.0" y1="226.0" x2="377.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="377.2" y1="226.0" x2="384.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="384.4" y1="226.0" x2="391.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="391.6" y1="226.0" x2="398.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="398.8" y1="226.0" x2="406.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="406.0" y1="226.0" x2="413.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="413.2" y1="226.0" x2="420.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="420.4" y1="226.0" x2="427.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="427.6" y1="226.0" x2="434.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="434.8" y1="226.0" x2="442.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="442.0" y1="226.0" x2="449.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="449.2" y1="226.0" x2="456.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="456.4" y1="226.0" x2="463.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="463.6" y1="226.0" x2="470.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="470.8" y1="226.0" x2="478.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="478.0" y1="226.0" x2="485.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="485.2" y1="226.0" x2="492.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="492.4" y1="226.0" x2="499.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="499.6" y1="226.0" x2="506.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="506.8" y1="226.0" x2="514.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="514.0" y1="226.0" x2="517.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="517.6" y1="218.0" x2="517.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><text x="175.6" y="38.0" font-size="9.5" fill="#10373E">MULTI-TENANCY</text><text x="276.4" y="38.0" font-size="9.5" fill="#10373E">SYSTEM</text><text x="326.8" y="38.0" font-size="9.5" fill="#10373E">v1.1.0</text><text x="60.4" y="102.0" font-size="9.5" fill="#10373E">ADMIN</text><text x="218.8" y="102.0" font-size="9.5" fill="#10373E">OWNER</text><text x="370.0" y="102.0" font-size="9.5" fill="#10373E">SUPER</text><text x="413.2" y="102.0" font-size="9.5" fill="#10373E">USER</text><text x="74.8" y="150.0" font-size="9.5" fill="#10373E">v</text><text x="233.2" y="150.0" font-size="9.5" fill="#10373E">v</text><text x="406.0" y="150.0" font-size="9.5" fill="#10373E">v</text><text x="38.8" y="166.0" font-size="9.5" fill="#10373E">All</text><text x="67.6" y="166.0" font-size="9.5" fill="#10373E">Tenants</text><text x="190.0" y="166.0" font-size="9.5" fill="#10373E">Full</text><text x="226.0" y="166.0" font-size="9.5" fill="#10373E">Control</text><text x="362.8" y="166.0" font-size="9.5" fill="#10373E">Assigned</text><text x="427.6" y="166.0" font-size="9.5" fill="#10373E">Tenants</text><text x="38.8" y="182.0" font-size="9.5" fill="#10373E">Create/Delete</text><text x="190.0" y="182.0" font-size="9.5" fill="#10373E">Assign</text><text x="240.4" y="182.0" font-size="9.5" fill="#10373E">Users</text><text x="362.8" y="182.0" font-size="9.5" fill="#10373E">Assign</text><text x="413.2" y="182.0" font-size="9.5" fill="#10373E">Users</text><text x="38.8" y="198.0" font-size="9.5" fill="#10373E">Status</text><text x="89.2" y="198.0" font-size="9.5" fill="#10373E">Control</text><text x="190.0" y="198.0" font-size="9.5" fill="#10373E">Manage</text><text x="240.4" y="198.0" font-size="9.5" fill="#10373E">Settings</text><text x="362.8" y="198.0" font-size="9.5" fill="#10373E">Manage</text><text x="413.2" y="198.0" font-size="9.5" fill="#10373E">Branding</text></svg></div>

---

## What's New in v1.2.0

- **Domain Routing (Issue #85)**: Automatic tenant resolution from subdomain/custom domain
- **Subdomain Support**: Access tenants via `tenant.heritage.example.com`
- **Custom Domain Support**: Use institutional domains like `archive.institution.org`
- **Unknown Domain Handling**: Friendly error pages for unrecognized domains
- **Tenant Status Management**: Activate, suspend, and manage trial periods
- **Extended Roles**: Owner, Super User, Editor, Contributor, Viewer
- **Admin Dashboard**: Statistics and comprehensive tenant management
- **Trial Management**: Configurable trial periods with extension capability

---

## User Roles

- USER ROLE HIERARCHY
- 1. ADMINISTRATOR (Heratio Admin)
- - Full access to all tenants
- - Create, edit, suspend, delete tenants
- - Assign any role including Owner
- - Manage tenant status and settings
- 2. OWNER
- - Full control over their tenant
- - Assign Super Users and below
- - Manage all tenant settings
- - Cannot be demoted if last owner
- 3. SUPER USER
- - Assign Editors, Contributors, Viewers
- - Manage tenant branding
- - Cannot assign Owners or other Super Users
- 4. EDITOR
- - Edit content within tenant
- - Cannot manage users
- 5. CONTRIBUTOR
- - Add content within tenant
- - Limited editing
- 6. VIEWER
- - Read-only access

---

## Tenant Status

Tenants can be in one of three states:

| Status | Badge | Description |
|--------|-------|-------------|
| **Active** | Green | Full access to all features |
| **Trial** | Blue | Time-limited access (default 14 days) |
| **Suspended** | Red | No access - users cannot log in |

> **Note:** *Trial* is a display/filter state, not a status you pick when creating a tenant. The **Create Tenant** form's *Initial Status* only offers **Active** or **Suspended** (the underlying `is_active` flag). A tenant shows the **Trial** badge when its `status`/`trial_ends_at` data marks it as on trial; administrators manage that afterwards via the trial actions below.

### Trial Period

- Trial end date is shown in the admin dashboard
- Administrators can extend trials or activate tenants
- Expired trials show an "Expired" badge but remain accessible until suspended

---

## Domain Routing

Tenants can be accessed directly via subdomain or custom domain, without requiring users to manually switch tenants.

### How Domain Resolution Works

- DOMAIN RESOLUTION ORDER
- 1. CUSTOM DOMAIN CHECK
- archive.institution.org → Match tenant with this domain
- 2. SUBDOMAIN CHECK
- tenant.heritage.example.com → Extract "tenant" subdomain
- → Find tenant with this subdomain
- 3. SESSION FALLBACK
- Use previously selected tenant from session
- 4. UNKNOWN DOMAIN
- Show error page or redirect to main site

### Subdomain Access

Access tenants using subdomains of your main domain:

| Subdomain | Resolves To |
|-----------|-------------|
| `national-archives.heritage.example.com` | National Archives tenant |
| `city-library.heritage.example.com` | City Library tenant |
| `museum.heritage.example.com` | Museum tenant |

**Setup (Administrator):**
1. Create wildcard DNS: `*.heritage.example.com → server IP`
2. Obtain wildcard SSL certificate
3. In Admin > Tenants, set the subdomain field for each tenant

### Custom Domain Access

Allow tenants to use their own institutional domains:

| Custom Domain | Resolves To |
|---------------|-------------|
| `archive.nationalarchives.gov` | National Archives tenant |
| `collections.citymuseum.org` | City Museum tenant |

**Setup (Administrator):**
1. Tenant points their domain to your server (DNS)
2. Obtain SSL certificate for the domain
3. Configure nginx server block
4. In Admin > Tenants, set the custom domain field

### Unknown Domain Handling

When a request comes from an unrecognized domain:

**For Unknown Subdomains:**
- TENANT NOT FOUND
- The tenant "unknown-tenant" does not
- exist or may have been removed.
- [Go to Main Site]  [Go Back]

**For Unknown Custom Domains:**
- DOMAIN NOT CONFIGURED
- This domain is not configured for
- any tenant in our system.
- Contact administrator for setup.
- [Go to Main Site]  [Go Back]

---

## Administrator Functions

### Accessing Tenant Administration

1. Log in as an administrator
2. Navigate to **Admin > Tenants** or `/admin/tenants`

### Dashboard Overview

The admin dashboard shows:

<div style="overflow-x:auto;margin:1rem 0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 517 244" style="max-width:100%;height:auto;font-family:ui-monospace,Menlo,Consolas,monospace"><rect x="0.5" y="0.5" width="516" height="243" rx="8" fill="#f7faf9" stroke="#d8e6e3"/><line x1="13.6" y1="18.0" x2="17.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="18.0" x2="13.6" y2="26.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="18.0" x2="24.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="18.0" x2="31.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="18.0" x2="38.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="18.0" x2="46.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="18.0" x2="53.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="18.0" x2="60.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="18.0" x2="67.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="18.0" x2="74.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="18.0" x2="82.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="18.0" x2="89.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="18.0" x2="96.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="18.0" x2="103.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="18.0" x2="110.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="18.0" x2="118.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="18.0" x2="125.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="18.0" x2="132.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="18.0" x2="139.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="18.0" x2="146.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="18.0" x2="154.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="18.0" x2="161.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="18.0" x2="168.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="18.0" x2="175.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="18.0" x2="182.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="18.0" x2="190.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="18.0" x2="197.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="18.0" x2="204.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="18.0" x2="211.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="18.0" x2="218.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="18.0" x2="226.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="18.0" x2="233.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="18.0" x2="240.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="18.0" x2="247.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="18.0" x2="254.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="18.0" x2="262.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="18.0" x2="269.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="18.0" x2="276.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="18.0" x2="283.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="18.0" x2="290.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="18.0" x2="298.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="18.0" x2="305.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="18.0" x2="312.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="18.0" x2="319.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="18.0" x2="326.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="18.0" x2="334.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="18.0" x2="341.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="18.0" x2="348.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="348.4" y1="18.0" x2="355.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="18.0" x2="362.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="362.8" y1="18.0" x2="370.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="370.0" y1="18.0" x2="377.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="377.2" y1="18.0" x2="384.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="384.4" y1="18.0" x2="391.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="391.6" y1="18.0" x2="398.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="398.8" y1="18.0" x2="406.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="406.0" y1="18.0" x2="413.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="413.2" y1="18.0" x2="420.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="420.4" y1="18.0" x2="427.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="427.6" y1="18.0" x2="434.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="434.8" y1="18.0" x2="442.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="442.0" y1="18.0" x2="449.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="449.2" y1="18.0" x2="456.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="456.4" y1="18.0" x2="463.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="463.6" y1="18.0" x2="470.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="470.8" y1="18.0" x2="478.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="478.0" y1="18.0" x2="485.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="485.2" y1="18.0" x2="492.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="492.4" y1="18.0" x2="496.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="26.0" x2="13.6" y2="42.0" stroke="#10373E" stroke-width="1.3"/><line x1="503.2" y1="26.0" x2="503.2" y2="42.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="50.0" x2="17.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="42.0" x2="13.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="50.0" x2="13.6" y2="58.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="50.0" x2="24.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="50.0" x2="31.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="50.0" x2="38.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="50.0" x2="46.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="50.0" x2="53.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="50.0" x2="60.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="50.0" x2="67.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="50.0" x2="74.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="50.0" x2="82.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="50.0" x2="89.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="50.0" x2="96.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="50.0" x2="103.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="50.0" x2="110.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="50.0" x2="118.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="50.0" x2="125.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="50.0" x2="132.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="50.0" x2="139.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="50.0" x2="146.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="50.0" x2="154.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="50.0" x2="161.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="50.0" x2="168.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="50.0" x2="175.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="50.0" x2="182.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="50.0" x2="190.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="50.0" x2="197.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="50.0" x2="204.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="50.0" x2="211.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="50.0" x2="218.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="50.0" x2="226.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="50.0" x2="233.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="50.0" x2="240.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="50.0" x2="247.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="50.0" x2="254.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="50.0" x2="262.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="50.0" x2="269.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="50.0" x2="276.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="50.0" x2="283.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="50.0" x2="290.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="50.0" x2="298.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="50.0" x2="305.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="50.0" x2="312.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="50.0" x2="319.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="50.0" x2="326.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="50.0" x2="334.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="50.0" x2="341.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="50.0" x2="348.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="348.4" y1="50.0" x2="355.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="50.0" x2="362.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="362.8" y1="50.0" x2="370.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="370.0" y1="50.0" x2="377.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="377.2" y1="50.0" x2="384.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="384.4" y1="50.0" x2="391.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="391.6" y1="50.0" x2="398.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="398.8" y1="50.0" x2="406.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="406.0" y1="50.0" x2="413.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="413.2" y1="50.0" x2="420.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="420.4" y1="50.0" x2="427.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="427.6" y1="50.0" x2="434.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="434.8" y1="50.0" x2="442.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="442.0" y1="50.0" x2="449.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="449.2" y1="50.0" x2="456.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="456.4" y1="50.0" x2="463.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="463.6" y1="50.0" x2="470.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="470.8" y1="50.0" x2="478.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="478.0" y1="50.0" x2="485.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="485.2" y1="50.0" x2="492.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="492.4" y1="50.0" x2="496.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="58.0" x2="13.6" y2="74.0" stroke="#10373E" stroke-width="1.3"/><line x1="503.2" y1="58.0" x2="503.2" y2="74.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="74.0" x2="13.6" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="503.2" y1="74.0" x2="503.2" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="90.0" x2="13.6" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="503.2" y1="90.0" x2="503.2" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="106.0" x2="13.6" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="114.0" x2="38.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="114.0" x2="35.2" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="114.0" x2="46.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="114.0" x2="53.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="114.0" x2="60.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="114.0" x2="67.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="114.0" x2="74.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="114.0" x2="82.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="114.0" x2="89.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="114.0" x2="96.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="114.0" x2="100.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="114.0" x2="100.0" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="114.0" x2="125.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="114.0" x2="121.6" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="114.0" x2="132.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="114.0" x2="139.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="114.0" x2="146.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="114.0" x2="154.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="114.0" x2="161.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="114.0" x2="168.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="114.0" x2="175.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="114.0" x2="182.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="114.0" x2="186.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="114.0" x2="186.4" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="114.0" x2="211.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="114.0" x2="208.0" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="114.0" x2="218.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="114.0" x2="226.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="114.0" x2="233.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="114.0" x2="240.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="114.0" x2="247.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="114.0" x2="254.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="114.0" x2="262.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="114.0" x2="269.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="114.0" x2="272.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="272.8" y1="114.0" x2="272.8" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="294.4" y1="114.0" x2="298.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="294.4" y1="114.0" x2="294.4" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="114.0" x2="305.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="114.0" x2="312.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="114.0" x2="319.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="114.0" x2="326.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="114.0" x2="334.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="114.0" x2="341.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="114.0" x2="348.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="348.4" y1="114.0" x2="355.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="114.0" x2="359.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="359.2" y1="114.0" x2="359.2" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="380.8" y1="114.0" x2="384.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="380.8" y1="114.0" x2="380.8" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="384.4" y1="114.0" x2="391.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="391.6" y1="114.0" x2="398.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="398.8" y1="114.0" x2="406.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="406.0" y1="114.0" x2="413.2" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="413.2" y1="114.0" x2="420.4" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="420.4" y1="114.0" x2="427.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="427.6" y1="114.0" x2="434.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="434.8" y1="114.0" x2="442.0" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="442.0" y1="114.0" x2="445.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="445.6" y1="114.0" x2="445.6" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="503.2" y1="106.0" x2="503.2" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="122.0" x2="13.6" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="122.0" x2="35.2" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="122.0" x2="100.0" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="122.0" x2="121.6" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="122.0" x2="186.4" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="122.0" x2="208.0" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="272.8" y1="122.0" x2="272.8" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="294.4" y1="122.0" x2="294.4" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="359.2" y1="122.0" x2="359.2" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="380.8" y1="122.0" x2="380.8" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="445.6" y1="122.0" x2="445.6" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="503.2" y1="122.0" x2="503.2" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="138.0" x2="13.6" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="138.0" x2="35.2" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="138.0" x2="100.0" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="138.0" x2="121.6" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="138.0" x2="186.4" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="138.0" x2="208.0" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="272.8" y1="138.0" x2="272.8" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="294.4" y1="138.0" x2="294.4" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="359.2" y1="138.0" x2="359.2" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="380.8" y1="138.0" x2="380.8" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="445.6" y1="138.0" x2="445.6" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="503.2" y1="138.0" x2="503.2" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="154.0" x2="13.6" y2="170.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="162.0" x2="38.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="154.0" x2="35.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="162.0" x2="46.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="162.0" x2="53.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="162.0" x2="60.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="162.0" x2="67.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="162.0" x2="74.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="162.0" x2="82.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="162.0" x2="89.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="162.0" x2="96.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="162.0" x2="100.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="154.0" x2="100.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="162.0" x2="125.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="154.0" x2="121.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="162.0" x2="132.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="162.0" x2="139.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="162.0" x2="146.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="162.0" x2="154.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="162.0" x2="161.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="162.0" x2="168.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="162.0" x2="175.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="162.0" x2="182.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="162.0" x2="186.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="154.0" x2="186.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="162.0" x2="211.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="154.0" x2="208.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="162.0" x2="218.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="162.0" x2="226.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="162.0" x2="233.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="162.0" x2="240.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="162.0" x2="247.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="162.0" x2="254.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="162.0" x2="262.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="162.0" x2="269.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="162.0" x2="272.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="272.8" y1="154.0" x2="272.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="294.4" y1="162.0" x2="298.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="294.4" y1="154.0" x2="294.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="162.0" x2="305.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="162.0" x2="312.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="162.0" x2="319.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="162.0" x2="326.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="162.0" x2="334.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="162.0" x2="341.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="162.0" x2="348.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="348.4" y1="162.0" x2="355.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="162.0" x2="359.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="359.2" y1="154.0" x2="359.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="380.8" y1="162.0" x2="384.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="380.8" y1="154.0" x2="380.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="384.4" y1="162.0" x2="391.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="391.6" y1="162.0" x2="398.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="398.8" y1="162.0" x2="406.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="406.0" y1="162.0" x2="413.2" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="413.2" y1="162.0" x2="420.4" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="420.4" y1="162.0" x2="427.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="427.6" y1="162.0" x2="434.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="434.8" y1="162.0" x2="442.0" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="442.0" y1="162.0" x2="445.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="445.6" y1="154.0" x2="445.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="503.2" y1="154.0" x2="503.2" y2="170.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="170.0" x2="13.6" y2="186.0" stroke="#10373E" stroke-width="1.3"/><line x1="503.2" y1="170.0" x2="503.2" y2="186.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="186.0" x2="13.6" y2="202.0" stroke="#10373E" stroke-width="1.3"/><line x1="503.2" y1="186.0" x2="503.2" y2="202.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="202.0" x2="13.6" y2="218.0" stroke="#10373E" stroke-width="1.3"/><line x1="503.2" y1="202.0" x2="503.2" y2="218.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="226.0" x2="17.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="218.0" x2="13.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="226.0" x2="24.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="226.0" x2="31.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="226.0" x2="38.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="226.0" x2="46.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="226.0" x2="53.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="226.0" x2="60.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="226.0" x2="67.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="226.0" x2="74.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="226.0" x2="82.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="226.0" x2="89.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="226.0" x2="96.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="226.0" x2="103.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="226.0" x2="110.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="226.0" x2="118.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="226.0" x2="125.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="226.0" x2="132.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="226.0" x2="139.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="226.0" x2="146.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="226.0" x2="154.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="226.0" x2="161.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="226.0" x2="168.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="226.0" x2="175.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="226.0" x2="182.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="226.0" x2="190.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="226.0" x2="197.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="226.0" x2="204.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="226.0" x2="211.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="226.0" x2="218.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="226.0" x2="226.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="226.0" x2="233.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="226.0" x2="240.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="226.0" x2="247.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="226.0" x2="254.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="226.0" x2="262.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="226.0" x2="269.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="226.0" x2="276.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="226.0" x2="283.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="226.0" x2="290.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="226.0" x2="298.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="226.0" x2="305.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="226.0" x2="312.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="226.0" x2="319.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="226.0" x2="326.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="226.0" x2="334.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="226.0" x2="341.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="226.0" x2="348.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="348.4" y1="226.0" x2="355.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="355.6" y1="226.0" x2="362.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="362.8" y1="226.0" x2="370.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="370.0" y1="226.0" x2="377.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="377.2" y1="226.0" x2="384.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="384.4" y1="226.0" x2="391.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="391.6" y1="226.0" x2="398.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="398.8" y1="226.0" x2="406.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="406.0" y1="226.0" x2="413.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="413.2" y1="226.0" x2="420.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="420.4" y1="226.0" x2="427.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="427.6" y1="226.0" x2="434.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="434.8" y1="226.0" x2="442.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="442.0" y1="226.0" x2="449.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="449.2" y1="226.0" x2="456.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="456.4" y1="226.0" x2="463.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="463.6" y1="226.0" x2="470.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="470.8" y1="226.0" x2="478.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="478.0" y1="226.0" x2="485.2" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="485.2" y1="226.0" x2="492.4" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="492.4" y1="226.0" x2="496.0" y2="226.0" stroke="#10373E" stroke-width="1.3"/><text x="161.2" y="38.0" font-size="9.5" fill="#10373E">TENANT</text><text x="211.6" y="38.0" font-size="9.5" fill="#10373E">ADMINISTRATION</text><text x="31.6" y="86.0" font-size="9.5" fill="#10373E">[</text><text x="46.0" y="86.0" font-size="9.5" fill="#10373E">]</text><text x="60.4" y="86.0" font-size="9.5" fill="#10373E">Create</text><text x="110.8" y="86.0" font-size="9.5" fill="#10373E">Tenant</text><text x="46.0" y="134.0" font-size="9.5" fill="#10373E">TOTAL</text><text x="132.4" y="134.0" font-size="9.5" fill="#10373E">ACTIVE</text><text x="218.8" y="134.0" font-size="9.5" fill="#10373E">TRIAL</text><text x="298.0" y="134.0" font-size="9.5" fill="#10373E">SUSPEND</text><text x="384.4" y="134.0" font-size="9.5" fill="#10373E">EXPIRING</text><text x="60.4" y="150.0" font-size="9.5" fill="#10373E">12</text><text x="154.0" y="150.0" font-size="9.5" fill="#10373E">8</text><text x="233.2" y="150.0" font-size="9.5" fill="#10373E">3</text><text x="319.6" y="150.0" font-size="9.5" fill="#10373E">1</text><text x="406.0" y="150.0" font-size="9.5" fill="#10373E">2</text><text x="31.6" y="198.0" font-size="9.5" fill="#10373E">[Filter:</text><text x="96.4" y="198.0" font-size="9.5" fill="#10373E">All</text><text x="125.2" y="198.0" font-size="9.5" fill="#10373E">Status</text><text x="175.6" y="198.0" font-size="9.5" fill="#10373E">v]</text><text x="197.2" y="198.0" font-size="9.5" fill="#10373E">[Search...</text><text x="312.4" y="198.0" font-size="9.5" fill="#10373E">]</text><text x="326.8" y="198.0" font-size="9.5" fill="#10373E">[Search]</text></svg></div>

### Creating a New Tenant

1. Click **Create Tenant** button
2. Fill in the form:

- CREATE TENANT
- Tenant Name: [My Organization           ]
- Code:        [my-organization           ]
- Domain:      [myorg.example.com         ]
- Subdomain:   [myorg                     ]
- Link to Repository: [Select...         v]
- Initial Status:     [Active            v]
- Contact Name:  [John Smith              ]
- Contact Email: [john@example.com        ]
- Assign Owner:  [Select User...         v]
- [Cancel]                    [Create Tenant]

3. Click **Create Tenant**

### Managing Tenant Status

From the tenant list, use the action buttons:

| Button | Action |
|--------|--------|
| Green Check | Activate tenant (end trial/unsuspend) |
| Clock | Extend trial period |
| Yellow Ban | Suspend tenant |
| Trash | Delete tenant |

### Suspending a Tenant

1. Click the yellow ban icon next to the tenant
2. Enter an optional reason for suspension
3. Click **Suspend Tenant**

Users of a suspended tenant will no longer be able to access the system.

### Extending a Trial

1. Click the clock icon next to a trial tenant
2. Enter the number of additional days
3. Click **Extend Trial**

### Editing a Tenant

1. Click the edit icon next to a tenant
2. Modify the tenant details
3. Manage users in the right panel:
   - Change roles using the dropdown
   - Remove users with the X button
   - Add users using the form at the bottom
4. Click **Save Changes**

---

## Managing Tenant Users

### Adding a User to a Tenant

1. Navigate to the tenant edit page
2. In the "Add User" section:
   - Select a user from the dropdown
   - Select their role
   - Click **Add**

### Changing a User's Role

1. Navigate to the tenant edit page
2. Find the user in the list
3. Use the role dropdown to change their role
4. The change is saved automatically

### Removing a User

1. Navigate to the tenant edit page
2. Click the X button next to the user
3. Confirm the removal

**Note:** You cannot remove the last Owner from a tenant.

---

## Branding Your Tenant

Super Users and above can customize the appearance of their tenant.

### Accessing Branding Settings

1. Use the Tenant Switcher dropdown
2. Click **Branding**
3. Or navigate to `/tenant/{id}/branding`

### Logo Upload

1. Click **Choose File** in the Logo section
2. Select an image file
3. Click **Upload**

**Supported formats:** PNG, JPEG, GIF, SVG, WebP
**Maximum size:** 2MB

### Color Configuration

| Setting | Description |
|---------|-------------|
| Primary Color | Main brand color |
| Secondary Color | Secondary brand color |
| Header Background | Navigation bar background |
| Header Text | Navigation text color |
| Link Color | Text link color |
| Button Color | Action button color |

### Custom CSS

For advanced customization:

```css
/* Example custom CSS */
.tenant-header {
  border-bottom: 3px solid var(--tenant-primary-color);
}
```

**Maximum:** 10,000 characters

### Save and Preview

1. Review changes in the preview section
2. Click **Save Branding**

---

## Switching Between Tenants

### Using the Tenant Switcher

1. Look for the building icon in the navigation bar
2. Click to open the dropdown
3. Select a tenant to switch

- [Building] My Organization      v
- [Globe] All Tenants
- ------------------------------------
- [Star] My Organization
- [Building] Partner Archive
- [Building] City Library
- ------------------------------------
- [Cog] Manage Tenants
- [Users] Manage Users
- [Palette] Branding

### View All Mode (Administrators Only)

Administrators can view all records across tenants by selecting **All Tenants**.

---

## URL Reference

| Function | URL |
|----------|-----|
| Tenant Dashboard | `/admin/tenants` |
| Create Tenant | `/admin/tenants/create` |
| Edit Tenant | `/admin/tenants/{id}/edit-tenant` |
| User Management (Legacy) | `/tenant/{id}/users` |
| Branding | `/tenant/{id}/branding` |
| Switch Tenant | `/tenant/switch/{id}` |
| View All (Admin) | `/tenant/switch/all` |

### Domain-Based Access

| Access Method | URL Pattern |
|--------------|-------------|
| Subdomain Access | `https://{subdomain}.heritage.example.com/` |
| Custom Domain | `https://archive.institution.org/` |
| Main Site | `https://heritage.example.com/` |

---

## Common Tasks Quick Reference

### For Administrators

| Task | Steps |
|------|-------|
| Create tenant | Admin > Tenants > Create Tenant |
| Activate tenant | Admin > Tenants > Green check icon |
| Suspend tenant | Admin > Tenants > Yellow ban icon |
| Extend trial | Admin > Tenants > Clock icon |
| Delete tenant | Admin > Tenants > Trash icon |
| Assign owner | Edit Tenant > Add User > Role: Owner |

### For Owners/Super Users

| Task | Steps |
|------|-------|
| Add user | Edit Tenant > Add User section |
| Change role | Edit Tenant > User list > Role dropdown |
| Remove user | Edit Tenant > User list > X button |
| Update branding | Tenant Switcher > Branding |
| Upload logo | Branding > Logo section > Upload |

### For All Users

| Task | Steps |
|------|-------|
| Switch tenant | Tenant Switcher dropdown > Select |
| View current tenant | Check navigation bar |

---

## Troubleshooting

### Cannot access tenant

1. Your tenant may be suspended - contact administrator
2. Your trial may have expired - contact administrator
3. You may not be assigned - contact your tenant owner

### Cannot see other users to assign

1. Only active users appear in the list
2. Users already assigned to the tenant won't appear

### Branding not appearing

1. Clear browser cache
2. Hard refresh (Ctrl+Shift+R)
3. Wait a few seconds for regeneration

### Cannot delete tenant

1. Remove all users first
2. Only administrators can delete tenants

---

## Need Help?

Contact your system administrator for assistance.

---

*Part of the Heratio AHG Framework*
