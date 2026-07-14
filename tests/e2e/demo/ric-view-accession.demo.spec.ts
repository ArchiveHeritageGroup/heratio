/**
 * DEMO WALKTHROUGH - Records in Contexts view for an Accession. Non-prod only.
 */
import { defineRicViewDemo } from './demo-helpers';

defineRicViewDemo({
  name: 'ric-view-accession',
  display: 'RiC View - Accession',
  noun: 'accession',
  add: '/accession/add',
  req: 'identifier',
  makeVal: () => `ACC-RIC-${Date.now()}`,
  mainField: 'title', // accession numbers are auto-generated + readonly
  extra: { title: () => `Demo RiC Accession ${Date.now()}` },
});
