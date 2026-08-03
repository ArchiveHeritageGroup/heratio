> Heratio Help Center article. Category: Technical.

# Heratio — Nginx Security Hardening Guide

**Version:** 1.0
**Date:** 2026-03-07
**Author:** The Archive and Heritage Group (Pty) Ltd
**Framework Version:** 2.8.2

---

## 1. Overview

This document provides security hardening instructions for public-facing Heratio deployments. Heratio runs on Symfony 1.x (end-of-life since 2012), which introduces inherent risk. The mitigation strategy is **defense-in-depth at the Nginx layer** — blocking attack vectors before they reach the application.

### Architecture Security Position

Heratio uses a hybrid architecture:

<div style="overflow-x:auto;margin:1rem 0"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 358 372" style="max-width:100%;height:auto;font-family:ui-monospace,Menlo,Consolas,monospace"><rect x="0.5" y="0.5" width="357" height="371" rx="8" fill="#f7faf9" stroke="#d8e6e3"/><line x1="13.6" y1="18.0" x2="17.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="18.0" x2="13.6" y2="26.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="18.0" x2="20.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="20.8" y1="18.0" x2="24.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="18.0" x2="28.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="18.0" x2="31.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="18.0" x2="35.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="18.0" x2="38.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="18.0" x2="42.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="18.0" x2="46.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="18.0" x2="49.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="18.0" x2="53.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="18.0" x2="56.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="18.0" x2="60.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="18.0" x2="64.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="64.0" y1="18.0" x2="67.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="18.0" x2="71.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="18.0" x2="74.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="18.0" x2="78.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="18.0" x2="82.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="18.0" x2="85.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="85.6" y1="18.0" x2="89.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="18.0" x2="92.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="92.8" y1="18.0" x2="96.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="18.0" x2="100.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="18.0" x2="103.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="18.0" x2="107.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="107.2" y1="18.0" x2="110.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="18.0" x2="114.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="114.4" y1="18.0" x2="118.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="18.0" x2="121.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="18.0" x2="125.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="18.0" x2="128.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="128.8" y1="18.0" x2="132.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="18.0" x2="136.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="18.0" x2="139.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="18.0" x2="143.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="143.2" y1="18.0" x2="146.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="18.0" x2="150.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="150.4" y1="18.0" x2="154.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="18.0" x2="157.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="18.0" x2="161.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="18.0" x2="164.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="18.0" x2="168.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="18.0" x2="172.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="18.0" x2="175.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="18.0" x2="179.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="18.0" x2="182.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="18.0" x2="186.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="18.0" x2="190.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="18.0" x2="193.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="18.0" x2="197.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="18.0" x2="200.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="18.0" x2="204.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="18.0" x2="208.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="18.0" x2="211.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="18.0" x2="215.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="215.2" y1="18.0" x2="218.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="18.0" x2="222.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="222.4" y1="18.0" x2="226.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="18.0" x2="229.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="229.6" y1="18.0" x2="233.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="18.0" x2="236.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="236.8" y1="18.0" x2="240.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="18.0" x2="244.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="244.0" y1="18.0" x2="247.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="18.0" x2="251.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="251.2" y1="18.0" x2="254.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="18.0" x2="258.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="258.4" y1="18.0" x2="262.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="18.0" x2="265.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="265.6" y1="18.0" x2="269.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="18.0" x2="272.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="272.8" y1="18.0" x2="276.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="18.0" x2="280.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="280.0" y1="18.0" x2="283.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="18.0" x2="287.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="287.2" y1="18.0" x2="290.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="18.0" x2="294.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="294.4" y1="18.0" x2="298.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="18.0" x2="301.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="301.6" y1="18.0" x2="305.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="18.0" x2="308.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="308.8" y1="18.0" x2="312.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="18.0" x2="316.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="316.0" y1="18.0" x2="319.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="18.0" x2="323.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="323.2" y1="18.0" x2="326.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="18.0" x2="330.4" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="330.4" y1="18.0" x2="334.0" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="18.0" x2="337.6" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="337.6" y1="18.0" x2="341.2" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="18.0" x2="344.8" y2="18.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="18.0" x2="344.8" y2="26.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="26.0" x2="13.6" y2="34.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="34.0" x2="13.6" y2="42.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="26.0" x2="344.8" y2="34.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="34.0" x2="344.8" y2="42.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="50.0" x2="17.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="42.0" x2="13.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="50.0" x2="20.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="20.8" y1="50.0" x2="24.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="50.0" x2="28.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="50.0" x2="31.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="50.0" x2="35.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="50.0" x2="38.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="50.0" x2="42.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="50.0" x2="46.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="50.0" x2="49.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="50.0" x2="53.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="50.0" x2="56.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="50.0" x2="60.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="50.0" x2="64.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="64.0" y1="50.0" x2="67.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="50.0" x2="71.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="50.0" x2="74.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="50.0" x2="78.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="50.0" x2="82.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="50.0" x2="85.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="85.6" y1="50.0" x2="89.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="50.0" x2="92.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="92.8" y1="50.0" x2="96.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="50.0" x2="100.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="50.0" x2="103.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="50.0" x2="107.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="107.2" y1="50.0" x2="110.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="50.0" x2="114.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="114.4" y1="50.0" x2="118.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="50.0" x2="121.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="50.0" x2="125.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="50.0" x2="121.6" y2="58.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="50.0" x2="128.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="128.8" y1="50.0" x2="132.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="50.0" x2="136.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="50.0" x2="139.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="50.0" x2="143.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="143.2" y1="50.0" x2="146.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="50.0" x2="150.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="150.4" y1="50.0" x2="154.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="50.0" x2="157.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="50.0" x2="161.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="50.0" x2="164.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="50.0" x2="168.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="50.0" x2="172.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="50.0" x2="175.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="50.0" x2="179.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="50.0" x2="182.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="50.0" x2="186.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="50.0" x2="190.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="50.0" x2="193.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="50.0" x2="197.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="50.0" x2="200.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="50.0" x2="204.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="50.0" x2="208.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="50.0" x2="211.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="50.0" x2="215.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="215.2" y1="50.0" x2="218.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="50.0" x2="222.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="222.4" y1="50.0" x2="226.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="50.0" x2="229.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="229.6" y1="50.0" x2="233.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="50.0" x2="236.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="236.8" y1="50.0" x2="240.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="50.0" x2="244.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="244.0" y1="50.0" x2="247.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="50.0" x2="251.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="251.2" y1="50.0" x2="254.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="50.0" x2="258.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="258.4" y1="50.0" x2="262.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="50.0" x2="265.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="265.6" y1="50.0" x2="269.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="50.0" x2="272.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="272.8" y1="50.0" x2="276.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="50.0" x2="280.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="280.0" y1="50.0" x2="283.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="50.0" x2="287.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="287.2" y1="50.0" x2="290.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="50.0" x2="294.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="294.4" y1="50.0" x2="298.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="50.0" x2="301.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="301.6" y1="50.0" x2="305.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="50.0" x2="308.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="308.8" y1="50.0" x2="312.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="50.0" x2="316.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="316.0" y1="50.0" x2="319.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="50.0" x2="323.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="323.2" y1="50.0" x2="326.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="50.0" x2="330.4" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="330.4" y1="50.0" x2="334.0" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="50.0" x2="337.6" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="337.6" y1="50.0" x2="341.2" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="50.0" x2="344.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="42.0" x2="344.8" y2="50.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="58.0" x2="121.6" y2="66.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="66.0" x2="121.6" y2="74.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="82.0" x2="17.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="82.0" x2="13.6" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="82.0" x2="20.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="20.8" y1="82.0" x2="24.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="82.0" x2="28.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="82.0" x2="31.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="82.0" x2="35.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="82.0" x2="38.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="82.0" x2="42.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="82.0" x2="46.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="82.0" x2="49.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="82.0" x2="53.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="82.0" x2="56.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="82.0" x2="60.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="82.0" x2="64.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="64.0" y1="82.0" x2="67.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="82.0" x2="71.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="82.0" x2="74.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="82.0" x2="78.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="82.0" x2="82.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="82.0" x2="85.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="85.6" y1="82.0" x2="89.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="82.0" x2="92.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="92.8" y1="82.0" x2="96.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="82.0" x2="100.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="82.0" x2="103.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="82.0" x2="107.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="107.2" y1="82.0" x2="110.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="82.0" x2="114.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="114.4" y1="82.0" x2="118.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="82.0" x2="128.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="128.8" y1="82.0" x2="132.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="82.0" x2="136.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="82.0" x2="139.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="82.0" x2="143.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="143.2" y1="82.0" x2="146.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="82.0" x2="150.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="150.4" y1="82.0" x2="154.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="82.0" x2="157.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="82.0" x2="161.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="82.0" x2="164.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="82.0" x2="168.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="82.0" x2="172.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="82.0" x2="175.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="82.0" x2="179.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="82.0" x2="182.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="82.0" x2="186.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="82.0" x2="190.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="82.0" x2="193.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="82.0" x2="197.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="82.0" x2="200.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="82.0" x2="204.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="82.0" x2="208.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="82.0" x2="211.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="82.0" x2="215.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="215.2" y1="82.0" x2="218.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="82.0" x2="222.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="222.4" y1="82.0" x2="226.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="82.0" x2="229.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="229.6" y1="82.0" x2="233.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="82.0" x2="236.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="236.8" y1="82.0" x2="240.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="82.0" x2="244.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="244.0" y1="82.0" x2="247.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="82.0" x2="251.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="251.2" y1="82.0" x2="254.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="82.0" x2="258.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="258.4" y1="82.0" x2="262.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="82.0" x2="265.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="265.6" y1="82.0" x2="269.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="82.0" x2="272.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="272.8" y1="82.0" x2="276.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="82.0" x2="280.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="280.0" y1="82.0" x2="283.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="82.0" x2="287.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="287.2" y1="82.0" x2="290.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="82.0" x2="294.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="294.4" y1="82.0" x2="298.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="82.0" x2="301.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="301.6" y1="82.0" x2="305.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="82.0" x2="308.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="308.8" y1="82.0" x2="312.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="82.0" x2="316.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="316.0" y1="82.0" x2="319.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="82.0" x2="323.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="323.2" y1="82.0" x2="326.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="82.0" x2="330.4" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="330.4" y1="82.0" x2="334.0" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="82.0" x2="337.6" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="337.6" y1="82.0" x2="341.2" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="82.0" x2="344.8" y2="82.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="82.0" x2="344.8" y2="90.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="90.0" x2="13.6" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="98.0" x2="13.6" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="90.0" x2="344.8" y2="98.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="98.0" x2="344.8" y2="106.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="106.0" x2="13.6" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="114.0" x2="13.6" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="106.0" x2="344.8" y2="114.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="114.0" x2="344.8" y2="122.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="122.0" x2="13.6" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="130.0" x2="13.6" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="122.0" x2="344.8" y2="130.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="130.0" x2="344.8" y2="138.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="138.0" x2="13.6" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="146.0" x2="13.6" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="138.0" x2="344.8" y2="146.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="146.0" x2="344.8" y2="154.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="154.0" x2="13.6" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="162.0" x2="13.6" y2="170.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="154.0" x2="344.8" y2="162.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="162.0" x2="344.8" y2="170.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="170.0" x2="13.6" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="178.0" x2="13.6" y2="186.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="170.0" x2="344.8" y2="178.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="178.0" x2="344.8" y2="186.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="186.0" x2="13.6" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="194.0" x2="13.6" y2="202.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="186.0" x2="344.8" y2="194.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="194.0" x2="344.8" y2="202.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="210.0" x2="17.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="202.0" x2="13.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="210.0" x2="13.6" y2="218.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="210.0" x2="20.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="20.8" y1="210.0" x2="24.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="210.0" x2="28.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="210.0" x2="31.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="210.0" x2="35.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="210.0" x2="38.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="210.0" x2="42.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="210.0" x2="46.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="210.0" x2="49.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="210.0" x2="53.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="210.0" x2="56.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="210.0" x2="60.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="210.0" x2="64.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="64.0" y1="210.0" x2="67.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="210.0" x2="71.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="210.0" x2="74.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="210.0" x2="78.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="210.0" x2="82.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="210.0" x2="85.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="85.6" y1="210.0" x2="89.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="210.0" x2="92.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="92.8" y1="210.0" x2="96.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="210.0" x2="100.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="210.0" x2="103.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="210.0" x2="107.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="107.2" y1="210.0" x2="110.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="210.0" x2="114.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="114.4" y1="210.0" x2="118.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="210.0" x2="121.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="210.0" x2="125.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="210.0" x2="128.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="128.8" y1="210.0" x2="132.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="210.0" x2="136.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="210.0" x2="139.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="210.0" x2="143.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="143.2" y1="210.0" x2="146.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="210.0" x2="150.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="150.4" y1="210.0" x2="154.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="210.0" x2="157.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="210.0" x2="161.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="210.0" x2="164.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="210.0" x2="168.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="210.0" x2="172.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="210.0" x2="175.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="210.0" x2="179.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="210.0" x2="182.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="210.0" x2="186.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="210.0" x2="190.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="210.0" x2="193.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="210.0" x2="197.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="210.0" x2="200.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="210.0" x2="204.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="210.0" x2="208.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="210.0" x2="211.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="210.0" x2="215.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="215.2" y1="210.0" x2="218.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="210.0" x2="222.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="222.4" y1="210.0" x2="226.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="210.0" x2="229.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="229.6" y1="210.0" x2="233.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="210.0" x2="236.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="236.8" y1="210.0" x2="240.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="210.0" x2="244.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="244.0" y1="210.0" x2="247.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="210.0" x2="251.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="251.2" y1="210.0" x2="254.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="210.0" x2="258.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="258.4" y1="210.0" x2="262.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="210.0" x2="265.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="265.6" y1="210.0" x2="269.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="210.0" x2="272.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="272.8" y1="210.0" x2="276.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="210.0" x2="280.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="280.0" y1="210.0" x2="283.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="210.0" x2="287.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="287.2" y1="210.0" x2="290.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="210.0" x2="294.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="294.4" y1="210.0" x2="298.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="210.0" x2="301.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="301.6" y1="210.0" x2="305.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="210.0" x2="308.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="308.8" y1="210.0" x2="312.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="210.0" x2="316.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="316.0" y1="210.0" x2="319.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="210.0" x2="323.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="323.2" y1="210.0" x2="326.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="210.0" x2="330.4" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="330.4" y1="210.0" x2="334.0" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="210.0" x2="337.6" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="337.6" y1="210.0" x2="341.2" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="210.0" x2="344.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="202.0" x2="344.8" y2="210.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="210.0" x2="344.8" y2="218.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="218.0" x2="13.6" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="226.0" x2="13.6" y2="234.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="218.0" x2="344.8" y2="226.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="226.0" x2="344.8" y2="234.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="234.0" x2="13.6" y2="242.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="242.0" x2="13.6" y2="250.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="234.0" x2="344.8" y2="242.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="242.0" x2="344.8" y2="250.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="250.0" x2="13.6" y2="258.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="258.0" x2="13.6" y2="266.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="250.0" x2="344.8" y2="258.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="258.0" x2="344.8" y2="266.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="266.0" x2="13.6" y2="274.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="274.0" x2="13.6" y2="282.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="266.0" x2="344.8" y2="274.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="274.0" x2="344.8" y2="282.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="290.0" x2="17.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="282.0" x2="13.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="290.0" x2="13.6" y2="298.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="290.0" x2="20.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="20.8" y1="290.0" x2="24.4" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="290.0" x2="28.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="290.0" x2="31.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="290.0" x2="35.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="290.0" x2="38.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="290.0" x2="42.4" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="290.0" x2="46.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="290.0" x2="49.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="290.0" x2="53.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="290.0" x2="56.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="290.0" x2="60.4" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="290.0" x2="64.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="64.0" y1="290.0" x2="67.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="290.0" x2="71.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="290.0" x2="74.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="290.0" x2="78.4" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="290.0" x2="82.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="290.0" x2="85.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="85.6" y1="290.0" x2="89.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="290.0" x2="92.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="92.8" y1="290.0" x2="96.4" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="290.0" x2="100.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="290.0" x2="103.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="290.0" x2="107.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="107.2" y1="290.0" x2="110.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="290.0" x2="114.4" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="114.4" y1="290.0" x2="118.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="290.0" x2="121.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="290.0" x2="125.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="290.0" x2="128.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="128.8" y1="290.0" x2="132.4" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="290.0" x2="136.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="290.0" x2="139.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="290.0" x2="143.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="143.2" y1="290.0" x2="146.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="290.0" x2="150.4" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="150.4" y1="290.0" x2="154.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="290.0" x2="157.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="290.0" x2="161.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="290.0" x2="164.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="290.0" x2="168.4" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="290.0" x2="172.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="290.0" x2="175.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="290.0" x2="179.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="290.0" x2="182.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="290.0" x2="186.4" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="290.0" x2="190.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="290.0" x2="193.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="290.0" x2="197.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="290.0" x2="200.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="290.0" x2="204.4" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="290.0" x2="208.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="290.0" x2="211.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="290.0" x2="215.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="215.2" y1="290.0" x2="218.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="290.0" x2="222.4" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="222.4" y1="290.0" x2="226.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="290.0" x2="229.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="229.6" y1="290.0" x2="233.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="290.0" x2="236.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="236.8" y1="290.0" x2="240.4" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="290.0" x2="244.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="244.0" y1="290.0" x2="247.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="290.0" x2="251.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="251.2" y1="290.0" x2="254.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="290.0" x2="258.4" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="258.4" y1="290.0" x2="262.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="290.0" x2="265.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="265.6" y1="290.0" x2="269.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="290.0" x2="272.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="272.8" y1="290.0" x2="276.4" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="290.0" x2="280.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="280.0" y1="290.0" x2="283.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="290.0" x2="287.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="287.2" y1="290.0" x2="290.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="290.0" x2="294.4" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="294.4" y1="290.0" x2="298.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="290.0" x2="301.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="301.6" y1="290.0" x2="305.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="290.0" x2="308.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="308.8" y1="290.0" x2="312.4" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="290.0" x2="316.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="316.0" y1="290.0" x2="319.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="290.0" x2="323.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="323.2" y1="290.0" x2="326.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="290.0" x2="330.4" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="330.4" y1="290.0" x2="334.0" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="290.0" x2="337.6" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="337.6" y1="290.0" x2="341.2" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="290.0" x2="344.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="282.0" x2="344.8" y2="290.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="290.0" x2="344.8" y2="298.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="298.0" x2="13.6" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="306.0" x2="13.6" y2="314.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="298.0" x2="344.8" y2="306.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="306.0" x2="344.8" y2="314.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="314.0" x2="13.6" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="322.0" x2="13.6" y2="330.0" stroke="#10373E" stroke-width="1.3"/><line x1="337.6" y1="314.0" x2="337.6" y2="322.0" stroke="#10373E" stroke-width="1.3"/><line x1="337.6" y1="322.0" x2="337.6" y2="330.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="330.0" x2="13.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="338.0" x2="13.6" y2="346.0" stroke="#10373E" stroke-width="1.3"/><line x1="337.6" y1="330.0" x2="337.6" y2="338.0" stroke="#10373E" stroke-width="1.3"/><line x1="337.6" y1="338.0" x2="337.6" y2="346.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="354.0" x2="17.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="13.6" y1="346.0" x2="13.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="17.2" y1="354.0" x2="20.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="20.8" y1="354.0" x2="24.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="24.4" y1="354.0" x2="28.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="28.0" y1="354.0" x2="31.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="31.6" y1="354.0" x2="35.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="35.2" y1="354.0" x2="38.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="38.8" y1="354.0" x2="42.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="42.4" y1="354.0" x2="46.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="46.0" y1="354.0" x2="49.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="49.6" y1="354.0" x2="53.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="53.2" y1="354.0" x2="56.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="56.8" y1="354.0" x2="60.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="60.4" y1="354.0" x2="64.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="64.0" y1="354.0" x2="67.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="67.6" y1="354.0" x2="71.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="71.2" y1="354.0" x2="74.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="74.8" y1="354.0" x2="78.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="78.4" y1="354.0" x2="82.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="82.0" y1="354.0" x2="85.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="85.6" y1="354.0" x2="89.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="89.2" y1="354.0" x2="92.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="92.8" y1="354.0" x2="96.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="96.4" y1="354.0" x2="100.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="100.0" y1="354.0" x2="103.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="103.6" y1="354.0" x2="107.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="107.2" y1="354.0" x2="110.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="110.8" y1="354.0" x2="114.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="114.4" y1="354.0" x2="118.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="118.0" y1="354.0" x2="121.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="121.6" y1="354.0" x2="125.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="125.2" y1="354.0" x2="128.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="128.8" y1="354.0" x2="132.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="132.4" y1="354.0" x2="136.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="136.0" y1="354.0" x2="139.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="139.6" y1="354.0" x2="143.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="143.2" y1="354.0" x2="146.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="146.8" y1="354.0" x2="150.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="150.4" y1="354.0" x2="154.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="154.0" y1="354.0" x2="157.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="157.6" y1="354.0" x2="161.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="161.2" y1="354.0" x2="164.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="164.8" y1="354.0" x2="168.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="168.4" y1="354.0" x2="172.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="172.0" y1="354.0" x2="175.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="175.6" y1="354.0" x2="179.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="179.2" y1="354.0" x2="182.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="182.8" y1="354.0" x2="186.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="186.4" y1="354.0" x2="190.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="190.0" y1="354.0" x2="193.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="193.6" y1="354.0" x2="197.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="197.2" y1="354.0" x2="200.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="200.8" y1="354.0" x2="204.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="204.4" y1="354.0" x2="208.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="208.0" y1="354.0" x2="211.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="211.6" y1="354.0" x2="215.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="215.2" y1="354.0" x2="218.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="218.8" y1="354.0" x2="222.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="222.4" y1="354.0" x2="226.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="226.0" y1="354.0" x2="229.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="229.6" y1="354.0" x2="233.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="233.2" y1="354.0" x2="236.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="236.8" y1="354.0" x2="240.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="240.4" y1="354.0" x2="244.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="244.0" y1="354.0" x2="247.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="247.6" y1="354.0" x2="251.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="251.2" y1="354.0" x2="254.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="254.8" y1="354.0" x2="258.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="258.4" y1="354.0" x2="262.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="262.0" y1="354.0" x2="265.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="265.6" y1="354.0" x2="269.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="269.2" y1="354.0" x2="272.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="272.8" y1="354.0" x2="276.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="276.4" y1="354.0" x2="280.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="280.0" y1="354.0" x2="283.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="283.6" y1="354.0" x2="287.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="287.2" y1="354.0" x2="290.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="290.8" y1="354.0" x2="294.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="294.4" y1="354.0" x2="298.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="298.0" y1="354.0" x2="301.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="301.6" y1="354.0" x2="305.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="305.2" y1="354.0" x2="308.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="308.8" y1="354.0" x2="312.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="312.4" y1="354.0" x2="316.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="316.0" y1="354.0" x2="319.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="319.6" y1="354.0" x2="323.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="323.2" y1="354.0" x2="326.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="326.8" y1="354.0" x2="330.4" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="330.4" y1="354.0" x2="334.0" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="334.0" y1="354.0" x2="337.6" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="337.6" y1="354.0" x2="341.2" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="341.2" y1="354.0" x2="344.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><line x1="344.8" y1="346.0" x2="344.8" y2="354.0" stroke="#10373E" stroke-width="1.3"/><path d="M117.6 77.0 L121.6 84.0 L125.6 77.0 Z" fill="#10373E"/><text x="31.6" y="38.0" font-size="9.5" fill="#10373E">Internet</text><text x="96.4" y="38.0" font-size="9.5" fill="#10373E">(untrusted)</text><text x="132.4" y="70.0" font-size="9.5" fill="#10373E">HTTPS</text><text x="175.6" y="70.0" font-size="9.5" fill="#10373E">(TLS</text><text x="211.6" y="70.0" font-size="9.5" fill="#10373E">1.2</text><text x="240.4" y="70.0" font-size="9.5" fill="#10373E">)</text><text x="31.6" y="102.0" font-size="9.5" fill="#10373E">Nginx</text><text x="74.8" y="102.0" font-size="9.5" fill="#10373E">(SECURITY</text><text x="146.8" y="102.0" font-size="9.5" fill="#10373E">BOUNDARY)</text><text x="31.6" y="118.0" font-size="9.5" fill="#10373E">•</text><text x="46.0" y="118.0" font-size="9.5" fill="#10373E">TLS</text><text x="74.8" y="118.0" font-size="9.5" fill="#10373E">termination</text><text x="31.6" y="134.0" font-size="9.5" fill="#10373E">•</text><text x="46.0" y="134.0" font-size="9.5" fill="#10373E">Rate</text><text x="82.0" y="134.0" font-size="9.5" fill="#10373E">limiting,</text><text x="154.0" y="134.0" font-size="9.5" fill="#10373E">bot</text><text x="182.8" y="134.0" font-size="9.5" fill="#10373E">protection</text><text x="31.6" y="150.0" font-size="9.5" fill="#10373E">•</text><text x="46.0" y="150.0" font-size="9.5" fill="#10373E">Security</text><text x="110.8" y="150.0" font-size="9.5" fill="#10373E">headers</text><text x="168.4" y="150.0" font-size="9.5" fill="#10373E">(HSTS,</text><text x="218.8" y="150.0" font-size="9.5" fill="#10373E">CSP,</text><text x="254.8" y="150.0" font-size="9.5" fill="#10373E">etc.)</text><text x="31.6" y="166.0" font-size="9.5" fill="#10373E">•</text><text x="46.0" y="166.0" font-size="9.5" fill="#10373E">Path</text><text x="82.0" y="166.0" font-size="9.5" fill="#10373E">traversal</text><text x="154.0" y="166.0" font-size="9.5" fill="#10373E">/</text><text x="168.4" y="166.0" font-size="9.5" fill="#10373E">exploit</text><text x="226.0" y="166.0" font-size="9.5" fill="#10373E">blocking</text><text x="31.6" y="182.0" font-size="9.5" fill="#10373E">•</text><text x="46.0" y="182.0" font-size="9.5" fill="#10373E">Authentication</text><text x="154.0" y="182.0" font-size="9.5" fill="#10373E">gating</text><text x="204.4" y="182.0" font-size="9.5" fill="#10373E">for</text><text x="233.2" y="182.0" font-size="9.5" fill="#10373E">APIs</text><text x="31.6" y="198.0" font-size="9.5" fill="#10373E">•</text><text x="46.0" y="198.0" font-size="9.5" fill="#10373E">fail2ban</text><text x="110.8" y="198.0" font-size="9.5" fill="#10373E">integration</text><text x="31.6" y="230.0" font-size="9.5" fill="#10373E">PHP-FPM</text><text x="89.2" y="230.0" font-size="9.5" fill="#10373E">8.3</text><text x="118.0" y="230.0" font-size="9.5" fill="#10373E">(application)</text><text x="31.6" y="246.0" font-size="9.5" fill="#10373E">•</text><text x="46.0" y="246.0" font-size="9.5" fill="#10373E">Symfony</text><text x="103.6" y="246.0" font-size="9.5" fill="#10373E">1.4</text><text x="132.4" y="246.0" font-size="9.5" fill="#10373E">(router</text><text x="204.4" y="246.0" font-size="9.5" fill="#10373E">template</text><text x="269.2" y="246.0" font-size="9.5" fill="#10373E">engine)</text><text x="31.6" y="262.0" font-size="9.5" fill="#10373E">•</text><text x="46.0" y="262.0" font-size="9.5" fill="#10373E">AHG</text><text x="74.8" y="262.0" font-size="9.5" fill="#10373E">Framework</text><text x="146.8" y="262.0" font-size="9.5" fill="#10373E">(Laravel</text><text x="211.6" y="262.0" font-size="9.5" fill="#10373E">services)</text><text x="31.6" y="278.0" font-size="9.5" fill="#10373E">•</text><text x="46.0" y="278.0" font-size="9.5" fill="#10373E">AHG</text><text x="74.8" y="278.0" font-size="9.5" fill="#10373E">Plugins</text><text x="132.4" y="278.0" font-size="9.5" fill="#10373E">(business</text><text x="204.4" y="278.0" font-size="9.5" fill="#10373E">logic)</text><text x="31.6" y="310.0" font-size="9.5" fill="#10373E">Data</text><text x="67.6" y="310.0" font-size="9.5" fill="#10373E">Layer</text><text x="110.8" y="310.0" font-size="9.5" fill="#10373E">(localhost</text><text x="190.0" y="310.0" font-size="9.5" fill="#10373E">only)</text><text x="31.6" y="326.0" font-size="9.5" fill="#10373E">•</text><text x="46.0" y="326.0" font-size="9.5" fill="#10373E">MySQL</text><text x="89.2" y="326.0" font-size="9.5" fill="#10373E">8,</text><text x="110.8" y="326.0" font-size="9.5" fill="#10373E">Elasticsearch</text><text x="211.6" y="326.0" font-size="9.5" fill="#10373E">7.x</text><text x="31.6" y="342.0" font-size="9.5" fill="#10373E">•</text><text x="46.0" y="342.0" font-size="9.5" fill="#10373E">Fuseki</text><text x="96.4" y="342.0" font-size="9.5" fill="#10373E">triplestore,</text><text x="190.0" y="342.0" font-size="9.5" fill="#10373E">TrueNAS</text><text x="247.6" y="342.0" font-size="9.5" fill="#10373E">NFS</text></svg></div>

