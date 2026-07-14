/**
 * DEMO WALKTHROUGH - Donor: full CRUD (narrated). Non-prod only.
 */
import { defineCrudDemo } from './demo-helpers';

defineCrudDemo({
  name: 'donor-crud',
  display: 'Donor CRUD',
  noun: 'donor',
  browse: '/donor/browse',
  add: '/donor/add',
  req: 'authorized_form_of_name',
  makeVal: () => `Demo Donor ${Date.now()}`,
  hasRic: true,
});
