/**
 * DEMO WALKTHROUGH - Records in Contexts view for a Repository. Non-prod only.
 */
import { defineRicViewDemo } from './demo-helpers';

defineRicViewDemo({
  name: 'ric-view-repository',
  display: 'RiC View - Repository',
  noun: 'repository',
  add: '/repository/add',
  req: 'authorized_form_of_name',
  makeVal: () => `Demo RiC Repository ${Date.now()}`,
  extra: { identifier: () => `REPO-RIC-${Date.now()}` },
});