**Key principle:** Symfony 1.4's attack surface is minimized because Nginx intercepts and blocks exploit patterns before they reach PHP. The application layer adds its own defenses (CSP nonces, CSRF tokens, file validation, SSRF protection) as a second line.

---

## 2. Symfony 1.x Risk Assessment

### Why Symfony 1.4 Is a Risk

| Concern | Detail |
|---------|--------|
| End of life | No security patches since 2012 |
| Known CVEs | CSRF bypass, XSS in forms, session fixation |
| Deserialization | `unserialize()` used in session/config handling |
| Routing | Path-based routing may expose internal module names |

### Why the Risk Is Manageable

| Mitigation | Effect |
|------------|--------|
| PHP 8.3 | Modern PHP handles sessions, crypto, and I/O — not Symfony |
| Nginx filtering | Exploit patterns blocked before reaching PHP |
| CSP nonces | XSS mitigated at browser level |
| Laravel QB | SQL injection mitigated — plugins don't use Propel for queries |
| `unserialize()` hardened | All instances use `['allowed_classes' => false]` (see M0_SECURITY_HARDENING.md) |
| File validation | FileValidationService validates MIME, extension, size |
| CSRF tokens | CsrfService provides per-session tokens |
| SSRF protection | HttpClientService blocks private IPs and metadata endpoints |

