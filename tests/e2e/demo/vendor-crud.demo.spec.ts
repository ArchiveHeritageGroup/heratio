/**
 * DEMO WALKTHROUGH - Vendor: full CRUD (narrated). Non-prod only.
 */
import { defineCrudDemo } from './demo-helpers';

defineCrudDemo({
  name: 'vendor-crud',
  display: 'Vendor CRUD',
  noun: 'vendor',
  browse: '/admin/vendor/browse',
  add: '/admin/vendor/add',
  req: 'name',
  makeVal: () => `Demo Vendor ${Date.now()}`,
  extra: { email: 'vendor@example.com' },
  hasRic: false,
});
