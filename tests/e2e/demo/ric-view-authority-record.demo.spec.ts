/**
 * DEMO WALKTHROUGH - Records in Contexts view for an Authority Record. Non-prod only.
 */
import { defineRicViewDemo } from './demo-helpers';

defineRicViewDemo({
  name: 'ric-view-authority-record',
  display: 'RiC View - Authority Record',
  noun: 'authority record',
  add: '/actor/add',
  req: 'authorized_form_of_name',
  makeVal: () => `Demo RiC Person ${Date.now()}`,
  extra: { entity_type_id: '' },
});
