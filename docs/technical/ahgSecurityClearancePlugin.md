# ahgSecurityClearancePlugin - Technical Documentation

**Version:** 1.1.0  
**Category:** Security (REQUIRED - LOCKED)  
**Dependencies:** atom-framework

---

## Overview

Security classification system implementing multi-level access controls for sensitive archival materials. Integrates with AtoM's ACL system.

---

## Database Schema

### ERD Diagram

```
┌─────────────────────────────┐
│  security_classification    │
├─────────────────────────────┤
│ PK id INT                  │
│    name VARCHAR             │
│    code VARCHAR UNIQUE      │
│    level INT                │
│    description TEXT         │
│    color VARCHAR            │
│    icon VARCHAR             │
│    requires_justification   │
│    auto_expire_days INT     │
│    is_active TINYINT        │
│    sort_order INT           │
│    created_at TIMESTAMP     │
│    updated_at TIMESTAMP     │
└─────────────────────────────┘
              │
              │ 1:N
              ▼
┌──────────────────────────────────┐
│ object_security_classification   │
├──────────────────────────────────┤
│ PK id INT                       │
│ FK object_id INT                │
│    object_type ENUM             │
│ FK classification_id INT        │──────┐
│    assigned_by INT              │      │
│    assigned_at TIMESTAMP        │      │
│    justification TEXT           │      │
│    expires_at TIMESTAMP         │      │
│    review_date DATE             │      │
│    declassify_on DATE           │      │
│    is_inherited TINYINT         │      │
└──────────────────────────────────┘      │
                                          │
┌─────────────────────────────┐           │
│  user_security_clearance    │           │
├─────────────────────────────┤           │
│ PK id INT                  │           │
│ FK user_id INT             │           │
│ FK classification_id INT   │───────────┘
│    granted_by INT          │
│    granted_at TIMESTAMP    │
│    expires_at TIMESTAMP    │
│    justification TEXT      │
│    is_active TINYINT       │
│    created_at TIMESTAMP    │
│    updated_at TIMESTAMP    │
└─────────────────────────────┘

┌─────────────────────────────┐
│security_clearance_audit     │
├─────────────────────────────┤
│ PK id INT                  │
│ FK user_id INT             │
│ FK object_id INT           │
│    object_type VARCHAR      │
│    action VARCHAR           │
│    classification_from INT  │
│    classification_to INT    │
│    justification TEXT       │
│    ip_address VARCHAR       │
│    created_at TIMESTAMP     │
└─────────────────────────────┘
```

---

## Default Classifications

| Level | Code | Name | Color |
|-------|------|------|-------|
| 0 | PUBLIC | Public | #28a745 |
| 1 | INTERNAL | Internal Use | #17a2b8 |
| 2 | CONFIDENTIAL | Confidential | #ffc107 |
| 3 | RESTRICTED | Restricted | #fd7e14 |
| 4 | SECRET | Secret | #dc3545 |
| 5 | TOP_SECRET | Top Secret | #6f42c1 |

---

## Access Control Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                   SECURITY ACCESS CHECK                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│   User Request                                                  │
│        │                                                        │
│        ▼                                                        │
│   ┌───────────────────────────────────────────────────────┐    │
│   │  1. Get Object Classification                         │    │
│   │     SELECT classification_id FROM object_security_... │    │
│   └───────────────────────────────────────────────────────┘    │
│        │                                                        │
│        ▼                                                        │
│   ┌───────────────────────────────────────────────────────┐    │
│   │  2. Get User Clearance Level                          │    │
│   │     SELECT MAX(c.level) FROM user_security_clearance  │    │
│   │     WHERE user_id = ? AND is_active = 1               │    │
│   │     AND (expires_at IS NULL OR expires_at > NOW())    │    │
│   └───────────────────────────────────────────────────────┘    │
│        │                                                        │
│        ▼                                                        │
│   ┌───────────────────────────────────────────────────────┐    │
│   │  3. Compare Levels                                    │    │
│   │     user_level >= object_level ?                      │    │
│   └───────────────────────────────────────────────────────┘    │
│        │                                                        │
│   ┌────┴────┐                                                  │
│   │         │                                                  │
│   ▼         ▼                                                  │
│ ACCESS    ACCESS                                               │
│ GRANTED   DENIED                                               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Service Methods

### SecurityClearanceService