### Residual Risk

The primary residual risk is a **zero-day in Symfony 1.4's routing or session handling** that bypasses Nginx. This is low-probability because:
- Symfony 1.4 has been static for 14 years (no new code = no new bugs)
- The routing layer is simple and well-understood
- Session handling is delegated to PHP 8.3's native session implementation

---

## 3. Required Security Headers

### 3.1 Headers Already Present

```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
```

### 3.2 Headers to Add

Add these to the `server` block in your Nginx site configuration, in the `SECURITY HEADERS` section:

```nginx
# HSTS — force HTTPS for 1 year, include subdomains
# Only enable after confirming HTTPS works correctly
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

# Permissions-Policy — restrict browser features
add_header Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=()" always;
```

**HSTS warning:** Once enabled, browsers will refuse HTTP connections for the specified duration. Test with a short `max-age` (e.g., 300) first, then increase to 31536000 (1 year) once confirmed working.

---

## 4. API & SPARQL Endpoint Protection

### 4.1 Problem

The following endpoints are publicly accessible without authentication:

| Endpoint | Risk |
|----------|------|
| `/sparql/` | Full SPARQL query access to triplestore — data exfiltration |
| `/api/ric/` | RiC semantic search API — information disclosure |
| `/api/provenance/` | Provenance API — information disclosure |
| `/api/editor/` | RiC editor API — potential data modification |
| `/ric-dashboard/` | Admin dashboard — information disclosure |

