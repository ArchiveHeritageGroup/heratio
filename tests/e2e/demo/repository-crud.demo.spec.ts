/**
 * DEMO WALKTHROUGH - Repository (ISDIAH): full CRUD (narrated). Non-prod only.
 */
import { defineCrudDemo } from './demo-helpers';

defineCrudDemo({
  name: 'repository-crud',
  display: 'Repository CRUD',
  noun: 'repository',
  browse: '/repository/browse',
  add: '/repository/add',
  req: 'authorized_form_of_name',
  makeVal: () => `Demo Repository ${Date.now()}`,
  extra: { identifier: () => `REPO-${Date.now()}` },
  hasRic: true,
});
