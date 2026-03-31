# ahgContactPlugin - Technical Documentation

**Version:** 1.0.0
**Category:** AHG Core
**Dependencies:** atom-framework, ahgCorePlugin

---

## Overview

The ahgContactPlugin provides extended contact information management for AtoM authority records (persons, organizations) and repositories. It extends the core AtoM contact_information table with additional fields for professional contact details while maintaining full backward compatibility.

---

## Architecture

```
+---------------------------------------------------------------------+
|                       ahgContactPlugin                               |
+---------------------------------------------------------------------+
|                                                                     |
|  +---------------------------------------------------------------+  |
|  |              Plugin Configuration                              |  |
|  |  ahgContactPluginConfiguration.class.php                       |  |
|  |  - Registers PSR-4 autoloader                                  |  |
|  |  - Connects to context.load_factories event                    |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |              ContactInformationRepository                      |  |
|  |  - CRUD operations on three tables                             |  |
|  |  - Joins contact_information + i18n + extended                 |  |
|  |  - Laravel Query Builder (Illuminate\Database)                 |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |              ContactInformationService                         |  |
|  |  - Business logic layer                                        |  |
|  |  - Address formatting                                          |  |
|  |  - Primary contact management                                  |  |
|  +---------------------------------------------------------------+  |
|                              |                                      |
|                              v                                      |
|  +---------------------------------------------------------------+  |
|  |              Blade Templates                                   |  |
|  |  - _contactInformation.blade.php (view)                        |  |
|  |  - _contactEdit.blade.php (edit container)                     |  |
|  |  - _contactForm.blade.php (individual form)                    |  |
|  +---------------------------------------------------------------+  |
|                                                                     |
+---------------------------------------------------------------------+
```

---

## Database Schema

### ERD Diagram

```
+------------------------------------+
|        contact_information         |   (AtoM Core Table)
+------------------------------------+
| PK id INT                          |
|    actor_id INT                    |------+
|    primary_contact TINYINT         |      |
|    contact_person VARCHAR(1024)    |      |
|    street_address TEXT             |      |
|    website VARCHAR(1024)           |      |
|    email VARCHAR(255)              |      |
|    telephone VARCHAR(255)          |      |
|    fax VARCHAR(255)                |      |
|    postal_code VARCHAR(255)        |      |
|    country_code VARCHAR(255)       |      |
|    longitude FLOAT                 |      |
|    latitude FLOAT                  |      |
|    created_at DATETIME             |      |
|    updated_at DATETIME             |      |
|    source_culture VARCHAR(16)      |      |
+------------------------------------+      |
         |                                  |
         | 1:N                              | N:1
         v                                  v
+------------------------------------+    +----------------+
|    contact_information_i18n        |    |     actor      |
+------------------------------------+    +----------------+
| PK id INT                          |    | PK id INT      |
| PK culture VARCHAR(16)             |    |    ...         |
|    contact_type VARCHAR(1024)      |    +----------------+
|    city VARCHAR(1024)              |
|    region VARCHAR(1024)            |
|    note TEXT                       |
+------------------------------------+
         |
         | 1:1 (via FK)
         |
+------------------------------------+
|  contact_information_extended      |   (AHG Extension Table)
+------------------------------------+
| PK id INT                          |
| FK contact_information_id INT      |-------> contact_information.id
|    title VARCHAR(100)              |
|    role VARCHAR(255)               |
|    department VARCHAR(255)         |
|    cell VARCHAR(255)               |
|    id_number VARCHAR(50)           |
|    alternative_email VARCHAR(255)  |
|    alternative_phone VARCHAR(255)  |
|    preferred_contact_method ENUM   |
|    language_preference VARCHAR(16) |
|    notes TEXT                      |
|    created_at DATETIME             |
|    updated_at DATETIME             |
+------------------------------------+

Indexes:
+--------------------------------------------+
| uk_contact_id (contact_information_id)     |   UNIQUE
| fk_contact_info_ext -> contact_information |   CASCADE DELETE
+--------------------------------------------+
```

### SQL Schema

```sql
-- Core AtoM Table (DO NOT MODIFY)
-- contact_information

-- Core AtoM Table (DO NOT MODIFY)
-- contact_information_i18n

-- AHG Extension Table
CREATE TABLE IF NOT EXISTS `contact_information_extended` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `contact_information_id` INT NOT NULL,
  `title` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'Mr, Mrs, Dr, Prof, etc.',
  `role` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'Job title/position',
  `department` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'Department/Division',
  `cell` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'Mobile/Cell phone',
  `id_number` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'ID/Passport number',
  `alternative_email` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'Secondary email',
  `alternative_phone` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'Secondary phone',
  `preferred_contact_method` ENUM('email','phone','cell','fax','mail')
    COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `language_preference` VARCHAR(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL
    COMMENT 'Preferred communication language',
  `notes` TEXT COLLATE utf8mb4_unicode_ci
    COMMENT 'Additional notes',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_contact_id` (`contact_information_id`),
  CONSTRAINT `fk_contact_info_ext`
    FOREIGN KEY (`contact_information_id`)
    REFERENCES `contact_information` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Field Reference