### 4.2 Fix: Require Authentication

Replace the existing unprotected `location` blocks with authenticated versions. The fix uses the Symfony session cookie — if the user is not logged in, they get a 403.

```nginx
# ======================================
# RiC ENDPOINTS — AUTHENTICATED ONLY
# ======================================

# SPARQL Proxy — require login
location ^~ /sparql/ {
    if ($cookie_symfony = "") {
        return 403;
    }
    proxy_pass http://192.168.0.112:3030/ric/;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_connect_timeout 30;
    proxy_read_timeout 180;
    proxy_send_timeout 180;
    # Remove wildcard CORS — only allow same-origin
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
}

# RiC Semantic Search API — require login
location ^~ /api/ric/ {
    if ($cookie_symfony = "") {
        return 403;
    }
    proxy_pass http://127.0.0.1:5001/api/;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_connect_timeout 30;
    proxy_read_timeout 30;
}

# RiC Provenance API — require login
location ^~ /api/provenance/ {
    if ($cookie_symfony = "") {
        return 403;
    }
    proxy_pass http://127.0.0.1:5003/api/provenance/;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}

# RiC Editor API — require login
location ^~ /api/editor/ {
    if ($cookie_symfony = "") {
        return 403;
    }
    proxy_pass http://127.0.0.1:5002/api/editor/;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}

# RiC Dashboard — require login
location ^~ /ric-dashboard/ {
    if ($cookie_symfony = "") {
        return 403;
    }
    alias /usr/share/nginx/archive/web/ric-dashboard/;
    index index.php index.html;
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $request_filename;
        include fastcgi_params;
    }
}
```

