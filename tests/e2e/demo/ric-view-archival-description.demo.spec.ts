/**
 * DEMO WALKTHROUGH - Records in Contexts view for an Archival Description. Non-prod only.
 */
import { defineRicViewDemo } from './demo-helpers';

defineRicViewDemo({
  name: 'ric-view-archival-description',
  display: 'RiC View - Archival Description',
  noun: 'archival description',
  add: '/informationobject/add',
  req: 'title',
  makeVal: () => `Demo RiC Fonds ${Date.now()}`,
});