```php
namespace ahgSecurityClearancePlugin\Service;

class SecurityClearanceService
{
    // Classifications
    public function getClassifications(): Collection
    public function getClassification(int $id): ?array
    public function createClassification(array $data): int
    public function updateClassification(int $id, array $data): bool
    
    // User Clearance
    public function grantClearance(int $userId, int $classificationId, array $data): int
    public function revokeClearance(int $userId, int $classificationId): bool
    public function getUserClearanceLevel(int $userId): int
    public function getUserClearances(int $userId): Collection
    public function hasAccess(int $userId, int $objectId, string $objectType): bool
    
    // Object Classification
    public function classifyObject(int $objectId, string $type, int $classificationId, array $data): int
    public function declassifyObject(int $objectId, string $type): bool
    public function getObjectClassification(int $objectId, string $type): ?array
    public function bulkClassify(array $objectIds, string $type, int $classificationId): int
    
    // Inheritance
    public function propagateToChildren(int $parentId): int
    public function clearInheritedClassifications(int $objectId): int
    
    // Review
    public function getObjectsForReview(int $days = 30): Collection
    public function getExpiringClearances(int $days = 30): Collection
}
```

---

## ACL Integration

```php
// In QubitAcl - modified to check security clearance
public static function check($object, $action, $options = array())
{
    // Standard AtoM ACL check
    $result = parent::check($object, $action, $options);
    
    if ($result) {
        // Additional security clearance check
        $service = new SecurityClearanceService();
        $userId = sfContext::getInstance()->user->getAttribute('user_id');
        
        if (!$service->hasAccess($userId, $object->id, get_class($object))) {
            return false;
        }
    }
    
    return $result;
}
```

---

## Scheduled Tasks

### securityProcessTask

The scheduled task (`php symfony security:process`) handles automated security operations:

1. **Automatic Declassification** - Processes due declassifications
2. **Expired Clearances** - Marks expired clearances as inactive
3. **Expiry Warnings** - Sends email notifications 30 days before clearance expiry
4. **2FA Session Cleanup** - Removes expired two-factor authentication sessions
5. **Audit Log Retention** - Enforces retention policy on access logs

### Email Notifications

Expiry warning emails are sent using sfMail or PHP mail() fallback:

```php
protected function sendExpiryWarningEmail($clearance): void
{
    $subject = 'Security Clearance Expiry Warning';
    $body = sprintf(
        "Dear %s,\n\n"
        . "Your %s security clearance will expire on %s (%d days remaining).\n\n"
        . "Please contact your security administrator to request a renewal.\n\n"
        . "Regards,\nSecurity Administration",
        $clearance->username,
        $clearance->clearance_name,
        $clearance->expiry_date,
        $clearance->days_remaining
    );

    // Try Symfony mailer first if available
    if (class_exists('sfMail')) {
        $mail = new sfMail();
        $mail->setFrom($fromEmail, $siteName);
        $mail->addAddress($clearance->email);
        $mail->setSubject($subject);
        $mail->setBody($body);
        $mail->send();
    } else {
        // Fallback to PHP mail
        mail($clearance->email, $subject, $body, implode("\r\n", $headers));
    }
}
```

### Cron Configuration

```bash
# Run daily at 1:00 AM
0 1 * * * /usr/bin/php /var/www/atom/symfony security:process
```

---

## Compliance Reporting

### PDF Export

Compliance reports support PDF export using TCPDF with HTML fallback:

```php
protected function exportPdf($filename)
{
    $tcpdfPath = sfConfig::get('sf_root_dir') . '/vendor/tecnickcom/tcpdf/tcpdf.php';

    if (file_exists($tcpdfPath)) {
        require_once $tcpdfPath;
        return $this->exportPdfWithTcpdf($filename);
    }

    return $this->exportPdfAsHtml($filename);
}
```

**PDF Features:**
- Security classification summaries
- User clearance listings
- Access audit reports
- Compliance status dashboards

---

## Changelog

### v1.1.0 (February 2026)
- Fixed scheduled task email sending implementation
- Replaced broken job queue code with direct email functionality
- Added sfMail integration with PHP mail() fallback
- Added PDF export with TCPDF and HTML fallback
- Improved error handling and logging

### v1.0.0 (Initial)
- Security classification system
- User clearance management
- Object classification
- ACL integration
- Audit logging

---

*Part of the AtoM AHG Framework*