### 4.3 Bot Blocker Update

Remove the API bypass for SPARQL/RiC in `/etc/nginx/conf.d/bot-blocker.conf`:

```nginx
# BEFORE (allows bots to bypass protection for these endpoints)
map $request_uri $api_bypass {
    default 0;
    ~^/sparql/ 1;       # REMOVE
    ~^/api/ric/ 1;      # REMOVE
    ~^/ricExplorer/ 1;  # REMOVE
    ~^/api/library/ 1;
}

# AFTER
map $request_uri $api_bypass {
    default 0;
    ~^/api/library/ 1;
}
```

---

## 5. Login Brute Force Protection

### 5.1 Nginx Rate Limiting for Login

Add a dedicated rate limit zone for login attempts in `/etc/nginx/conf.d/bot-blocker.conf`:

```nginx
# Login rate limiting — 1 attempt per second per IP
limit_req_zone $binary_remote_addr zone=login_limit:10m rate=1r/s;
```

Add the login location block in the site configuration, **before** the main PHP handler:

```nginx
# Rate-limit login attempts
location ~ ^/index\.php/user/login$ {
    limit_req zone=login_limit burst=5 nodelay;

    include fastcgi_params;
    fastcgi_split_path_info ^(.+?\.php)(/.*)$;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    fastcgi_param PATH_INFO $fastcgi_path_info;
    fastcgi_param PATH_TRANSLATED $document_root$fastcgi_path_info;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param SCRIPT_NAME $fastcgi_script_name;
    fastcgi_index index.php;
    fastcgi_read_timeout 300;
    fastcgi_buffer_size 128k;
    fastcgi_buffers 4 256k;
    fastcgi_busy_buffers_size 256k;
}
```