### Core Fields (contact_information)

| Field | Type | Description |
|-------|------|-------------|
| id | INT | Primary key |
| actor_id | INT | FK to actor table |
| primary_contact | TINYINT | Is primary contact (0/1) |
| contact_person | VARCHAR(1024) | Contact person name |
| street_address | TEXT | Physical street address |
| website | VARCHAR(1024) | Website URL |
| email | VARCHAR(255) | Primary email address |
| telephone | VARCHAR(255) | Primary phone number |
| fax | VARCHAR(255) | Fax number |
| postal_code | VARCHAR(255) | ZIP/Postal code |
| country_code | VARCHAR(255) | ISO country code |
| longitude | FLOAT | GPS longitude |
| latitude | FLOAT | GPS latitude |
| source_culture | VARCHAR(16) | Default language |

### I18N Fields (contact_information_i18n)

| Field | Type | Description |
|-------|------|-------------|
| id | INT | FK to contact_information |
| culture | VARCHAR(16) | Language code (en, af, etc.) |
| contact_type | VARCHAR(1024) | Type of contact |
| city | VARCHAR(1024) | City name |
| region | VARCHAR(1024) | Province/State |
| note | TEXT | Additional notes |

### Extended Fields (contact_information_extended)

| Field | Type | Description |
|-------|------|-------------|
| title | VARCHAR(100) | Honorific (Mr, Mrs, Dr, Prof) |
| role | VARCHAR(255) | Job title/Position |
| department | VARCHAR(255) | Department/Division |
| cell | VARCHAR(255) | Mobile phone number |
| id_number | VARCHAR(50) | ID/Passport number |
| alternative_email | VARCHAR(255) | Secondary email |
| alternative_phone | VARCHAR(255) | Secondary phone |
| preferred_contact_method | ENUM | email/phone/cell/fax/mail |
| language_preference | VARCHAR(16) | Preferred language |
| notes | TEXT | Internal notes |

---

## Repository Methods

### ContactInformationRepository

```php
namespace AtomFramework\Extensions\Contact\Repositories;

class ContactInformationRepository
{
    // Retrieve contacts for an actor with all joined data
    public function getByActorId(int $actorId, string $culture = 'en'): Collection

    // Get primary contact for actor
    public function getPrimaryContact(int $actorId, string $culture = 'en'): ?object

    // Get contact by ID with all joined data
    public function getById(int $id, string $culture = 'en'): ?object

    // Create new contact with i18n and extended data
    public function create(array $data): int

    // Update existing contact
    public function update(int $id, array $data): bool

    // Delete contact and related records
    public function delete(int $id): bool

    // Check if actor has any contacts
    public function hasContacts(int $actorId): bool

    // Count contacts for actor
    public function countByActorId(int $actorId): int

    // Save from form data (create or update)
    public function saveFromForm(array $data): int
}
```

---

## Service Methods

### ContactInformationService

```php
namespace AtomFramework\Extensions\Contact\Services;

class ContactInformationService
{
    // Get all contacts for actor
    public function getForActor(int $actorId): Collection

    // Get primary contact
    public function getPrimary(int $actorId): ?object

    // Add new contact
    public function add(int $actorId, array $data): int

    // Update contact
    public function update(int $id, array $data): bool

    // Delete contact
    public function delete(int $id): bool

    // Set contact as primary
    public function setPrimary(int $actorId, int $contactId): bool

    // Format address for display
    public function formatAddress(object $contact): string

    // Check if actor has contacts
    public function hasContacts(int $actorId): bool
}
```

---

## Usage Examples

### Get Contacts for Actor

```php
use AtomFramework\Extensions\Contact\Services\ContactInformationService;

$service = new ContactInformationService();
$contacts = $service->getForActor($actorId);

foreach ($contacts as $contact) {
    echo $contact->contact_person;
    echo $contact->email;
    echo $contact->cell;  // Extended field
}
```

### Create New Contact

```php
use AtomFramework\Extensions\Contact\Repositories\ContactInformationRepository;

$repo = new ContactInformationRepository();

$contactId = $repo->create([
    'actor_id' => 123,
    'primary_contact' => 1,
    'contact_person' => 'John Smith',
    'email' => 'john@example.com',
    'telephone' => '+27 12 345 6789',
    'street_address' => '123 Main Street',
    'city' => 'Pretoria',
    'region' => 'Gauteng',
    'postal_code' => '0001',
    'country_code' => 'ZA',
    // Extended fields
    'title' => 'Dr',
    'role' => 'Director',
    'department' => 'Archives',
    'cell' => '+27 82 123 4567',
    'preferred_contact_method' => 'email',
    'language_preference' => 'en',
]);
```

### Update Contact

```php
$repo->update($contactId, [
    'role' => 'Senior Archivist',
    'department' => 'Special Collections',
    'preferred_contact_method' => 'cell',
]);
```

### Format Address for Display

```php
$service = new ContactInformationService();
$contact = $service->getPrimary($actorId);

if ($contact) {
    $formattedAddress = $service->formatAddress($contact);
    // Output:
    // 123 Main Street
    // Pretoria, Gauteng, 0001
    // ZA
}
```

