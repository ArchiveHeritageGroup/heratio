# Heratio - Complete Function Reference

**Version:** 2.0
**Last Updated:** February 2026

Complete listing of every function available in the Heratio framework and plugins.

---

## Table of Contents

1. [Core Framework](#core-framework)
2. [ahg3DModelPlugin](#ahg3dmodelplugin)
3. [ahgAccessRequestPlugin](#ahgaccessrequestplugin)
4. [ahgAIPlugin](#ahgaiplugin)
5. [ahgAPIPlugin](#ahgapiplugin)
6. [ahgAuditTrailPlugin](#ahgaudittrailplugin)
7. [ahgBackupPlugin](#ahgbackupplugin)
8. [ahgCartPlugin](#ahgcartplugin)
9. [ahgConditionPlugin](#ahgconditionplugin)
10. [ahgContactPlugin](#ahgcontactplugin)
11. [ahgCorePlugin](#ahgcoreplugin)
12. [ahgDAMPlugin](#ahgdamplugin)
13. [ahgDataMigrationPlugin](#ahgdatamigrationplugin)
14. [ahgDedupePlugin](#ahgdedupeplugin)
15. [ahgDisplayPlugin](#ahgdisplayplugin)
16. [ahgDoiPlugin](#ahgdoiplugin)
17. [ahgDonorAgreementPlugin](#ahgdonoragreementplugin)
18. [ahgExhibitionPlugin](#ahgexhibitionplugin)
19. [ahgExportPlugin](#ahgexportplugin)
20. [ahgExtendedRightsPlugin](#ahgextendedrightsplugin)
21. [ahgFavoritesPlugin](#ahgfavoritesplugin)
22. [ahgFeedbackPlugin](#ahgfeedbackplugin)
23. [ahgFormsPlugin](#ahgformsplugin)
24. [ahgGalleryPlugin](#ahggalleryplugin)
25. [ahgHeritageAccountingPlugin](#ahgheritageaccountingplugin)
26. [ahgHeritagePlugin](#ahgheritageplugin)
27. [ahgICIPPlugin](#ahgicipplugin)
28. [ahgIiifPlugin](#ahgiiifplugin)
29. [ahgLabelPlugin](#ahglabelplugin)
30. [ahgLandingPagePlugin](#ahglandingpageplugin)
31. [ahgLibraryPlugin](#ahglibraryplugin)
32. [ahgLoanPlugin](#ahgloanplugin)
33. [ahgMetadataExportPlugin](#ahgmetadataexportplugin)
34. [ahgMetadataExtractionPlugin](#ahgmetadataextractionplugin)
35. [ahgMigrationPlugin](#ahgmigrationplugin)
36. [ahgMultiTenantPlugin](#ahgmultitenantplugin)
37. [ahgMuseumPlugin](#ahgmuseumplugin)
38. [ahgPreservationPlugin](#ahgpreservationplugin)
39. [ahgPrivacyPlugin](#ahgprivacyplugin)
40. [ahgProvenancePlugin](#ahgprovenanceplugin)
41. [ahgReportBuilderPlugin](#ahgreportbuilderplugin)
42. [ahgReportsPlugin](#ahgreportsplugin)
43. [ahgRequestToPublishPlugin](#ahgrequesttopublishplugin)
44. [ahgResearchPlugin](#ahgresearchplugin)
45. [ahgRicExplorerPlugin](#ahgricexplorerplugin)
46. [ahgRightsPlugin](#ahgrightsplugin)
47. [ahgSecurityClearancePlugin](#ahgsecurityclearanceplugin)
48. [ahgSemanticSearchPlugin](#ahgsemanticsearchplugin)
49. [ahgSettingsPlugin](#ahgsettingsplugin)
50. [ahgSpectrumPlugin](#ahgspectrumplugin)
51. [ahgStatisticsPlugin](#ahgstatisticsplugin)
52. [ahgThemeB5Plugin](#ahgthemeb5plugin)
53. [ahgTiffPdfMergePlugin](#ahgtiffpdfmergeplugin)
54. [ahgVendorPlugin](#ahgvendorplugin)
55. [ahgWorkflowPlugin](#ahgworkflowplugin)
56. [Functionality Overlap](#functionality-overlap)

---

## Core Framework

### Database Services
- Initialize Laravel Query Builder connection
- Execute SELECT queries with Builder syntax
- Execute INSERT operations
- Execute UPDATE operations
- Execute DELETE operations
- Begin database transactions
- Commit transactions
- Rollback transactions
- Check database connection status
- Get raw PDO connection
- Execute raw SQL queries

### Configuration Services
- Get root path of installation
- Get framework path
- Get plugins path
- Get uploads path
- Get cache path
- Get temp path
- Get logs path
- Get site base URL
- Get upload URL path
- Detect environment (development/production)
- Check PHP version compatibility
- Get maximum upload size
- Read app settings
- Read database settings
- Get current culture/language

### Extension Management
- Discover available plugins
- Enable plugin
- Disable plugin
- Install plugin from GitHub
- Update plugin
- Update all plugins
- Check plugin dependencies
- Get plugin version
- List enabled plugins
- List disabled plugins

---

## ahg3DModelPlugin

### 3D Model Management
- Upload 3D model file (.glb, .gltf, .usdz, .obj, .stl, .fbx, .ply)
- Upload Gaussian Splat file (.splat, .ksplat)
- Store model metadata (vertex count, face count, texture count)
- Generate model thumbnail automatically
- Delete 3D model
- Update model settings
- Link model to information object

### 3D Viewer Functions
- Display 3D model in WebGL viewer
- Enable auto-rotation
- Set auto-rotation speed
- Configure camera orbit controls
- Set field of view
- Adjust exposure level
- Configure shadow intensity
- Set background color
- Enable/disable environment lighting
- Switch to Three.js fallback viewer
- Display Gaussian Splat in viewer

### Augmented Reality
- Launch iOS Quick Look AR view
- Launch Android Scene Viewer AR
- Enable WebXR AR mode
- Configure AR placement mode

### Hotspot Annotations
- Create annotation hotspot
- Create info hotspot
- Create damage marker hotspot
- Create detail hotspot
- Create link hotspot
- Edit hotspot position
- Edit hotspot content
- Delete hotspot
- Show/hide hotspots

### Texture Management
- Upload diffuse texture map
- Upload normal texture map
- Upload roughness texture map
- Upload metallic texture map
- Upload ambient occlusion map
- Upload emissive texture map
- Upload environment map

### IIIF Integration
- Generate IIIF 3D manifest
- Validate IIIF manifest
- Embed viewer in external sites

### Settings
- Configure global viewer settings
- Set per-model viewer settings
- Enable/disable auto-rotate globally
- Set default camera position
- Configure model processing queue

---

## ahgAccessRequestPlugin

### Request Types
- Submit security clearance upgrade request
- Submit object-level access request
- Submit researcher renewal request
- Cancel pending request
- View request status
- View request history

### Request Workflow
- Set request to pending status
- Approve request with notes
- Deny request with reason
- Set request to expired
- Cancel request
- Escalate request to higher authority

### Approval Management
- View pending requests queue
- Filter requests by type
- Filter requests by status
- Filter requests by date range
- View approval statistics
- Count pending requests
- Count approved today
- Count denied today

### Access Grants
- Create object access grant
- Set access grant expiry date
- Revoke access grant
- Check if user has access grant
- Check ancestor access grants
- Check repository-level access
- Include child records in grant

### Notifications
- Send approval notification email
- Send denial notification email
- Send expiry warning email
- Send escalation notification

### Integration
- Check user security clearance level
- Update researcher status
- Log access request actions

---

## ahgAIPlugin

### Named Entity Recognition (NER)
- Extract persons from text
- Extract organizations from text
- Extract places/locations from text
- Extract dates from text
- Extract events from text
- Calculate confidence score for each entity
- Link entity to existing actor record
- Link entity to existing term/subject
- Create new actor from entity
- Create new term from entity
- Mark entity as approved
- Mark entity as rejected
- Edit entity value
- Change entity type
- Export training data for model improvement
- Run NER on single record
- Run NER batch on multiple records
- View NER review dashboard
- Filter entities by status
- Filter entities by type
- Filter entities by confidence score

### Machine Translation
- Translate text from English to Afrikaans
- Translate text from English to French
- Translate text from English to German
- Translate text from English to Spanish
- Translate text from English to Portuguese
- Translate text from English to Dutch
- Translate text from English to Italian
- Translate between any supported language pair
- Install language pack
- Remove language pack
- List installed language packs
- Translate single record field
- Batch translate multiple records
- View translation history
- Set target field for translation

### Text Summarization
- Generate summary of scope and content
- Generate summary of any text field
- Set minimum summary length
- Set maximum summary length
- Extract text from PDF for summarization
- Summarize single record
- Batch summarize multiple records
- View generated summaries
- Approve generated summary
- Edit generated summary
- Reject generated summary

### Spellcheck
- Check spelling in title field
- Check spelling in scope and content
- Check spelling in any text field
- Get spelling suggestions
- Apply spelling correction
- Ignore spelling suggestion
- Add word to custom dictionary
- Batch spellcheck multiple records
- Support multiple languages

### Handwriting Recognition (HTR)
- Extract text from handwritten document image
- Process single page
- Process multi-page document
- View extracted text
- Edit extracted text
- Link extracted text to record

### LLM Description Suggestions
- Generate description using Ollama
- Generate description using OpenAI API
- Generate description using Anthropic API
- Create prompt template
- Edit prompt template
- Delete prompt template
- Use variables in prompts ({title}, {identifier}, {date_range}, {creator}, {repository}, {ocr_text})
- View suggestion dashboard
- Approve suggestion
- Edit and apply suggestion
- Reject suggestion
- Set suggestion expiry period
- Configure LLM provider settings
- Set API keys for providers
- Test LLM connection

### AI Job Queue
- Create NER batch job
- Create summarization batch job
- Create suggestion batch job
- Create translation batch job
- Create spellcheck batch job
- Create OCR batch job
- View job queue dashboard
- View job progress
- Cancel running job
- Retry failed job
- Set job priority
- Configure concurrent job limit
- Enable automatic retry
- Set retry backoff interval
- Enable server load protection
- Set CPU threshold for pausing

### CLI Commands
- php symfony ai:ner-extract
- php symfony ai:ner-review
- php symfony ai:translate
- php symfony ai:summarize
- php symfony ai:spellcheck
- php symfony ai:suggest
- php symfony ai:batch-process
- php symfony ai:queue-status
- php symfony ai:install

---

## ahgAPIPlugin

### Description Endpoints
- GET /api/v2/descriptions - Browse all descriptions
- GET /api/v2/descriptions/:slug - Get single description
- POST /api/v2/descriptions - Create new description
- PUT /api/v2/descriptions/:slug - Update description
- PATCH /api/v2/descriptions/:slug - Partial update
- DELETE /api/v2/descriptions/:slug - Delete description

### Authority Endpoints
- GET /api/v2/authorities - Browse all authorities
- GET /api/v2/authorities/:slug - Get single authority
- POST /api/v2/authorities - Create authority
- PUT /api/v2/authorities/:slug - Update authority
- DELETE /api/v2/authorities/:slug - Delete authority

### Repository Endpoints
- GET /api/v2/repositories - Browse repositories
- GET /api/v2/repositories/:slug - Get single repository

### Taxonomy Endpoints
- GET /api/v2/taxonomies - List all taxonomies
- GET /api/v2/taxonomies/:id/terms - Get taxonomy terms

### Search Endpoint
- POST /api/v2/search - Search with parameters
- Filter by repository
- Filter by level of description
- Filter by date range
- Filter by subject
- Filter by creator
- Sort results
- Paginate results

### Batch Operations
- POST /api/v2/batch - Execute batch operations
- Batch create records
- Batch update records
- Batch delete records

### API Key Management
- Create new API key
- View API keys
- Revoke API key
- Set key scopes (read, write, delete, admin)
- Set rate limit per key
- View key usage statistics

### Authentication
- Authenticate with X-API-Key header
- Authenticate with Bearer token
- Authenticate with REST-API-Key header
- Session-based authentication

### Response Features
- JSON:API format responses
- Include related resources
- Sparse fieldsets
- Error responses with details

---

## ahgAuditTrailPlugin

### Event Logging
- Log record creation
- Log record update with field changes
- Log record deletion
- Log user login
- Log user logout
- Log failed login attempt
- Log file download
- Log file upload
- Log permission change
- Log ACL modification
- Log data export
- Log data import
- Log search query (optional)
- Log record view (optional)
- Log custom action type

### Log Data Captured
- Generate UUID for each log entry
- Capture user ID
- Capture username
- Capture IP address
- Capture user agent
- Capture session ID
- Capture timestamp
- Capture entity type
- Capture entity ID
- Capture action type
- Store old values JSON
- Store new values JSON
- Store changed fields list
- Tag compliance category

### Log Viewing
- View audit log list
- Filter by user
- Filter by action type
- Filter by entity type
- Filter by date range
- Search log entries
- View single log entry detail
- View record change history
- View user activity history

### Data Protection
- Mask sensitive fields in logs
- Configure fields to mask
- Anonymize user data on request

### Retention
- Configure retention period
- Set NARSSA 7-year retention
- Automated purge of expired logs
- Manual purge with confirmation

### Export
- Export logs to CSV
- Export logs to JSON
- Export logs to PDF
- Export filtered results
- Schedule automatic exports

### Compliance Reports
- Generate POPIA compliance report
- Generate NARSSA compliance report
- Generate PAIA compliance report
- Generate GDPR compliance report
- Generate custom compliance report

### CLI Commands
- php symfony audit:view
- php symfony audit:export
- php symfony audit:purge
- php symfony audit:stats

---

## ahgBackupPlugin

### Backup Creation
- Create full backup (database + files)
- Create database-only backup
- Create files-only backup
- Create configuration backup
- Create incremental backup
- Set backup compression (gzip, bzip2)
- Set backup encryption
- Generate backup checksum
- Name backup with timestamp
- Add backup description

### Backup Scheduling
- Configure daily backup schedule
- Configure weekly backup schedule
- Configure monthly backup schedule
- Set backup time
- Enable/disable scheduled backups

### Backup Management
- View backup list
- View backup details
- Download backup file
- Delete backup
- Verify backup integrity
- Calculate backup size
- View backup history

### Retention Policies
- Set retention period (days)
- Set maximum backups to keep
- Enable automatic cleanup
- Exclude specific backups from cleanup

### Restore Functions
- Restore from backup
- Preview restore without applying
- Restore database only
- Restore files only
- Restore configuration only
- Select specific tables to restore

### Notifications
- Send email on backup completion
- Send email on backup failure
- Configure notification recipients

### CLI Commands
- php symfony backup:create
- php symfony backup:list
- php symfony backup:restore
- php symfony backup:verify
- php symfony backup:cleanup

---

## ahgCartPlugin

### Shopping Cart
- Add item to cart
- Remove item from cart
- Update item quantity
- Clear entire cart
- View cart contents
- Calculate cart total
- Apply VAT to cart
- Save cart for later
- Restore saved cart

### Guest Checkout
- Create guest cart (session-based)
- Checkout as guest
- Merge guest cart on login
- Guest order confirmation page

### User Cart
- Link cart to user account
- Persist cart across sessions
- View cart history

### Checkout Process
- Enter customer name
- Enter customer email
- Enter customer phone
- Enter delivery address
- Select delivery method
- Review order summary
- Accept terms and conditions
- Submit order

### Payment Integration (PayFast)
- Configure PayFast merchant ID
- Configure PayFast merchant key
- Configure PayFast passphrase
- Enable sandbox/test mode
- Generate payment signature
- Redirect to PayFast payment page
- Handle payment return
- Handle payment cancel
- Process ITN webhook notification
- Verify payment signature
- Update order payment status

### Order Management
- Create order from cart
- Generate order number
- Set order status (pending, paid, processing, completed, cancelled)
- View order details
- Update order status
- Cancel order
- Refund order (manual)

### E-Commerce Settings
- Enable/disable e-commerce per repository
- Set currency (ZAR, USD, EUR, GBP)
- Set VAT rate
- Configure product pricing
- Set product types (digital, physical)
- Configure shipping options

### Notifications
- Send order confirmation email
- Send payment received email
- Send order shipped email
- Send order completed email

### Admin Functions
- View all orders
- Filter orders by status
- Filter orders by date
- Search orders
- Export orders to CSV
- View order statistics

---

## ahgConditionPlugin

### Condition Assessments
- Create new condition assessment
- Edit existing assessment
- Delete assessment
- View assessment history
- Copy previous assessment

### Assessment Types
- Condition assessment
- Conservation assessment
- Inspection assessment
- Survey assessment

### Condition Ratings
- Set overall condition (excellent, good, fair, poor, critical)
- Set condition score (0-100)
- Set structural condition
- Set surface condition
- Set media condition
- Set housing condition
- Calculate composite score

### Risk Assessment
- Set risk score (0-10)
- Set priority score (0-10)
- Identify high-risk items
- Generate risk report

### Damage Documentation
- Record damage type (physical, chemical, biological, environmental, structural)
- Record damage location
- Describe damage extent
- Attach damage photographs
- Map damage on object image

### Environmental Factors
- Record temperature at assessment
- Record humidity at assessment
- Record light level (lux)
- Record UV level
- Note environmental concerns
- Set alert thresholds

### Treatment Tracking
- Create treatment record
- Link treatment to assessment
- Record treatment type
- Record treatment date
- Record treatment duration
- Record materials used
- Record treatment cost
- Record conservator name
- Attach before photos
- Attach after photos
- Record treatment outcome
- Schedule follow-up treatment

### Recommendations
- Add treatment recommendations
- Add handling instructions
- Add storage recommendations
- Add exhibition restrictions
- Set next assessment date

### Reporting
- Generate condition report
- Generate conservation workload report
- Generate at-risk items report
- Export assessments to CSV
- Print condition report

### CLI Commands
- php symfony condition:report
- php symfony condition:due-list

---

## ahgContactPlugin

### Contact Information
- Create contact record for actor
- Edit contact information
- Delete contact record
- Set primary contact
- View all contacts for actor

### Basic Contact Fields
- Enter contact person name
- Enter street address
- Enter city
- Enter region/state/province
- Enter postal code
- Enter country
- Enter telephone number
- Enter fax number
- Enter email address
- Enter website URL
- Store GPS latitude
- Store GPS longitude

### Extended Contact Fields
- Enter title/honorific (Mr, Mrs, Dr, Prof, etc.)
- Enter job role/position
- Enter department/division
- Enter mobile/cell phone
- Enter ID/passport number
- Enter alternative email
- Enter alternative phone
- Set preferred contact method (email, phone, cell, fax, mail)
- Set language preference
- Add internal notes

### Contact Type
- Set contact type
- Translate contact type (i18n)

### Display
- Format address for display
- Show primary contact indicator
- List all contacts for actor

---

## ahgCorePlugin

### Database Bootstrap (AhgDb)
- Initialize database connection
- Get Query Builder instance
- Execute table queries
- Begin transaction
- Commit transaction
- Rollback transaction
- Check connection status
- Get PDO connection

### Configuration (AhgConfig)
- Get root path
- Get framework path
- Get plugins path
- Get uploads path
- Get cache path
- Get temp path
- Get logs path
- Get base URL
- Get upload URL
- Check development mode
- Get PHP version
- Get max upload size
- Get app setting
- Get database setting
- Get current culture

### Taxonomy (AhgTaxonomy)
- Get taxonomy ID by name
- Get term ID by name
- Create term dynamically
- Cache taxonomy lookups

### File Storage (AhgStorage)
- Handle file upload
- Sanitize filename
- Verify file integrity (MD5)
- Verify file integrity (SHA-256)
- Create directory with permissions
- Check blocked extensions
- Detect MIME type
- Generate unique filename
- Format file size for display

### Hook System (AhgHooks)
- Register hook callback
- Set hook priority
- Trigger hook
- Filter through hook
- Remove hook

### Panel System (AhgPanels)
- Register panel
- Set panel position (sidebar, main, header, footer, actions)
- Set panel visibility condition
- Set panel weight/order
- Render panels

### Capabilities (AhgCapabilities)
- Register capability
- Check if capability exists
- Get all capabilities
- Feature detection for IIIF
- Feature detection for 3D
- Feature detection for AI
- Feature detection for PII
- Feature detection for Rights
- Feature detection for Loans
- Feature detection for Cart
- Feature detection for Favorites
- Feature detection for Backup
- Feature detection for Audit
- Feature detection for Spectrum
- Feature detection for Privacy
- Feature detection for Security

### Email Service
- Send email notification
- Parse email template
- Test email connection
- Send researcher pending notification
- Send researcher approved notification
- Send researcher rejected notification
- Send password reset email
- Send booking confirmation

### Watermark Service
- Configure watermark image
- Set watermark position
- Set watermark opacity
- Apply watermark to image
- Security clearance watermarks
- Watermark priority system

### Access Control
- Check embargo status
- Verify user access
- Gate with embargo support

### Text-to-Speech
- Convert text to speech audio
- TTS API endpoint

### User Services
- Authenticate user
- Reset password
- Register new user
- Update user attributes

---

## ahgDAMPlugin

### Asset Classification
- Set asset type (photograph, film, video, documentary, audio, podcast, speech, document, manuscript, artwork, map, 3d_model, dataset, software, website)
- View assets by type
- Filter by asset type

### IPTC Metadata
- Enter headline
- Enter description/caption
- Enter keywords/tags
- Enter creator name
- Enter credit line
- Enter source
- Enter copyright notice
- Enter usage terms

### Location Metadata
- Enter city name
- Enter province/state
- Enter country
- Enter sublocation
- Store GPS latitude
- Store GPS longitude
- Store altitude
- View on map

### Production Metadata
- Enter duration (minutes for video/audio)
- Enter production country
- Enter release date
- Enter production company

### Rights Management
- Set copyright holder
- Set copyright status
- Set license type
- Set license expiry date
- Enter rights usage terms

### Technical Metadata
- Store file format
- Store MIME type
- Store file size
- Store dimensions (width x height)
- Store color space
- Store bit depth

### Version Tracking
- Link version to master record
- Set version type (language, format_conversion, restoration, directors_cut, censored)
- Enter version language
- Enter version description
- View all versions of asset

### Format Holdings
- Create format holding record
- Set format type (35mm, 16mm, 8mm, VHS, Betacam, DVD, Digital File, DCP, Vinyl, CD, etc.)
- Set holding institution
- Set accession number
- Set format condition
- Set access status (available, restricted, digitized_available, on_request, staff_only)
- Set primary format
- Set verification date
- Add format notes

### External Links
- Add ESAT link
- Add IMDb link
- Add SAFILM link
- Add Wikipedia link
- Add Wikidata link
- Add VIAF link
- Add YouTube link
- Add Vimeo link
- Add Archive.org link
- Add BFI link
- Add AFI link
- Set person role for link
- Set link as primary
- Verify external link
- Track verification date

---

## ahgDataMigrationPlugin

### Source Detection
- Auto-detect ArchivesSpace format
- Auto-detect Vernon CMS format
- Auto-detect Preservica format
- Auto-detect generic CSV
- Auto-detect Excel format

### Import Formats
- Import from CSV file
- Import from Excel file (xlsx/xls)
- Import from XML file
- Import from Preservica OPEX
- Import from Preservica PAX package
- Import from ArchivesSpace JSON

### Field Mapping
- Map source field to AtoM field
- Set default values for unmapped fields
- Configure data transformations
- Trim whitespace
- Convert date formats
- Clean text (remove HTML, normalize spaces)
- Map to multiple target fields

### Hierarchy Building
- Resolve parent references
- Build parent-child relationships
- Calculate nested set values
- Create hierarchy from flat data
- Match parents by identifier
- Match parents by title

### Record Creation
- Create information objects
- Generate slugs
- Set publication status
- Assign to repository
- Set culture/language
- Set level of description
- Link to creators
- Create subject access points
- Create place access points
- Create name access points

### Digital Object Import
- Import files from package
- Import files from path
- Generate thumbnails
- Generate reference images
- Generate audio waveforms
- Verify checksums (MD5, SHA-256)
- Store original filename

### Preservica OPEX Import
- Extract OPEX package
- Parse OPEX metadata
- Map OPEX fields to AtoM
- Import rights from OPEX
- Import events as provenance
- Handle nested OPEX structure

### Preservica PAX Import
- Extract PAX package
- Parse XIP manifest
- Map PAX fields to AtoM
- Import content objects
- Import representations

### Job Management
- Create import job
- Queue job for processing
- Track job progress
- View job status
- Cancel running job
- Retry failed job
- View job errors

### Modes
- Dry-run mode (preview only)
- Create mode (new records only)
- Update mode (update existing)
- Skip duplicates option

### Sector Profiles
- Archive field set
- Museum field set
- Library field set
- Gallery field set
- DAM field set

### Export (to Preservica)
- Export to XIP/PAX format
- Export to OPEX format
- Include Dublin Core metadata

### CLI Commands
- php symfony migration:import
- php symfony migration:preview
- php symfony migration:status
- php symfony migration:export

---

## ahgDedupePlugin

### Detection Algorithms
- Levenshtein distance similarity
- Jaro-Winkler similarity
- Soundex phonetic matching
- Exact identifier matching
- Fuzzy identifier matching
- Date + creator combination
- File checksum matching (MD5)
- File checksum matching (SHA-256)
- Combined weighted analysis

### Detection Rules
- Create detection rule
- Edit detection rule
- Delete detection rule
- Set rule priority
- Set similarity threshold (0.0-1.0)
- Enable/disable rule
- Set rule scope (global or repository)

### Text Normalization
- Convert to lowercase
- Remove punctuation
- Collapse whitespace
- Remove articles (a, an, the)
- Remove diacritics

### Scanning
- Scan entire system for duplicates
- Scan specific repository
- Scan specific collection
- View scan progress
- Cancel running scan
- Schedule automatic scans
- View scan results

### Duplicate Review
- View pending duplicates list
- Filter by detection method
- Filter by similarity score
- Sort by score
- Sort by date detected
- View duplicate pair details
- Compare records side-by-side

### Duplicate Resolution
- Dismiss as false positive
- Never show this pair again
- Merge records
- Select master record
- Choose field values from either record
- Transfer digital objects
- Reparent child records
- Preserve slugs as redirects
- Delete duplicate record

### Real-Time Detection
- Check for duplicates on save
- Display warning before save
- Block save if duplicate found (configurable)
- Suggest existing record to link

### Checksum Management
- Calculate file checksums
- Store checksums in cache
- Lookup files by checksum
- Find duplicate files

### Reporting
- View duplicate statistics
- Count duplicates by method
- Count resolved duplicates
- Export duplicate report (CSV)
- Export duplicate report (JSON)

### Merge Audit
- Log all merge operations
- Record source records
- Record target record
- Record field decisions
- View merge history

### CLI Commands
- php symfony dedupe:scan
- php symfony dedupe:status
- php symfony dedupe:merge
- php symfony dedupe:report

---

## ahgDisplayPlugin

### GLAM Type Detection
- Detect archive type
- Detect museum type
- Detect gallery type
- Detect library type
- Detect DAM type
- Detect by level of description
- Detect by parent type
- Detect by events (photographer, artist, author)
- Fallback to archive type

### Display Profiles
- Create display profile
- Edit display profile
- Delete display profile
- Assign profile to object
- Set profile context
- Multiple profiles per object
- Set profile visibility conditions

### Field Configuration
- Define field groups
- Map display fields to database
- Map to ISAD(G) elements
- Map to Spectrum units
- Map to Dublin Core elements
- Set field display order
- Set field visibility
- Set field labels

### Levels of Description
- Support 40+ levels
- Organize levels by domain
- Archive levels
- Museum levels
- Library levels
- Gallery levels
- DAM levels
- Custom levels

### Display Modes
- Grid view
- List view
- Card view
- Gallery view
- Hierarchy/tree view
- Timeline view
- Map view
- Table view

### User Preferences
- Save preferred display mode
- Save preferred thumbnail size
- Save preferred card size
- Save preferred sort field
- Save preferred sort direction
- Save preferred items per page
- Enable/disable thumbnails
- Enable/disable descriptions

### Global Settings
- Set default display mode per module
- Set default thumbnail size
- Set default items per page
- Allow user overrides
- Admin mode switching

### Display Actions
- Register display action
- Render action buttons
- Render badges
- Render panels
- Position panels

### Elasticsearch Integration
- Connect to Elasticsearch 7
- Execute ES searches
- Apply facet filters
- Get aggregations
- Object type aggregation
- Media type aggregation
- Level aggregation
- Subject aggregation
- Creator aggregation
- Date range aggregation

### Browse Functions
- Browse by GLAM type
- Browse by collection
- Browse by repository
- Autocomplete search
- Faceted search results
- Apply multiple filters
- Clear filters

### Type Appearance
- Set type color
- Set type icon
- Set type label

### Output Options
- Print view (up to 500 items)
- Export to CSV
- Standard web view

### CLI Commands
- php symfony display:auto-detect
- php symfony display:reindex

---

## ahgDoiPlugin

### DOI Minting
- Mint new DOI for record
- Mint DOI via DataCite API
- Auto-mint on record publication
- Set auto-mint level filters
- Require digital object for auto-mint
- Queue DOI for async minting
- Batch mint multiple DOIs
- Dry-run mode (preview only)

### DOI States
- Set DOI state to draft
- Set DOI state to registered
- Set DOI state to findable
- Mark DOI as failed
- Mark DOI as deleted

### DOI Configuration
- Configure DataCite API URL
- Configure repository ID
- Configure API username
- Configure API password
- Enable sandbox/test mode
- Enable production mode
- Set DOI prefix
- Configure DOI suffix pattern

### Suffix Patterns
- Use {repository_code} variable
- Use {year} variable
- Use {month} variable
- Use {object_id} variable
- Use {slug} variable
- Use {identifier} variable
- Custom suffix format

### Metadata Mapping
- Map title field
- Map creator field
- Map date field
- Map description field
- Map subject field
- Map type field
- Map format field
- Map identifier field
- Map rights field
- Map publisher field
- Custom field mappings per repository
- Apply transformations (uppercase, lowercase, strip_html, truncate)
- Set fallback values

### DOI Queue
- View queue status
- Process queue items
- Set queue priority
- Retry failed items
- Configure max retry attempts
- View queue statistics

### DOI Verification
- Verify DOI resolves correctly
- Check DOI status at DataCite
- Update local status from DataCite
- Track last sync time

### DOI Resolution
- Resolve DOI to local record
- Redirect DOI requests
- Handle DOI landing page

### Repository Configuration
- Configure DOI settings per repository
- Set repository-specific mappings
- Enable/disable DOI per repository
- Set default configuration

### Reporting
- View DOI statistics
- Count by state
- Count minted this month
- View activity log

### CLI Commands
- php symfony doi:mint
- php symfony doi:process-queue
- php symfony doi:verify
- php symfony doi:stats

---

## ahgDonorAgreementPlugin

### Donor Management
- Create donor record
- Edit donor record
- Delete donor record
- Set donor type (individual, corporate, family, estate, government, other)
- Enter tax ID
- Set preferred contact person
- Add donor notes
- View donor history
- Link donor to actor record

### Agreement Management
- Create new agreement
- Edit agreement
- Delete agreement
- Generate agreement number
- Set agreement type (gift, bequest, purchase, deposit, loan, exchange)
- Set agreement status (draft, pending, active, expired, terminated)
- Set agreement start date
- Set agreement end date
- Enter agreement description
- Enter total value
- Enter terms and conditions
- Record signature date
- Record signatory name
- Record witness name
- Link multiple agreements to donor

### Record Linking
- Link records to agreement
- Unlink records from agreement
- View records under agreement
- Bulk link records

### Restrictions
- Add restriction to agreement
- Set restriction type (access, reproduction, publication, exhibition, disposal, digital)
- Set restriction start date
- Set restriction end date
- Set restriction scope
- Enter restriction description
- Track restriction enforcement
- View active restrictions
- View expired restrictions

### Reminders
- Create agreement reminder
- Set reminder due date
- Set reminder type
- Assign reminder to staff member
- Mark reminder as completed
- View pending reminders
- Send reminder notifications
- Log notification history

### Documents
- Attach document to agreement
- Set document type
- Upload agreement scan
- Upload supporting documents
- View attached documents
- Download documents
- Delete documents

### Provenance
- Create provenance record
- Set provenance type
- Set acquisition date
- Enter provenance description
- Link provenance to agreement
- View provenance chain

### Accession Linking
- Link accession to agreement
- View accessions under agreement
- Track accession status

### Audit Trail
- Log agreement changes
- Track field changes (old/new values)
- View agreement history
- View who made changes

### Searching
- Search agreements by donor
- Search agreements by status
- Search agreements by type
- Search agreements by date range
- Filter by repository

### Reporting
- Generate agreement report
- Export agreements to CSV
- Print agreement details

---

## ahgExhibitionPlugin

### Exhibition Management
- Create new exhibition
- Edit exhibition
- Delete exhibition
- Duplicate exhibition
- Set exhibition title
- Set exhibition description
- Set exhibition type (permanent, temporary, traveling, online, pop-up)
- Set venue/gallery
- Set start date
- Set end date
- Set opening hours
- Enter curator name
- Enter organizer

### Exhibition Status Workflow
- Set status to concept
- Set status to planning
- Set status to preparation
- Set status to installation
- Set status to open
- Set status to closed
- Set status to archived
- Set status to cancelled

### Object Selection
- Add object to exhibition
- Remove object from exhibition
- Set object display order
- Set object section
- Set object label text
- Check object availability
- Flag objects on loan

### Section Organization
- Create exhibition section
- Edit section
- Delete section
- Set section title
- Set section description
- Set section order
- Assign objects to section

### Storyline/Narrative
- Create storyline
- Add narrative stops
- Set stop order
- Set stop content (text)
- Set stop media (audio/video)
- Link stop to object
- Set stop duration

### Environmental Requirements
- Set required temperature range
- Set required humidity range
- Set maximum light level (lux)
- Set UV requirements
- Note special requirements

### Event Scheduling
- Create exhibition event
- Set event type (opening, closing, talk, tour, workshop, performance)
- Set event date and time
- Set event location
- Set event capacity
- Enter event description
- Track RSVPs

### Checklists
- Create preparation checklist
- Create installation checklist
- Create closing checklist
- Add checklist items
- Mark items complete
- Assign items to staff
- Set due dates

### Loan Integration
- Track borrowed items
- Link to loan records
- Monitor loan return dates
- Insurance tracking

### Visitor Tracking
- Set expected visitor count
- Enter actual visitor count
- Track by day/week/month
- Calculate attendance rates

### Insurance
- Set exhibition insurance value
- Track per-object values
- Sum total insured value

### Venue Management
- Create venue record
- Set venue name
- Set venue address
- Set venue capacity
- Set venue facilities

### Output
- Generate object list (CSV)
- Generate object list (JSON)
- Print exhibition guide
- Print label copy

---

## ahgExportPlugin

### Export Formats
- Export to CSV (ISAD-G compliant, 52 columns)
- Export to JSON
- Export to XML (generic)
- Export to EAD 2002
- Export to EAD3
- Export to Dublin Core XML
- Export to MODS XML

### Sector-Specific Export
- Archive export profile
- Museum export profile
- Library export profile
- Gallery export profile
- DAM export profile

### Export Scope
- Export single record
- Export record with descendants
- Export record without descendants
- Export entire repository
- Export search results
- Export collection

### Export Options
- Include digital object paths
- Include only published records
- Include draft records
- Select culture/language
- Select fields to include
- Exclude specific fields

### Package Export
- Create ZIP package
- Include metadata file
- Include digital objects
- Include manifest file
- Generate checksums

### Scheduling
- Schedule recurring export
- Set export frequency
- Set export time
- Email export on completion

### Delivery
- Download export directly
- Email export file
- Save to server path

### Statistics
- Count records in export
- Count digital objects in export
- Calculate export file size
- Track export history

### CLI Commands
- php symfony export:csv
- php symfony export:ead
- php symfony export:dc
- php symfony export:package

---

## ahgExtendedRightsPlugin

### Rights Statements (RightsStatements.org)
- Apply "In Copyright" statement
- Apply "In Copyright - EU Orphan Work"
- Apply "In Copyright - Educational Use Permitted"
- Apply "In Copyright - Non-Commercial Use Permitted"
- Apply "In Copyright - Rights-holder(s) Unlocatable or Unidentifiable"
- Apply "No Copyright - Contractual Restrictions"
- Apply "No Copyright - Non-Commercial Use Only"
- Apply "No Copyright - Other Known Legal Restrictions"
- Apply "No Copyright - United States"
- Apply "Copyright Not Evaluated"
- Apply "Copyright Undetermined"
- Apply "No Known Copyright"

### Creative Commons Licenses
- Apply CC0 (Public Domain)
- Apply CC BY
- Apply CC BY-SA
- Apply CC BY-NC
- Apply CC BY-NC-SA
- Apply CC BY-ND
- Apply CC BY-NC-ND
- Set CC license version

### Traditional Knowledge Labels
- Apply TK Attribution
- Apply TK Non-Commercial
- Apply TK Community Voice
- Apply TK Community Use Only
- Apply TK Outreach
- Apply TK Open
- Apply TK Verified
- Apply TK Seasonal
- Apply TK Women General
- Apply TK Men General
- Apply TK Culturally Sensitive

### Rights Holder Information
- Enter rights holder name
- Enter rights holder URI
- Enter rights holder role
- Enter contact information

### Embargo Management
- Create embargo on record
- Set embargo end date
- Set embargo as permanent
- Set embargo type (full, metadata_only, digital_only, partial)
- Enter embargo reason
- Lift embargo manually
- Auto-release on expiry date
- Process embargo expiry job

### Embargo Exceptions
- Grant user exception
- Grant group exception
- Grant IP range exception
- Set exception start date
- Set exception end date
- Revoke exception

### Embargo Notifications
- Send 30-day expiry warning
- Send 7-day expiry warning
- Send 1-day expiry warning
- Send embargo lifted notification
- Configure notification recipients

### Batch Operations
- Batch apply rights statement
- Batch apply CC license
- Batch apply TK label
- Batch create embargoes
- Batch lift embargoes

### Rights Display
- Show rights badge on record
- Show embargo indicator
- Show TK label badge
- Display rights text
- Display license icon

### Reporting
- Report active embargoes
- Report expiring embargoes
- Report by rights statement
- Export rights data

---

## ahgFavoritesPlugin

### Favorite Items
- Add record to favorites
- Remove record from favorites
- Check if record is favorited
- View my favorites list
- Sort favorites by date added
- Sort favorites by title
- Filter favorites by type

### Favorite Collections
- Add collection to favorites
- Remove collection from favorites
- View favorite collections

### Sharing
- Share favorites list
- Generate shareable link
- Set sharing permissions
- Revoke sharing

### Export
- Export favorites to CSV
- Export favorites to PDF
- Print favorites list

### Statistics
- Count total favorites
- Count favorites by type
- Most favorited records

---

## ahgFeedbackPlugin

### Feedback Submission
- Submit feedback form
- Enter feedback subject
- Enter feedback message
- Select feedback category
- Submit anonymously
- Attach screenshot
- Rate experience (1-5)

### Feedback Categories
- General feedback
- Bug report
- Feature request
- Content suggestion
- Accessibility issue
- Other

### Admin Dashboard
- View all feedback
- Filter by category
- Filter by status
- Filter by date
- Search feedback
- Sort by date
- Sort by priority

### Feedback Processing
- Set feedback status (new, in progress, resolved, closed)
- Assign to staff member
- Add internal notes
- Reply to submitter
- Mark as resolved

### Notifications
- Notify staff of new feedback
- Notify submitter of response
- Notify on status change

### Statistics
- Count feedback by category
- Count feedback by status
- Average rating
- Response time metrics

---

## ahgFormsPlugin

### Form Builder
- Create new form
- Edit form
- Delete form
- Duplicate form
- Set form title
- Set form description
- Set form instructions
- Enable/disable form

### Field Types
- Text input field
- Textarea field
- Email field
- Phone field
- Number field
- Date field
- Time field
- DateTime field
- Select dropdown
- Multi-select
- Checkbox
- Checkbox group
- Radio buttons
- File upload
- Hidden field
- HTML content block

### Field Properties
- Set field label
- Set field placeholder
- Set field help text
- Set default value
- Mark field as required
- Set field order
- Set field width

### Validation Rules
- Required field validation
- Email format validation
- Phone format validation
- Number range validation
- Text length validation
- Pattern/regex validation
- File type validation
- File size validation
- Custom validation message

### Conditional Logic
- Show field based on condition
- Hide field based on condition
- Require field based on condition
- Set condition field
- Set condition operator (equals, not equals, contains, etc.)
- Set condition value

### Form Submission
- Submit form
- Save partial submission
- Resume partial submission
- Confirmation message
- Redirect after submit

### Submission Management
- View all submissions
- Filter submissions
- Search submissions
- View submission details
- Delete submission
- Export submissions to CSV
- Export submissions to Excel

### Form Analytics
- Count total submissions
- Submissions by date
- Completion rate
- Average completion time
- Field-level analytics

### Notifications
- Email on submission
- Configure recipients
- Custom email template

---

## ahgGalleryPlugin

### Gallery Views
- Grid view display
- Masonry layout
- Carousel view
- Slideshow view
- Full-screen view

### Grid Configuration
- Set thumbnail size (small, medium, large, extra-large)
- Set columns per row
- Set gap between items
- Enable/disable image borders
- Enable/disable captions

### Lightbox
- Open image in lightbox
- Navigate between images
- Zoom in/out
- Download original
- View image info
- Close lightbox

### Carousel
- Auto-advance slides
- Set slide duration
- Manual navigation
- Dot indicators
- Arrow navigation
- Pause on hover

### Filtering
- Filter by subject
- Filter by creator
- Filter by date range
- Filter by collection
- Text search within gallery

### Featured Items
- Mark item as featured
- Set featured order
- Display featured carousel
- Feature on homepage

### Collections
- Create gallery collection
- Add items to collection
- Remove items from collection
- Reorder items
- Set collection cover image
- Publish collection

### Publications
- Create gallery publication
- Set publication date
- Set publication description
- Link items to publication

---

## ahgHeritageAccountingPlugin

### GRAP 103 Compliance
- Record heritage asset acquisition cost
- Record current valuation
- Record valuation date
- Record valuation method
- Record valuer name
- Set asset category
- Track accumulated depreciation
- Calculate net book value

### IPSAS 45 Alignment
- Map to IPSAS 45 categories
- International reporting format
- Public sector compliance

### Asset Valuation
- Create valuation record
- Set valuation type (acquisition, revaluation, impairment)
- Enter valuation amount
- Enter valuation currency
- Enter valuation date
- Enter valuer credentials
- Attach valuation report

### Depreciation
- Set depreciation method
- Set useful life
- Calculate annual depreciation
- Record depreciation entries
- Track accumulated depreciation

### Financial Reporting
- Generate asset register
- Generate valuation summary
- Generate depreciation schedule
- Export to financial system format
- Audit trail for valuations

### Categories
- Define heritage asset categories
- Set category depreciation rules
- Assign assets to categories

---

## ahgHeritagePlugin

### Heritage Site Management
- Register heritage site
- Edit site details
- Delete site record
- Set site name
- Set site type
- Enter site description
- Enter historical significance

### Location Tracking
- Enter site address
- Store GPS coordinates (polygon/boundary)
- Store site area (hectares)
- Link to map
- Mark site boundaries

### Conservation
- Create conservation plan
- Set conservation priority
- Schedule conservation activities
- Track conservation budget
- Record conservation works

### Condition Monitoring
- Assess site condition
- Record condition changes
- Schedule inspections
- Track deterioration
- Alert on urgent issues

### Significance Assessment
- Document heritage value
- Historical significance
- Cultural significance
- Architectural significance
- Scientific significance
- Social significance
- Statement of significance

### Protection Status
- Record protection status
- Set protection level
- Record proclamation date
- Track status changes

---

## ahgICIPPlugin

### ICIP Metadata
- Enter indigenous community
- Enter cultural group
- Enter language group
- Set cultural protocols
- Set access restrictions
- Document traditional knowledge

### Cultural Protocols
- Define viewing protocols
- Define reproduction protocols
- Define distribution protocols
- Set gender restrictions
- Set ceremony restrictions
- Set seasonal restrictions

### Community Consent
- Record consent status
- Record consent date
- Record consent provider
- Attach consent documentation
- Track consent expiry

### Repatriation
- Flag for repatriation
- Track repatriation status
- Record repatriation date
- Link to community records
- Digital repatriation support

### Sensitive Content
- Mark as culturally sensitive
- Set sensitivity level
- Restrict public access
- Require approval for access

---

## ahgIiifPlugin

### IIIF Manifests
- Generate IIIF Presentation 3 manifest
- Generate manifest for single image
- Generate manifest for multi-page document
- Generate manifest for compound object
- Include canvas annotations
- Include structural metadata
- Set manifest label
- Set manifest description
- Set rights/license

### IIIF Image API
- Serve images via IIIF Image API
- Support region extraction
- Support size scaling
- Support rotation
- Support quality (color, gray, bitonal)
- Support format conversion

### Viewers
- Embed Universal Viewer
- Embed Mirador viewer
- Configure viewer options
- Full-screen mode
- Thumbnail navigation

### Annotations
- Create Web Annotation
- Create text annotation
- Create tag annotation
- Create comment annotation
- Create transcription annotation
- Edit annotation
- Delete annotation
- View annotations on canvas
- Export annotations

### OCR Integration
- Extract text via OCR
- Store OCR output
- Display OCR text alongside image
- Search within OCR text
- Highlight search terms
- Correct OCR errors

### Manifest Validation
- Validate manifest syntax
- Check IIIF compliance
- Report validation errors

### Sharing
- Generate IIIF manifest URL
- Embed viewer in external sites
- Copy manifest link

---

## ahgLabelPlugin

### Label Templates
- Create label template
- Edit template
- Delete template
- Set template name
- Set template size (Avery sizes, custom)
- Set label dimensions
- Set margins
- Design label layout

### Label Content
- Include title field
- Include identifier/reference code
- Include repository name
- Include date
- Include location
- Include custom text
- Include barcode
- Include QR code
- Include logo/image

### Barcode Generation
- Generate Code 128 barcode
- Generate Code 39 barcode
- Generate EAN barcode
- Generate UPC barcode
- Set barcode data source
- Set barcode size

### QR Code Generation
- Generate QR code
- Link to record URL
- Custom QR content
- Set QR size
- Set error correction level

### Label Printing
- Print single label
- Print multiple labels
- Print label sheet
- Select records for printing
- Preview before print
- Set copies per label

### Batch Operations
- Batch print from search results
- Batch print from collection
- Batch print by identifier range

---

## ahgLandingPagePlugin

### Homepage Configuration
- Set welcome title
- Set welcome text
- Set welcome image
- Configure layout sections
- Enable/disable sections
- Set section order

### Featured Items
- Select featured records
- Set featured order
- Configure carousel settings
- Auto-rotate featured items
- Set rotation interval

### Collection Highlights
- Select featured collections
- Set collection display order
- Show collection thumbnails
- Show collection descriptions

### Recent Acquisitions
- Display recent additions
- Set number to display
- Filter by repository
- Auto-update list

### Statistics Widgets
- Display total records count
- Display total digital objects
- Display total collections
- Display researcher count
- Custom statistics

### Custom Sections
- Add HTML content block
- Add image block
- Add video embed
- Add link list
- Add news/announcements

### Styling
- Set background color
- Set background image
- Set section colors
- Custom CSS

---

## ahgLibraryPlugin

### Bibliographic Metadata
- Enter ISBN
- Enter ISBN-13
- Enter ISSN
- Enter LCCN
- Enter OCLC number
- Enter call number
- Set call number scheme (LOC, Dewey, custom)

### Publication Information
- Enter publisher name
- Enter place of publication
- Enter publication year
- Enter edition
- Enter series title
- Enter series number
- Enter volume number
- Enter issue number

### Physical Description
- Enter page count
- Enter dimensions
- Enter illustrations note
- Enter binding type
- Enter accompanying materials

### Copy Tracking
- Create copy record
- Set copy number
- Set copy location
- Set copy condition
- Set copy availability
- Track multiple copies per title

### FRBR Support
- Link to Work entity
- Link to Expression entity
- Link to Manifestation entity
- Create Item record
- Navigate FRBR relationships

### Circulation (if enabled)
- Check out item
- Check in item
- Place hold
- View circulation history
- Manage overdue items
- Send overdue notices

---

## ahgLoanPlugin

### Loan Types
- Outgoing loan (lending to others)
- Incoming loan (borrowing from others)
- Internal transfer
- Exhibition loan

### Loan Creation
- Create new loan
- Set loan type
- Set borrower/lender
- Set contact person
- Set loan purpose
- Set loan start date
- Set loan end date
- Set loan status

### Loan Status Workflow
- Set status to requested
- Set status to approved
- Set status to active
- Set status to overdue
- Set status to returned
- Set status to cancelled
- Set status to lost

### Object Management
- Add objects to loan
- Remove objects from loan
- Set object-level insurance value
- Check object availability
- Confirm object condition
- Set individual loan terms per object

### Insurance
- Set loan insurance value
- Set insurance provider
- Set policy number
- Attach insurance certificate
- Track coverage period

### Condition Reporting
- Create outgoing condition report
- Create incoming condition report
- Create return condition report
- Document condition changes
- Attach condition photos
- Compare before/after

### Documentation
- Attach loan agreement
- Attach correspondence
- Attach receipts
- Attach shipping documents
- Attach customs documents
- Generate loan agreement template

### Reminders
- Set due date reminders
- Set renewal reminders
- Set insurance expiry reminders
- Configure reminder recipients
- Send reminder notifications

### Loan History
- View complete loan history per object
- View loans by borrower/lender
- View active loans
- View overdue loans
- View completed loans

### Reporting
- Generate active loans report
- Generate overdue report
- Generate insurance valuation report
- Export loans to CSV

### CLI Commands
- php symfony loan:reminders
- php symfony loan:overdue-report

---

## ahgMetadataExportPlugin

### Export Configuration
- Create export configuration
- Set export name
- Set export format (CSV, JSON, XML)
- Set field mappings
- Set output field names
- Set field order
- Include/exclude fields

### Export Execution
- Run export manually
- Schedule export
- Set schedule frequency
- Set schedule time
- Email export on completion

### Field Selection
- Select specific fields to export
- Select all fields
- Exclude specific fields
- Map field to custom column name

### Transformations
- Transform values during export
- Date format conversion
- Text transformations
- Value mapping

### Filtering
- Filter records for export
- Filter by repository
- Filter by level
- Filter by date range
- Filter by publication status

### Output Options
- Download directly
- Save to server path
- Email to recipients
- FTP upload

---

## ahgMetadataExtractionPlugin

### Image Metadata
- Extract EXIF data
- Extract camera make/model
- Extract date taken
- Extract GPS coordinates
- Extract exposure settings
- Extract focal length
- Extract ISO
- Extract aperture
- Extract shutter speed
- Extract flash status
- Extract orientation
- Extract color space

### IPTC Metadata
- Extract headline
- Extract caption
- Extract keywords
- Extract creator
- Extract credit
- Extract copyright
- Extract source
- Extract city
- Extract country

### XMP Metadata
- Extract XMP Dublin Core
- Extract XMP Rights
- Extract XMP IPTC Extension
- Extract custom XMP fields

### PDF Metadata
- Extract PDF title
- Extract PDF author
- Extract PDF subject
- Extract PDF keywords
- Extract creation date
- Extract modification date
- Extract page count
- Extract PDF producer
- Extract PDF version

### Document Metadata
- Extract Word document metadata
- Extract Excel metadata
- Extract PowerPoint metadata

### Audio/Video Metadata
- Extract duration
- Extract bitrate
- Extract codec
- Extract dimensions
- Extract frame rate
- Extract audio channels
- Extract sample rate

### Auto-Population
- Configure auto-population rules
- Map extracted field to AtoM field
- Enable/disable auto-populate
- Overwrite existing values option
- Append to existing values option

### Extraction Settings
- Configure which formats to process
- Configure which fields to extract
- Set extraction priority
- Handle extraction errors

---

## ahgMigrationPlugin

### Legacy System Import
- Import from legacy AtoM
- Import from CONTENTdm
- Import from PastPerfect
- Import from Re:discovery
- Import from custom legacy systems

### Data Transformation
- Clean data during migration
- Standardize date formats
- Normalize text
- Remove invalid characters
- Fix encoding issues
- Merge duplicate values

### Validation
- Validate data before import
- Check required fields
- Check data types
- Check value ranges
- Check referential integrity
- Report validation errors

### Error Handling
- Log migration errors
- Skip invalid records
- Attempt error correction
- Generate error report
- Review and fix errors

### Rollback
- Create rollback point
- Undo failed migration
- Restore previous state

### Progress Tracking
- View migration progress
- Count records processed
- Count records created
- Count records updated
- Count records failed
- Estimate completion time

---

## ahgMultiTenantPlugin

### Tenant Management
- Create tenant
- Edit tenant settings
- Delete tenant
- Enable/disable tenant
- Set tenant name
- Set tenant identifier
- Set tenant domain

### Tenant Isolation
- Separate databases per tenant
- Separate file storage per tenant
- Separate settings per tenant
- Separate users per tenant

### Tenant Configuration
- Configure tenant-specific settings
- Set tenant theme
- Set tenant branding
- Configure tenant plugins

### Tenant Switching
- Switch between tenants (admin)
- View tenant list
- Access tenant admin panel

### Resource Management
- Allocate storage per tenant
- Set storage limits
- Monitor storage usage
- Allocate user limits

---

## ahgMuseumPlugin

### Spectrum 5.0 Fields
- Enter object number
- Enter other number
- Set acquisition method
- Set acquisition date
- Enter acquisition source
- Enter brief description
- Enter physical description
- Enter inscription/marks
- Enter object name/title
- Enter object type
- Enter classification
- Enter production date
- Enter production place
- Enter producer/maker
- Enter materials
- Enter techniques
- Enter measurements
- Enter dimensions
- Enter condition

### CCO Compliance
- Categories for Description of Works of Art fields
- Object/work type
- Materials and techniques
- Measurements
- Inscriptions and marks
- State/edition
- Style/period/group
- Subject matter
- Context

### Location Tracking
- Set current location
- Set normal location
- Record location history
- Set location date
- Set location reason (display, storage, loan, conservation)

### Acquisition
- Create acquisition record
- Set acquisition number
- Set acquisition method
- Set acquisition date
- Link acquisition to donor
- Set acquisition value
- Attach acquisition documentation

### Deaccession
- Create deaccession record
- Set deaccession method
- Set deaccession date
- Set deaccession reason
- Set disposal method
- Track disposal proceeds

### Object Relationships
- Link related objects
- Set relationship type
- Link parts to whole
- Link components

---

## ahgPreservationPlugin

### OAIS Compliance
- Create SIP (Submission Information Package)
- Validate SIP
- Create AIP (Archival Information Package)
- Create DIP (Dissemination Information Package)
- Track package status
- Package metadata management

### Checksum Verification
- Calculate MD5 checksum
- Calculate SHA-1 checksum
- Calculate SHA-256 checksum
- Calculate SHA-512 checksum
- Store checksums
- Verify checksums
- Report checksum failures
- Schedule fixity checking

### Format Identification
- Identify file format
- Query PRONOM registry
- Store PUID (PRONOM ID)
- Detect format risks
- Flag obsolete formats
- Recommend format migration

### Preservation Metadata (PREMIS)
- Record preservation events
- Record preservation agents
- Record preservation rights
- Store PREMIS XML
- Generate PREMIS report

### Format Migration
- Plan format migration
- Identify target format
- Execute migration
- Verify migration success
- Track migration history
- Link derivatives to originals

### Fixity Scheduling
- Schedule fixity checks
- Set check frequency
- Configure alert threshold
- Send failure alerts
- View fixity report

### Preservation Policies
- Create preservation policy
- Set retention period
- Set action triggers
- Configure format preferences
- Apply policies to collections

### Storage Management
- Track storage locations
- Monitor storage capacity
- Replicate to secondary storage
- Verify replication

---

## ahgPrivacyPlugin

### Personal Data Marking
- Mark record as containing personal data
- Set personal data type
- Set data sensitivity level
- Flag PII fields
- Flag sensitive data fields

### Redaction
- Redact text in field
- Create redaction rule
- Apply redaction pattern
- Preview redaction
- Undo redaction
- Redact in PDF export
- Redact in image export
- Selective redaction (internal/external)

### Right to Erasure
- Receive erasure request
- Review erasure request
- Execute erasure
- Document erasure
- Exempt from erasure (with justification)
- Log erasure actions

### Data Subject Access Requests (DSAR)
- Receive access request
- Identify relevant records
- Compile data package
- Review before release
- Track request deadline
- Document response

### Consent Management
- Record consent
- Set consent type
- Set consent date
- Set consent expiry
- Record consent source
- Withdraw consent
- Track consent history

### Privacy Impact Assessment
- Create PIA
- Assess privacy risks
- Document mitigations
- Review and approve PIA
- Link PIA to processing activities

### Compliance Reporting
- Generate POPIA compliance report
- Generate GDPR compliance report
- Generate CCPA compliance report
- Generate data inventory
- Generate processing register

### Data Retention
- Set retention periods
- Apply retention policies
- Flag for deletion
- Execute retention actions
- Document retention decisions

### Access Logging
- Log personal data access
- Log data exports
- Log data sharing
- Generate access report

---

## ahgProvenancePlugin

### Provenance Records
- Create provenance record
- Edit provenance record
- Delete provenance record
- Link provenance to object

### Provenance Events
- Record acquisition event
- Record transfer event
- Record custody change
- Record location change
- Record ownership change
- Record loan event
- Set event date
- Set event date range
- Enter event description
- Enter event source

### Ownership History
- Document previous owners
- Set ownership period
- Enter ownership documentation
- Track chain of title

### Custody History
- Document custodians
- Set custody period
- Enter custody notes
- Track physical custody changes

### Event Types
- Acquisition (purchase, gift, bequest, transfer)
- Sale/disposal
- Loan (in/out)
- Deposit
- Theft/loss
- Recovery
- Repatriation
- Conservation treatment
- Exhibition

### Documentation
- Attach provenance documents
- Link to correspondence
- Link to receipts
- Link to legal documents

### Provenance Research
- Flag research status
- Mark gaps in provenance
- Note disputed ownership
- Track research sources

### Display
- Generate provenance timeline
- Display provenance narrative
- Print provenance report

---

## ahgReportBuilderPlugin

### Report Designer
- Create new report
- Edit report
- Delete report
- Duplicate report
- Set report name
- Set report description
- Set report category

### Data Selection
- Select data source tables
- Join related tables
- Filter data
- Set filter conditions
- Set filter operators
- Combine filters (AND/OR)

### Field Selection
- Add fields to report
- Set field order
- Set field labels
- Format field values
- Calculate derived fields
- Aggregate fields (sum, count, avg, min, max)
- Group by field

### Formatting
- Set page orientation
- Set page size
- Set margins
- Add header
- Add footer
- Add page numbers
- Add date/time stamp
- Add logo

### Charts
- Add pie chart
- Add bar chart
- Add line chart
- Add area chart
- Configure chart data
- Set chart colors
- Set chart labels

### Output
- Preview report
- Export to PDF
- Export to Excel
- Export to CSV
- Export to HTML
- Print report

### Scheduling
- Schedule report
- Set schedule frequency
- Set schedule time
- Email on completion
- Configure recipients

### Permissions
- Set report visibility
- Share with users
- Share with groups
- Public reports

---

## ahgReportsPlugin

### Accession Reports
- Accessions by date range
- Accessions by donor
- Accessions by repository
- Accession summary
- Accession details

### Collection Reports
- Collection summary
- Collection growth
- Collection by level
- Collection by type
- Collection gaps analysis

### Condition Reports
- Condition summary
- Items by condition
- Conservation needed
- Treatment history
- Assessment schedule

### Loan Reports
- Active loans
- Overdue loans
- Loan history
- Loans by borrower
- Loans by object
- Insurance values

### Digital Object Reports
- Digital objects by format
- Digital objects by size
- Storage usage
- Digitization progress
- Missing digital objects

### User Reports
- User activity
- Login history
- Actions by user
- User permissions

### Statistical Reports
- Records by repository
- Records by level
- Records by date
- Records created per month
- Records updated per month

### Compliance Reports
- Audit log summary
- Access log report
- POPIA compliance
- NARSSA compliance

### Custom Reports
- Save report parameters
- Load saved report
- Export report results
- Schedule report

---

## ahgRequestToPublishPlugin

### Publication Requests
- Submit request to publish record
- Enter request justification
- Select records for publication
- Submit batch request
- View request status
- Cancel pending request

### Request Status
- Pending review
- Under review
- Approved
- Rejected
- Cancelled

### Admin Review
- View pending requests
- View request details
- View record preview
- Add review notes
- Request additional information

### Approval Actions
- Approve publication request
- Reject with reason
- Defer decision
- Escalate to supervisor

### Auto-Publish
- Enable auto-publish on approval
- Set publication delay
- Queue for publication
- Publish immediately

### Notifications
- Notify requester of submission
- Notify reviewer of new request
- Notify requester of decision
- Notify on publication

### Request History
- View all requests
- Filter by status
- Filter by requester
- Filter by date
- Export request history

---

## ahgResearchPlugin

### Researcher Registration
- Register as researcher (public form)
- Complete researcher profile
- Enter personal details
- Enter institutional affiliation
- Enter research topic
- Enter research purpose
- Upload reference letter
- Accept terms and conditions
- Submit for approval

### Researcher Status
- Pending approval
- Approved
- Rejected (with reason)
- Expired
- Suspended

### Researcher Profile
- View my profile
- Edit profile details
- Change password
- Upload photo
- Link ORCID ID
- Manage API keys
- View registration expiry
- Request renewal

### Reading Room Booking
- View available dates
- View available time slots
- Select reading room
- Select date and time
- Enter visit purpose
- Request specific materials
- Add notes
- Submit booking request
- View booking confirmation
- Cancel booking
- Modify booking (before cutoff)
- View booking history

### Booking Status
- Pending confirmation
- Confirmed
- Checked in
- Checked out
- Completed
- No show
- Cancelled

### Check In/Out
- Staff check in researcher
- Record arrival time
- Assign seat
- Staff check out researcher
- Record departure time
- Record materials used

### Seat Management
- View room seats
- Add seat to room
- Edit seat details
- Set seat number
- Set seat label
- Set seat type (standard, accessible, computer, microfilm, oversize, quiet, group)
- Set seat zone
- Set seat amenities (power, lamp, computer, magnifier)
- Activate/deactivate seat
- Bulk create seats (pattern-based)
- View seat occupancy
- View real-time availability
- Assign seat to booking
- Release seat
- View seat assignment history

### Equipment Management
- View room equipment
- Add equipment item
- Edit equipment details
- Set equipment name
- Set equipment code
- Set equipment type (microfilm_reader, microfiche_reader, scanner, computer, magnifier, book_cradle, light_box, camera_stand, gloves, weights)
- Set equipment brand/model
- Set equipment location
- Set equipment condition (excellent, good, fair, needs_repair, out_of_service)
- Set max booking hours
- Mark as available/unavailable
- Log maintenance
- Set next maintenance date
- View equipment usage statistics

### Material Requests
- Request materials for visit
- Specify reference/call number
- Specify box/folder numbers
- Set request priority (normal, high, rush)
- View request status
- Cancel material request

### Retrieval Queue
- View retrieval queue dashboard
- View queue counts by status
- Filter by queue (new, rush, retrieval, transit, delivery, curatorial, return)
- Select requests for batch update
- Update request status
- Move to different queue
- Add notes to request
- Print call slips
- Batch print call slips
- Mark call slip as printed

### Call Slip Printing
- Generate call slip
- Include request details
- Include item details
- Include researcher details
- Include booking details
- Include barcode
- Print individual slip
- Print batch of slips
- Customize call slip template

### Walk-In Visitors
- Register walk-in visitor
- Enter visitor name
- Enter visitor contact
- Enter visitor ID
- Enter organization
- Enter purpose of visit
- Acknowledge reading room rules
- Assign seat (optional)
- Check in walk-in
- Check out walk-in
- View current walk-ins
- Convert walk-in to registered researcher

### Collections
- Create research collection
- Add items to collection
- Remove items from collection
- Rename collection
- Delete collection
- View collection items
- Share collection
- Export collection

### Annotations
- Add annotation to record
- Enter annotation text
- Set annotation type
- Set page/section reference
- Mark as private/shared
- Edit annotation
- Delete annotation
- View my annotations
- View annotations by record

### Citations
- Generate citation for record
- Select citation style (Chicago, APA, MLA, Harvard, Turabian)
- Copy citation text
- Save citation
- View citation history
- Export citations

### Bibliographies
- Create bibliography
- Add entries to bibliography
- Edit bibliography entry
- Remove entry
- Reorder entries
- Export bibliography (RIS, BibTeX, formatted)
- Share bibliography

### Research Projects
- Create project
- Edit project details
- Set project title
- Set project description
- Set project dates
- Add project milestones
- Invite collaborators
- Manage collaborator permissions
- View project activity
- Archive project

### Team Workspaces
- Create workspace
- Invite members
- Set member roles
- Share resources
- Discussion threads
- Collaborative annotations
- Leave workspace

### ORCID Integration
- Connect ORCID account
- Disconnect ORCID
- Import ORCID profile data
- Display ORCID badge

### API Keys
- Generate personal API key
- View API keys
- Revoke API key
- Set key permissions
- View key usage

### Researcher Renewal
- Submit renewal request
- Update profile for renewal
- Staff review renewal
- Approve renewal
- Reject renewal
- Set new expiry date

### Admin Functions
- View pending researchers
- Approve researcher
- Reject researcher (with reason)
- Reset researcher password
- View researcher details
- Edit researcher type
- Manage researcher types
- View research statistics

---

## ahgRicExplorerPlugin

### RiC Entity Browsing
- Browse Record Resources
- Browse Agents
- Browse Activities
- Browse Rules
- Browse Places
- Browse Dates
- Browse Events

### Relationship Navigation
- View entity relationships
- Navigate to related entities
- Visualize relationship graph
- Filter by relationship type

### Entity Search
- Search across RiC entities
- Filter by entity type
- Full-text search
- Faceted search

### Data Export
- Export RiC data (RDF)
- Export RiC data (JSON-LD)
- Export entity relationships

---

## ahgRightsPlugin

### Rights Statements
- Apply rights statement to record
- Select from RightsStatements.org list
- Select Creative Commons license
- Enter custom rights statement
- Set rights basis
- Set rights holder

### Rights Display
- Display rights statement on record
- Show rights icon
- Show rights badge
- Display rights text

### Batch Rights
- Apply rights to multiple records
- Apply rights to collection
- Apply rights to repository

---

## ahgSecurityClearancePlugin

### Clearance Levels
- Define clearance level 0 (Public)
- Define clearance level 1 (Internal)
- Define clearance level 2 (Confidential)
- Define clearance level 3 (Secret)
- Define clearance level 4 (Top Secret)
- Define clearance level 5 (Restricted)
- Customize level names
- Customize level colors

### Record Classification
- Classify record with clearance level
- Set classification reason
- Set classification date
- Set declassification date
- Review classification
- Downgrade classification
- Upgrade classification

### User Clearance
- Assign clearance level to user
- Set clearance effective date
- Set clearance expiry date
- Revoke clearance
- View user clearance history

### Access Enforcement
- Check user clearance vs record classification
- Block access if insufficient clearance
- Allow access if clearance sufficient
- Log access attempts

### Watermarking
- Apply watermark to classified downloads
- Include classification level
- Include user identifier
- Include date/time
- Include copy number

### Clearance Requests
- Request clearance upgrade
- Justify upgrade request
- Review clearance request
- Approve/deny request
- Notify requester

### Reporting
- Report classified records
- Report by classification level
- Report declassification due
- Report clearance by user

---

## ahgSemanticSearchPlugin

### Semantic Search
- Natural language search query
- Semantic query understanding
- Concept extraction
- Intent recognition

### Entity-Based Search
- Search by person entity
- Search by organization entity
- Search by place entity
- Search by date/period
- Combine entity searches

### Concept Matching
- Find conceptually related records
- Expand query with concepts
- Include related terms
- Concept hierarchy navigation

### Synonym Support
- Automatic synonym expansion
- Configure synonym dictionaries
- Language-specific synonyms
- Domain-specific synonyms

### Relevance Ranking
- Semantic relevance scoring
- Boost exact matches
- Boost recent records
- Boost popular records
- Configure ranking factors

### Search Suggestions
- Suggest query refinements
- Suggest related searches
- Did you mean corrections
- Autocomplete with concepts

---

## ahgSettingsPlugin

### Settings Interface
- View all settings
- Search settings
- Filter by category
- Filter by plugin

### Setting Management
- Edit setting value
- Reset to default
- View setting description
- View setting type
- Validate setting value

### Setting Categories
- General settings
- Display settings
- Search settings
- Security settings
- Email settings
- Plugin-specific settings

### Import/Export
- Export settings to file
- Import settings from file
- Backup settings
- Restore settings

### Setting Types
- Text input
- Number input
- Boolean toggle
- Select dropdown
- Multi-select
- Color picker
- Date picker
- File upload

---

## ahgSpectrumPlugin

### Spectrum 5.0 Procedures
- Object entry
- Acquisition
- Location and movement control
- Cataloguing
- Object condition checking
- Conservation
- Risk management
- Insurance and indemnity management
- Valuation control
- Audit
- Rights management
- Use of collections
- Object exit
- Loans in
- Loans out
- Documentation
- Reproduction
- Emergency planning
- Collections care
- Deaccessioning and disposal

### Spectrum Units
- Object identification
- Object name
- Object number
- Object production
- Object association
- Object location
- Object collection
- Object owner's contribution
- Object viewer's contribution
- Object valuation
- Object audit
- Object rights
- Object use

### CIDOC CRM Mapping
- Map Spectrum to CIDOC-CRM
- Export CIDOC-CRM RDF
- Import CIDOC-CRM data

### Validation
- Validate against Spectrum
- Check required fields
- Check controlled vocabularies
- Generate compliance report

### Reporting
- Spectrum procedure checklist
- Spectrum compliance report
- Object entry report
- Exit report

---

## ahgStatisticsPlugin

### Collection Statistics
- Total records count
- Records by repository
- Records by level of description
- Records by publication status
- Records created over time
- Records updated over time
- Collection growth chart

### Digital Object Statistics
- Total digital objects
- Digital objects by format
- Digital objects by MIME type
- Total storage used
- Storage by repository
- Digitization rate
- Missing digital objects

### Access Statistics
- Page views
- Record views
- Search queries
- Downloads
- API requests
- Views by record
- Views by repository
- Views over time

### User Statistics
- Registered users
- Active users
- Logins over time
- User actions
- User registrations

### Researcher Statistics
- Registered researchers
- Approved researchers
- Pending researchers
- Bookings over time
- Materials requested
- Check-ins

### Custom Dashboards
- Create dashboard
- Add widgets
- Configure widget data
- Set widget layout
- Share dashboard

### Export
- Export statistics to CSV
- Export statistics to Excel
- Export charts as images
- Schedule statistics export

### Trend Analysis
- Compare periods
- Calculate growth rate
- Identify trends
- Forecast projections

---

## ahgThemeB5Plugin

### Responsive Design
- Mobile-friendly layouts
- Tablet optimization
- Desktop layouts
- Responsive navigation
- Touch-friendly controls

### Bootstrap 5 Components
- Navigation bar
- Breadcrumbs
- Cards
- Buttons
- Forms
- Modals
- Alerts
- Badges
- Tables
- Pagination
- Tabs
- Accordions
- Tooltips
- Popovers

### Accessibility
- WCAG 2.1 compliance
- Keyboard navigation
- Screen reader support
- Skip navigation links
- Focus indicators
- High contrast support
- Text resizing

### Customization
- Color scheme configuration
- Logo upload
- Favicon upload
- Custom CSS
- Font selection

### Dark Mode
- Enable dark mode
- Auto-detect system preference
- User toggle
- Dark mode styling

### Print Styles
- Print-friendly pages
- Hide navigation in print
- Optimize images for print
- Page break control

### Language Support
- RTL language support
- Language switcher
- Translation support

---

## ahgTiffPdfMergePlugin

### TIFF Merging
- Merge multiple TIFF images
- Set merge order
- Handle multi-page TIFFs
- Preserve resolution
- Preserve color depth

### PDF Merging
- Merge TIFF images into PDF
- Merge PDF files
- Set PDF metadata
- Set PDF compression
- Set PDF quality

### Sequence Processing
- Process image sequence
- Auto-detect sequence order
- Number-based ordering
- Name-based ordering

### Batch Operations
- Batch merge multiple sets
- Queue merge operations
- Track batch progress
- Handle batch errors

### Output Options
- Set output filename
- Set output location
- Set output format
- Download result
- Link to record

---

## ahgVendorPlugin

### Vendor Management
- Create vendor record
- Edit vendor details
- Delete vendor
- Set vendor name
- Set vendor type
- Enter contact information
- Enter address
- Enter tax information

### Service Tracking
- Track services used
- Link services to vendor
- Track service dates
- Track service costs
- Rate vendor service

### Contract Management
- Create contract record
- Set contract terms
- Set contract dates
- Attach contract document
- Set contract value
- Track contract renewal

### Performance
- Rate vendor performance
- Enter performance notes
- Track issues
- Flag preferred vendors
- Flag problematic vendors

### Reporting
- Vendor list report
- Services by vendor
- Contracts by vendor
- Spending by vendor

---

## ahgWorkflowPlugin

### Workflow Designer
- Create workflow
- Edit workflow
- Delete workflow
- Set workflow name
- Set workflow description
- Set workflow trigger

### Workflow Steps
- Add step to workflow
- Set step name
- Set step action
- Set step order
- Set step assignee
- Set step conditions
- Set step timeout
- Remove step

### Step Types
- Approval step
- Review step
- Notification step
- Data update step
- Conditional branch step
- Parallel step
- End step

### Approvers
- Assign individual approver
- Assign role-based approver
- Set approval threshold
- Set escalation rules
- Set delegation rules

### Conditional Routing
- Branch based on field value
- Branch based on user role
- Branch based on record type
- Multiple condition support
- Default branch

### Notifications
- Notify on step assignment
- Notify on completion
- Notify on timeout
- Configure notification template
- Configure recipients

### Workflow Execution
- Start workflow instance
- View workflow status
- View current step
- Complete assigned step
- Approve/reject
- Add comments
- Escalate step
- Cancel workflow

### Monitoring
- View active workflows
- View completed workflows
- View workflow history
- View bottlenecks
- Performance metrics

### Reporting
- Workflow completion time
- Step completion time
- Approval rate
- Rejection reasons
- Escalation frequency

---

## Functionality Overlap

### Access Control Overlap
| Function | ahgSecurityClearancePlugin | ahgExtendedRightsPlugin | ahgPrivacyPlugin |
|----------|---------------------------|------------------------|-----------------|
| User access control | ✓ (clearance levels) | ✓ (embargo exceptions) | ✓ (PII access) |
| Record restrictions | ✓ (classification) | ✓ (embargoes) | ✓ (personal data) |
| Time-based access | ✓ (declassification) | ✓ (embargo dates) | ✓ (retention) |
| Watermarking | ✓ | | |

### Data Import Overlap
| Function | ahgDataMigrationPlugin | ahgMigrationPlugin |
|----------|----------------------|-------------------|
| CSV import | ✓ | ✓ |
| Legacy system import | ✓ (specific systems) | ✓ (general) |
| Field mapping | ✓ | ✓ |
| Data transformation | ✓ | ✓ |

### AI Processing Overlap
| Function | ahgAIPlugin | ahgMetadataExtractionPlugin |
|----------|------------|---------------------------|
| Metadata extraction | ✓ (content-based) | ✓ (file-embedded) |
| Entity recognition | ✓ | |
| Text extraction | ✓ (OCR) | ✓ (EXIF, IPTC) |

### Reporting Overlap
| Function | ahgReportsPlugin | ahgReportBuilderPlugin | ahgStatisticsPlugin |
|----------|-----------------|---------------------|-------------------|
| Pre-built reports | ✓ | | |
| Custom reports | | ✓ | |
| Dashboards | | | ✓ |
| Charts | | ✓ | ✓ |
| Scheduled reports | ✓ | ✓ | ✓ |

### Museum Standards Overlap
| Function | ahgMuseumPlugin | ahgSpectrumPlugin |
|----------|----------------|------------------|
| Spectrum fields | ✓ (core) | ✓ (full) |
| CCO fields | ✓ | |
| Procedures | | ✓ |

### Researcher Functions Overlap
| Function | ahgResearchPlugin | ahgFavoritesPlugin |
|----------|------------------|-------------------|
| Save items | ✓ (collections) | ✓ (favorites) |
| Share items | ✓ | ✓ |
| Export items | ✓ | ✓ |

---

*Document generated: February 2026*
*Heratio Framework v2.0*