### 5.2 fail2ban Login Jail

Create a fail2ban filter for Heratio login failures.

**Filter** — `/etc/fail2ban/filter.d/atom-login.conf`:

```ini
[Definition]
failregex = ^<HOST> .* "POST /index\.php/user/login HTTP/.*" (401|403)
            ^<HOST> .* "POST /index\.php/user/login HTTP/.*" 200 .*
ignoreregex =
```

**Jail** — add to `/etc/fail2ban/jail.local`:

```ini
[atom-login]
enabled  = true
filter   = atom-login
port     = http,https
logpath  = /var/log/nginx/psis_access.log
maxretry = 5
findtime = 300
bantime  = 1800
```

This bans an IP for 30 minutes after 5 failed login attempts within 5 minutes.

---

## 6. Additional Hardening

### 6.1 Hide Server Version

In `/etc/nginx/nginx.conf`, within the `http` block:

```nginx
server_tokens off;
```

### 6.2 Limit Request Body Size

Already configured (`client_max_body_size 2G`). This is appropriate for digital object uploads. For non-upload endpoints, consider a tighter limit:

```nginx
# Default limit for most requests
client_max_body_size 10m;

# Override for upload endpoints only
location ~ ^/index\.php/.*/digitalobject/ {
    client_max_body_size 2G;
    # ... existing config ...
}
```

