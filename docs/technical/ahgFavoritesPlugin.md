# ahgFavoritesPlugin - Technical Documentation

**Version:** 1.0.0  
**Category:** User Engagement  
**Dependencies:** atom-framework

---

## Overview

User favorites/bookmarks system allowing authenticated users to save archival records for quick access.

---

## Database Schema

### ERD Diagram
```
┌─────────────────────────────────────────┐
│              favorites                  │
├─────────────────────────────────────────┤
│ PK id INT AUTO_INCREMENT               │
│ FK user_id INT                         │
│ FK archival_description_id INT         │
│    archival_description VARCHAR(1024)  │
│    slug VARCHAR(1024)                  │
│    created_at DATETIME                 │
├─────────────────────────────────────────┤
│ UNIQUE (user_id, archival_description_id)│
└─────────────────────────────────────────┘
         │
         │ user_id
         ▼
┌─────────────────────────────────────────┐
│              user                       │
├─────────────────────────────────────────┤
│ PK id INT                              │
│    username VARCHAR                     │
│    email VARCHAR                        │
└─────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_fa11ab57.png)
```

### Table Definition
```sql
CREATE TABLE IF NOT EXISTS `favorites` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT DEFAULT NULL,
  `archival_description_id` INT DEFAULT NULL,
  `archival_description` VARCHAR(1024) DEFAULT NULL,
  `slug` VARCHAR(1024) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user` (`user_id`),
  INDEX `idx_description` (`archival_description_id`),
  UNIQUE KEY `unique_user_item` (`user_id`, `archival_description_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Architecture

### File Structure
```
ahgFavoritesPlugin/
├── config/
│   └── ahgFavoritesPluginConfiguration.class.php
├── data/
│   └── install.sql
├── extension.json
├── lib/
│   ├── Repositories/
│   │   └── FavoritesRepository.php
│   └── Services/
│       └── FavoritesService.php
└── modules/
    └── ahgFavorites/
        ├── actions/
        │   ├── addAction.class.php
        │   ├── browseAction.class.php
        │   └── removeAction.class.php
        └── templates/
            └── browseSuccess.php
```

### Component Diagram
```
┌─────────────────────────────────────────────────────────────────┐
│                      ahgFavoritesPlugin                         │
├─────────────────────────────────────────────────────────────────┤
│  ┌─────────────┐    ┌─────────────┐    ┌─────────────┐         │
│  │   Actions   │───▶│   Service   │───▶│ Repository  │         │
│  │             │    │             │    │             │         │
│  │ • add       │    │ FavoritesS  │    │ FavoritesR  │         │
│  │ • remove    │    │             │    │             │         │
│  │ • browse    │    └──────┬──────┘    └──────┬──────┘         │
│  └─────────────┘           │                  │                │
│                            │                  │                │
│                            ▼                  ▼                │
│                    ┌──────────────────────────────────┐        │
│                    │     Laravel Query Builder        │        │
│                    │     (Illuminate\Database)        │        │
│                    └──────────────────────────────────┘        │
└─────────────────────────────────────────────────────────────────┘
![wireframe](./images/wireframes/wireframe_545da167.png)
```

---

## Routes

| Route | Method | Action | Description |
|-------|--------|--------|-------------|
| `/favorites` | GET | browse | List user's favorites |
| `/favorites/add/:slug` | GET | add | Add item to favorites |
| `/favorites/remove/:id` | GET | remove | Remove from favorites |

---

## Repository Methods
```php
class FavoritesRepository
{
    public function getByUserId(int $userId): array
    public function exists(int $userId, int $objectId): bool
    public function getByUserAndObject(int $userId, int $objectId): ?object
    public function add(array $data): int
    public function remove(int $id): bool
    public function countByUser(int $userId): int
}
```

---

## Service Methods
```php
class FavoritesService
{
    public function getUserFavorites(int $userId): array
    public function addToFavorites(int $userId, int $objectId, ?string $title, ?string $slug): array
    public function removeFromFavorites(int $userId, int $favoriteId): array
    public function isInFavorites(int $userId, int $objectId): bool
}
```

---

## Integration Points

### Template Button (All GLAM/DAM Templates)
```php
<?php if (class_exists('ahgFavoritesPluginConfiguration') && $userId): ?>
  <?php if ($favoriteId): ?>
    <a href="<?php echo url_for(['module' => 'ahgFavorites', 'action' => 'remove', 'id' => $favoriteId]); ?>" 
       class="btn btn-xs btn-outline-danger" title="<?php echo __('Remove from Favorites'); ?>">
      <i class="fas fa-heart-broken"></i>
    </a>
  <?php else: ?>
    <a href="<?php echo url_for(['module' => 'ahgFavorites', 'action' => 'add', 'slug' => $resource->slug]); ?>" 
       class="btn btn-xs btn-outline-danger" title="<?php echo __('Add to Favorites'); ?>">
      <i class="fas fa-heart"></i>
    </a>
  <?php endif; ?>
<?php endif; ?>
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-01-13 | Initial release with Laravel Query Builder |

---

*Part of the AtoM AHG Framework*