---

## Template Integration

### Display Template

Include in authority record view:

```php
@include('Extensions.Contact.templates._contactInformation', ['resource' => $resource])
```

### Edit Template

Include in authority record edit form:

```php
@include('Extensions.Contact.templates._contactEdit', ['resource' => $resource])
```

### Form Data Structure

Contact form data is submitted as an array:

```php
// Form submission structure
$_POST['contacts'] = [
    0 => [
        'id' => 123,           // Existing contact ID (empty for new)
        'delete' => '',        // Set to '1' to delete
        'contact_person' => 'John Smith',
        'primary_contact' => 1,
        'email' => 'john@example.com',
        // ... other fields
    ],
    1 => [
        'id' => '',            // New contact
        'contact_person' => 'Jane Doe',
        // ... other fields
    ],
];
```

---

## Configuration

### Plugin Configuration Class

```php
class ahgContactPluginConfiguration extends sfPluginConfiguration
{
    public function initialize()
    {
        // Register PSR-4 autoloader for plugin classes
        $this->registerAutoloader();

        // Connect to context.load_factories for late initialization
        $this->dispatcher->connect('context.load_factories', [$this, 'loadContact']);
    }
}
```

### Autoloader Namespace

The plugin registers the following namespace:

```
AtomFramework\Extensions\Contact\
```

Mapped to:

```
plugins/ahgContactPlugin/lib/Extensions/Contact/
```

---

## File Structure

```
ahgContactPlugin/
|-- config/
|   +-- ahgContactPluginConfiguration.class.php
|-- lib/
|   +-- Extensions/
|       +-- Contact/
|           |-- Repositories/
|           |   +-- ContactInformationRepository.php
|           |-- Services/
|           |   +-- ContactInformationService.php
|           +-- templates/
|               |-- _contactEdit.blade.php
|               |-- _contactForm.blade.php
|               +-- _contactInformation.blade.php
+-- extension.json
```

---

## Integration Points

### With ahgPrivacyPlugin

Contact information may contain personal data subject to POPIA/GDPR compliance. The privacy plugin can:
- Mark contact records as containing personal information
- Apply retention policies to contact data
- Support data subject access requests

### With ahgDonorPlugin

Contact information links to donor records:
- Donor agreement contacts
- Depositor communication details
- Legal contact information

### With ahgLoanPlugin

Contact information supports loan workflows:
- Borrower contact details
- Insurance contact information
- Courier/shipping contacts

### With ahgResearchPlugin

Researcher contact information:
- Access request contacts
- Research visit scheduling
- Communication preferences

---

## Primary Contact Logic

When setting a contact as primary:

1. The repository clears `primary_contact` flag on all other contacts for the actor
2. Sets `primary_contact = 1` on the selected contact
3. This ensures only one primary contact exists per actor

```php
protected function clearPrimaryContact(int $actorId, ?int $exceptId = null): void
{
    $query = DB::table($this->table)
        ->where('actor_id', $actorId);

    if ($exceptId) {
        $query->where('id', '!=', $exceptId);
    }

    $query->update(['primary_contact' => 0]);
}
```

---

## Cascade Delete Behavior

When a contact_information record is deleted:
1. contact_information_i18n records are deleted (FK CASCADE)
2. contact_information_extended record is deleted (FK CASCADE)

The repository handles deletion order for safety:

```php
public function delete(int $id): bool
{
    // Delete extended first
    DB::table($this->extendedTable)
        ->where('contact_information_id', $id)
        ->delete();

    // Delete i18n records
    DB::table($this->i18nTable)
        ->where('id', $id)
        ->delete();

    // Delete main record
    return DB::table($this->table)
        ->where('id', $id)
        ->delete() > 0;
}
```

---

## Multilingual Support

The plugin supports multilingual contact information through the i18n table:

- **city** - City name in selected culture
- **region** - Region/Province in selected culture
- **contact_type** - Type description in selected culture
- **note** - Notes in selected culture

The repository methods accept a `culture` parameter:

```php
$contacts = $repo->getByActorId($actorId, 'af');  // Afrikaans
$contacts = $repo->getByActorId($actorId, 'en');  // English (default)
```

---

## Performance Considerations

### Indexes

The extended table has:
- Primary key index on `id`
- Unique index on `contact_information_id`
- Foreign key constraint for cascade delete

### Query Optimization

The repository uses efficient joins:

```php
DB::table($this->table . ' as ci')
    ->leftJoin($this->i18nTable . ' as cii', function ($join) use ($culture) {
        $join->on('ci.id', '=', 'cii.id')
             ->where('cii.culture', '=', $culture);
    })
    ->leftJoin($this->extendedTable . ' as cie', 'ci.id', '=', 'cie.contact_information_id')
```

---

## Error Handling

The repository methods return appropriate values for error conditions:

| Method | Success | Failure |
|--------|---------|---------|
| create() | int (new ID) | Exception |
| update() | true | false |
| delete() | true | false |
| getById() | object | null |
| getPrimaryContact() | object | null |

---

*Part of the AtoM AHG Framework*