### 6.3 Timeout Tuning

The current `fastcgi_read_timeout 3600` (1 hour) on the main PHP handler is very generous. For public-facing, consider:

```nginx
# Main handler — 5 minutes max
fastcgi_read_timeout 300;

# Import/export jobs — allow longer (only for authenticated admin)
location ~ ^/index\.php/(import|export|jobs) {
    if ($cookie_symfony = "") {
        return 403;
    }
    fastcgi_read_timeout 3600;
    # ... fastcgi config ...
}
```

### 6.4 SSL Hardening

The current SSL config is good. Optionally add OCSP stapling:

```nginx
ssl_stapling on;
ssl_stapling_verify on;
resolver 8.8.8.8 8.8.4.4 valid=300s;
resolver_timeout 5s;
```

---

## 7. Complete Security Checklist

### Nginx Layer
- [ ] HSTS header enabled
- [ ] Permissions-Policy header enabled
- [ ] `server_tokens off` set
- [ ] SPARQL endpoint requires authentication
- [ ] RiC API endpoints require authentication
- [ ] RiC Dashboard requires authentication
- [ ] Login endpoint rate-limited
- [ ] Bot blocker API bypass removed for RiC/SPARQL
- [ ] SSL OCSP stapling enabled
- [ ] Main handler timeout reduced to 300s

