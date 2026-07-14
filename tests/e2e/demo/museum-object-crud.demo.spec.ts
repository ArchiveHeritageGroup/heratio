/**
 * DEMO WALKTHROUGH - Museum Object (CIDOC CRM): full CRUD (narrated). Non-prod only.
 */
import { defineCrudDemo } from './demo-helpers';

defineCrudDemo({
  name: 'museum-object-crud',
  display: 'Museum Object CRUD',
  noun: 'museum object',
  browse: '/museum/browse',
  add: '/museum/add',
  req: 'title',
  makeVal: () => `Demo Museum Object ${Date.now()}`,
  extra: {
    object_number: () => `MUS-${Date.now()}`,
    work_type: 'painting',
    title_type: '',        // required select -> first real option
    creator_display: 'Demo Artist',
    creator: '',           // required select -> first real option
    creator_role: 'artist',
  },
  hasRic: true,
});
