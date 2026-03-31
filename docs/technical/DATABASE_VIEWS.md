# Database Views Documentation

## Overview

This document describes the database views used in the AtoM AHG Framework and its plugins.

## Views by Plugin

### ahgRicExplorerPlugin

#### ric_queue_status
**Purpose:** Shows the current status of RIC (Records in Context) queue items.
**Location:** `atom-ahg-plugins/ahgRicExplorerPlugin/data/install.sql:58`
**Columns:** Queue status counts and metrics

#### ric_recent_operations
**Purpose:** Shows recent RIC operations for monitoring.
**Location:** `atom-ahg-plugins/ahgRicExplorerPlugin/data/install.sql:73`
**Columns:** Recent operation details with timestamps

#### ric_sync_summary
**Purpose:** Provides a summary of RIC synchronization status.
**Location:** `atom-ahg-plugins/ahgRicExplorerPlugin/data/install.sql:204`
**Columns:** Sync counts and status information

## Usage Guidelines

1. Views are read-only aggregations of data
2. Views are automatically refreshed on each query
3. Do not create views that depend on other views (avoid view chains)
4. Always prefix plugin views with the plugin's domain (e.g., `ric_`, `spectrum_`)

## Adding New Views

When adding new views:
1. Add the CREATE VIEW statement to the plugin's `data/install.sql`
2. Document the view in this file
3. Consider performance implications for large datasets
4. Use indexed columns in WHERE clauses within the view definition

## Notes

- Most data access in the framework uses Laravel Query Builder directly
- Views are primarily used for reporting and monitoring dashboards
- Consider materialized tables for frequently accessed aggregations
