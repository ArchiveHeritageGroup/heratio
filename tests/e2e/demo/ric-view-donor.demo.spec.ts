/**
 * DEMO WALKTHROUGH - Records in Contexts view for a Donor. Non-prod only.
 */
import { defineRicViewDemo } from './demo-helpers';

defineRicViewDemo({
  name: 'ric-view-donor',
  display: 'RiC View - Donor',
  noun: 'donor',
  add: '/donor/add',
  req: 'authorized_form_of_name',
  makeVal: () => `Demo RiC Donor ${Date.now()}`,
});
