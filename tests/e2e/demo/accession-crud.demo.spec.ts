/**
 * DEMO WALKTHROUGH - Accession: full CRUD (narrated). Non-prod only.
 */
import { defineCrudDemo } from './demo-helpers';

defineCrudDemo({
  name: 'accession-crud',
  display: 'Accession CRUD',
  noun: 'accession',
  browse: '/accession/browse',
  add: '/accession/add',
  req: 'identifier',
  makeVal: () => `ACC-${Date.now()}`,
  mainField: 'title', // accession numbers are auto-generated + readonly; verify/edit the title
  extra: {
    title: () => `Demo Accession ${Date.now()}`,
    scope_and_content: 'Records received under a deed of gift, pending processing.',
  },
  hasRic: true,
});