### fail2ban
- [ ] SSH jail enabled
- [ ] nginx-badbots jail enabled
- [ ] atom-login jail enabled

### Application Layer (already done)
- [x] CSP nonces on all script/style tags
- [x] `unserialize()` hardened with `allowed_classes => false`
- [x] FileValidationService for uploads
- [x] CSRF tokens via CsrfService
- [x] SSRF protection via HttpClientService
- [x] Shell command escaping via ShellCommandService
- [x] XXE prevention via XmlParserService

### Infrastructure
- [x] PHP 8.3 (current)
- [x] MySQL 8 (current, localhost only)
- [x] Elasticsearch 7.x (localhost only)
- [x] TLS 1.2+ with strong ciphers
- [x] Let's Encrypt auto-renewal
- [ ] Fuseki triplestore not exposed to network (verify firewall)

---

## 8. Applying the Changes

### Step 1: Backup Current Config

```bash
sudo cp /etc/nginx/sites-enabled/psis.theahg.co.za.conf \
        /etc/nginx/sites-enabled/psis.theahg.co.za.conf.bak.$(date +%Y%m%d)
sudo cp /etc/nginx/conf.d/bot-blocker.conf \
        /etc/nginx/conf.d/bot-blocker.conf.bak.$(date +%Y%m%d)
```

### Step 2: Apply Nginx Changes

1. Add security headers (Section 3.2) to the site config
2. Replace RiC/SPARQL location blocks (Section 4.2) in the site config
3. Update bot-blocker map (Section 4.3) in `/etc/nginx/conf.d/bot-blocker.conf`
4. Add login rate limit zone (Section 5.1) to bot-blocker.conf
5. Add login location block (Section 5.1) to the site config
6. Set `server_tokens off` in `/etc/nginx/nginx.conf`

### Step 3: Test and Reload

```bash
sudo nginx -t                      # Validate config
sudo systemctl reload nginx        # Apply without downtime
```

### Step 4: Configure fail2ban

```bash
# Create the atom-login filter
sudo nano /etc/fail2ban/filter.d/atom-login.conf
# Add the atom-login jail to jail.local
sudo nano /etc/fail2ban/jail.local
# Restart fail2ban
sudo systemctl restart fail2ban
# Verify
sudo fail2ban-client status
```

### Step 5: Verify

```bash
# Check HSTS header
curl -sI https://psis.theahg.co.za | grep -i strict

# Check Permissions-Policy header
curl -sI https://psis.theahg.co.za | grep -i permissions

# Verify SPARQL blocked for anonymous
curl -s -o /dev/null -w "%{http_code}" https://psis.theahg.co.za/sparql/

# Verify server version hidden
curl -sI https://psis.theahg.co.za | grep -i server

# Check fail2ban jails
sudo fail2ban-client status atom-login
```

---

## 9. Security References

- [OWASP Secure Headers Project](https://owasp.org/www-project-secure-headers/)
- [Mozilla Observatory](https://observatory.mozilla.org/)
- [Nginx Security Hardening Guide](https://nginx.org/en/docs/http/configuring_https_servers.html)
- [fail2ban Documentation](https://www.fail2ban.org/wiki/index.php/Main_Page)
- [HSTS Preload List](https://hstspreload.org/)

---

## 10. Related Documents

- [SECURITY_MODEL.md](SECURITY_MODEL.md) — Application security architecture
- [M0_SECURITY_HARDENING.md](M0_SECURITY_HARDENING.md) — PHP deserialization and upload hardening
- [CSRF_POLICY.md](CSRF_POLICY.md) — Cross-site request forgery protection
- [OUTBOUND_HTTP_POLICY.md](OUTBOUND_HTTP_POLICY.md) — SSRF prevention
- [SHELL_EXECUTION_POLICY.md](SHELL_EXECUTION_POLICY.md) — Shell command safety
