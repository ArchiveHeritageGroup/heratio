# Federation Module User Guide

## Overview

The Federation Module enables Heratio instances to share data across multiple institutions. It provides three core capabilities:

1. **OAI-PMH Harvesting** - Import records from other repositories using the OAI-PMH protocol
2. **Federated Search** - Search across multiple institutions simultaneously
3. **Vocabulary Synchronization** - Share and synchronize taxonomies between peers

## Getting Started

### Accessing Federation Settings

1. Log in as an administrator
2. Navigate to **Admin > Federation** or go to `/index.php/federation`

### Understanding Peers

A "peer" is another AtoM/Heritage Platform instance that you want to connect with. Each peer has:
- A name (human-readable identifier)
- A base URL (the OAI-PMH endpoint)
- Optional API key for authentication
- Configuration for harvesting, search, and vocabulary sync

## Managing Federation Peers

### Adding a New Peer

1. Go to **Admin > Federation > Peers**
2. Click **Add Peer**
3. Fill in the required fields:
   - **Name**: A descriptive name (e.g., "National Archives")
   - **Base URL**: The peer's OAI-PMH URL (e.g., `https://archives.example.org/oai`)
   - **API Key**: If the peer requires authentication
4. Configure harvesting options:
   - **Metadata Prefix**: Usually `oai_dc` or `oai_heritage`
   - **Default Set**: Leave empty to harvest all, or specify a set
   - **Harvest Interval**: How often to auto-harvest (in hours)
5. Click **Save**

### Editing a Peer

1. Go to **Admin > Federation > Peers**
2. Click the peer name or the edit icon
3. Modify settings as needed
4. Click **Save**

### Deactivating a Peer

1. Go to **Admin > Federation > Peers**
2. Click the peer name
3. Uncheck **Active**
4. Click **Save**

Deactivated peers are not included in harvesting, search, or sync operations.

## OAI-PMH Harvesting

### Manual Harvest

1. Go to **Admin > Federation > Peers**
2. Click **Harvest** next to the peer
3. Optionally set:
   - **From Date**: Only harvest records modified after this date
   - **Until Date**: Only harvest records modified before this date
   - **Set**: Specific set to harvest
4. Click **Start Harvest**

### Viewing Harvest History

1. Go to **Admin > Federation > Harvest Log**
2. Filter by peer, date, or status
3. Click a session to see details:
   - Records created, updated, deleted
   - Errors encountered
   - Duration

### Harvest Status Values

| Status | Description |
|--------|-------------|
| Running | Harvest is in progress |
| Completed | Harvest finished successfully |
| Failed | Harvest encountered an error |
| Cancelled | Harvest was manually stopped |

## Federated Search

Federated search allows users to search across all connected peers simultaneously.

### Enabling Federated Search

1. Go to **Admin > Federation > Peers**
2. Click a peer name
3. Go to the **Search** tab
4. Check **Enable Federated Search**
5. Configure:
   - **Search API URL**: Usually auto-detected
   - **Timeout**: How long to wait for response (ms)
   - **Max Results**: Maximum results from this peer
   - **Priority**: Lower numbers appear first
6. Click **Save**

### Using Federated Search

1. Go to the main search page
2. Check **Include federated results** or use the federated search URL
3. Enter your search terms
4. Results from all peers are merged and displayed with source attribution

### Search Result Sources

Each result shows:
- The peer it came from (with link)
- The original URL at the source repository
- A relevance score

## Vocabulary Synchronization

Vocabulary sync keeps taxonomies consistent across institutions.

### Configuring Vocabulary Sync

1. Go to **Admin > Federation > Vocabulary Sync**
2. Click **Configure Sync**
3. Select:
   - **Peer**: The peer to sync with
   - **Taxonomy**: Which taxonomy to synchronize
   - **Direction**: Pull, Push, or Bidirectional
   - **Conflict Resolution**: How to handle conflicts

### Sync Direction Options

| Direction | Description |
|-----------|-------------|
| Pull | Import terms from the remote peer |
| Push | Export terms to the remote peer |
| Bidirectional | Both import and export terms |

### Conflict Resolution Options

| Option | Description |
|--------|-------------|
| Prefer Local | Keep local terms when conflicts occur |
| Prefer Remote | Use remote terms when conflicts occur |
| Skip | Don't sync conflicting terms |
| Merge | Combine translations from both |

### Running a Sync

1. Go to **Admin > Federation > Vocabulary Sync**
2. Click **Sync Now** next to the configuration
3. Review the results:
   - Terms added
   - Terms updated
   - Conflicts encountered

### Viewing Sync History

1. Go to **Admin > Federation > Vocab Sync Log**
2. Filter by peer, taxonomy, or status
3. Click a session for details

## Dropdown Configuration

Federation status values are managed through the AHG Dropdown system for easy customization.

### Accessing Dropdown Settings

1. Go to **Admin > AHG Dropdown** or `/index.php/ahgDropdown`
2. Filter by "federation" to see all federation-related dropdowns

### Federation Taxonomies

| Taxonomy | Purpose |
|----------|---------|
| `federation_sync_direction` | Pull, Push, Bidirectional options |
| `federation_conflict_resolution` | Conflict handling strategies |
| `federation_harvest_action` | Created, Updated, Deleted |
| `federation_session_status` | Running, Completed, Failed, Cancelled |
| `federation_mapping_status` | Term mapping states |
| `federation_change_type` | Vocabulary change types |
| `federation_search_status` | Search result states |

### Adding Custom Values

1. Go to **Admin > AHG Dropdown**
2. Select the federation taxonomy
3. Click **Add Term**
4. Enter code, label, and optional color
5. Click **Save**

## Troubleshooting

### Harvest Fails Immediately

- Check the peer URL is accessible
- Verify the API key if authentication is required
- Check the peer's OAI-PMH endpoint is responding

### Search Returns No Results from a Peer

- Verify federated search is enabled for the peer
- Check the search timeout isn't too low
- Verify the peer's search API is accessible

### Vocabulary Sync Shows Conflicts

- Review the conflict resolution setting
- Consider using "Merge" for translations
- Check for duplicate terms with different cases

### Slow Federated Search

- Increase timeout for slow peers
- Reduce max results per peer
- Lower priority for less important peers

## Best Practices

1. **Start with Pull**: When first connecting, use "Pull" direction to import existing terms
2. **Use Sets**: Configure OAI sets to harvest only relevant content
3. **Regular Harvests**: Set appropriate harvest intervals (24h is common)
4. **Monitor Logs**: Regularly check harvest and sync logs for errors
5. **Test Connectivity**: Before enabling features, test that peers are accessible

## Related Documentation

- [OAI-PMH Protocol](https://www.openarchives.org/pmh/)
- [AHG Dropdown User Guide](ahg-settings-user-guide.md)
- [API User Guide](api-user-guide.md)
