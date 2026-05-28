# PSIS Z39.50 / SRU Port

**Source:** Heratio `packages/ahg-z3950/` (v1.112+)  
**Target:** PSIS `plugins/ahgLibraryPlugin/` (Symfony 1.4 / Doctrine 1.2)  
**Issue:** atom-ahg-plugins#92

This directory holds ready-to-copy Symfony 1.4 artefacts for the Z39.50 client and SRU 2.0 server module in PSIS. Heratio's implementation is the canonical reference; this port mirrors it faithfully but adapts for Doctrine 1.x over PDO.

---

## What is here

```
psis-z3950-port/
├── README.md                        ← you are here
├── actions/
│   └── z3950Actions.class.php       ← Symfony 1.4 actions (client UI + SRU dispatcher)
├── lib/Service/
│   ├── Z3950Service.class.php       ← YAZ client, MARC parser, catalogue import
│   └── SruService.class.php         ← SRU 2.0 server (searchRetrieve + explain)
├── config/
│   └── module.yml                   ← Symfony 1.4 routing + security
└── sql/
    └── psis-z3950-schema.sql         ← DB migration for z3950_* tables
```

## Installation steps

### 1. Database migration

```bash
# PSIS operator shell
psql -U postgres -d qtatomo -f /path/to/psis-z3950-schema.sql
```

This creates:
- `z3950_targets` — remote target profiles
- `z3950_query_log` — query audit log
- `z3950_import_log` — import audit log

The pre-seeded targets (LoC, BL, WorldCat) are inserted but `active=FALSE` by default.

### 2. Copy module files

```bash
# PSIS tree — these are the destination paths
cp actions/z3950Actions.class.php \
   /usr/share/nginx/archive/plugins/ahgLibraryPlugin/modules/z3950/actions/actionsActions.class.php

cp lib/Service/Z3950Service.class.php \
   /usr/share/nginx/archive/plugins/ahgLibraryPlugin/lib/Service/Z3950Service.class.php

cp lib/Service/SruService.class.php \
   /usr/share/nginx/archive/plugins/ahgLibraryPlugin/lib/Service/SruService.class.php

cp config/module.yml \
   /usr/share/nginx/archive/plugins/ahgLibraryPlugin/modules/z3950/config/module.yml
```

Create the templates directory and copy the Blade-equivalent PHP templates:

```bash
mkdir -p /usr/share/nginx/archive/plugins/ahgLibraryPlugin/modules/z3950/templates
# Then copy templates from below (indexSuccess.php, searchSuccess.php, etc.)
```

### 3. Create templates

Create these files in `modules/z3950/templates/`:

**`indexSuccess.php`** — Dashboard:
```php
<?php use_helper('Javascript', 'I18N') ?>
<h2><?php echo __('Z39.50 / SRU') ?></h2>
<div class="z3950-stats">
  <div class="stat-row">Targets: <strong><?php echo $totalTargets ?></strong></div>
  <div class="stat-row">Searches: <strong><?php echo $totalSearches ?></strong></div>
  <div class="stat-row">Imports:  <strong><?php echo $totalImports ?></strong></div>
  <div class="stat-row yaz-badge">
    php-yaz: <?php echo $yazAvailable ? '<span class="ok">LOADED</span>' : '<span class="warn">NOT INSTALLED</span>' ?>
  </div>
</div>
<p><?php echo link_to('New Search',   'z3950/search') ?></p>
<p><?php echo link_to('Admin Panel', 'z3950/admin') ?></p>
<p><?php echo link_to('SRU Explain', 'z3950/sru?operation=explain') ?></p>
```

**`searchSuccess.php`** — Search form:
```php
<?php use_helper('Form', 'I18N') ?>
<h2><?php echo __('Search Remote Catalogue') ?></h2>
<form method="post" action="<?php echo url_for('z3950/searchExec') ?>">
  <label>Target: <select name="target_id" required>
    <?php foreach ($targets as $t): ?>
      <option value="<?php echo $t['id'] ?>"><?php echo $t['name'] ?> (<?php echo $t['host'] ?>)</option>
    <?php endforeach ?>
  </select></label>
  <br>
  <label>Query (CQL or bib-1):<br>
    <input type="text" name="query" size="60" required
           placeholder="title = africa AND author = smith">
  </label>
  <br>
  <label>Element set: <select name="element_set">
    <option value="F">Full (F)</option>
    <option value="B">Brief (B)</option>
  </select></label>
  <br>
  <label>Max records: <input type="number" name="max_records" value="100" min="1" max="1000"></label>
  <br><br>
  <input type="submit" value="<?php echo __('Search') ?>">
</form>
```

**`resultSuccess.php`** — Browse results:
```php
<?php use_helper('I18N') ?>
<h2><?php echo __('Search Results') ?></h2>
<p><?php echo count($records) ?> records in result set.</p>
<form method="post" action="<?php echo url_for('z3950/importBatch') ?>">
  <input type="hidden" name="result_set" value="<?php echo $resultSet ?>">
  <table>
    <thead><tr><th></th><th>001</th><th>245</th><th>100</th><th>260</th><th>020</th></tr></thead>
    <tbody>
    <?php foreach ($records as $idx => $raw): ?>
      <?php $p = $parsed[$idx] ?>
      <tr>
        <td><input type="checkbox" name="record_numbers"
                   value="<?php echo $idx ?>"
                   <?php echo $idx === 0 ? 'checked' : '' ?>></td>
        <td><?php echo $p['001']['a'] ?? '' ?></td>
        <td><?php echo $p['245']['a'] ?? '' ?> <?php echo $p['245']['b'] ?? '' ?></td>
        <td><?php echo $p['100']['a'] ?? '' ?></td>
        <td><?php echo $p['260']['a'] ?? '' ?> <?php echo $p['260']['b'] ?? '' ?> <?php echo $p['260']['c'] ?? '' ?></td>
        <td><?php echo $p['020']['a'] ?? '' ?></td>
        <td><?php echo link_to('Import', "z3950/import?resultSet={$resultSet}&recordNumber={$idx}") ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>
  <p>Selected: <input type="text" name="record_numbers_manual" placeholder="all or 0,1,2,..." size="20">
  <button type="submit">Import selected</button></p>
</form>
```

**`adminSuccess.php`** — Admin panel (targets + logs):
```php
<?php use_helper('I18N') ?>
<h2><?php echo __('Z39.50 Admin') ?></h2>

<h3><?php echo __('Remote Targets') ?></h3>
<p><?php echo link_to('+ Add Target', 'z3950/newTarget') ?></p>
<table>
  <thead><tr><th>ID</th><th>Name</th><th>Host</th><th>Port</th><th>DB</th><th>Active</th></tr></thead>
  <tbody>
  <?php foreach ($targets as $t): ?>
    <tr>
      <td><?php echo $t['id'] ?></td>
      <td><?php echo $t['name'] ?></td>
      <td><?php echo $t['host'] ?></td>
      <td><?php echo $t['port'] ?></td>
      <td><?php echo $t['database_name'] ?></td>
      <td><?php echo $t['active'] ? 'Yes' : 'No' ?></td>
      <td><?php echo link_to('Delete', "z3950/deleteTarget?id={$t['id']}", 'confirm=Delete?') ?></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>

<h3><?php echo __('Recent Queries') ?></h3>
<table>
  <thead><tr><th>When</th><th>Target</th><th>Query</th><th>Hits</th><th>ms</th><th>Error</th></tr></thead>
  <tbody>
  <?php foreach ($recentQueries as $q): ?>
    <tr>
      <td><?php echo $q['created_at'] ?></td>
      <td><?php echo $q['target_name'] ?></td>
      <td><?php echo $q['query'] ?></td>
      <td><?php echo $q['result_count'] ?></td>
      <td><?php echo $q['elapsed_ms'] ?></td>
      <td><?php echo $q['error'] ? "<span class='error'>{$q['error']}</span>" : '—' ?></td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
```

**`newTargetSuccess.php`** — Add target form:
```php
<?php use_helper('Form', 'I18N') ?>
<h2><?php echo __('Add Z39.50 Target') ?></h2>
<form method="post" action="<?php echo url_for('z3950/createTarget') ?>">
  <label>Name: <input name="name" required size="40"></label><br>
  <label>Host: <input name="host" required size="40" placeholder="lx2.loc.gov"></label><br>
  <label>Port: <input name="port" value="210" required size="6"></label><br>
  <label>Database: <input name="database" required size="30" placeholder="LCDB"></label><br>
  <label>Syntax: <select name="syntax">
    <option>USmarc</option><option>SUTRS</option><option>XML</option>
  </select></label><br>
  <label>Element set: <select name="element_set">
    <option>F</option><option>B</option>
  </select></label><br>
  <label>Charset: <input name="charset" value="UTF-8" size="20"></label><br>
  <label>Active: <input type="checkbox" name="active" checked></label><br><br>
  <input type="submit" value="<?php echo __('Save Target') ?>">
</form>
```

### 4. Enable the module

In `apps/atom/config/settings.yml` or via the PSIS plugin admin UI, enable `ahgLibraryPlugin` if not already active, then clear the Symfony cache:

```bash
cd /usr/share/nginx/archive
./symfony cc
```

The routes are defined in `module.yml` and will be picked up automatically.

---

## Prerequisite: php-yaz

`z3950Actions.class.php` checks `extension_loaded('yaz')` and returns a user-friendly error if the PECL extension is missing. It will NOT crash — it redirects to the search form with a flash message.

For best results, install php-yaz:

```bash
pecl install yaz
# or
apt-get install php-yaz
sudo systemctl restart php8.3-fpm
```

---

## SRU endpoint

Once installed, SRU 2.0 is live at:

```
https://psis.theahg.co.za/z3950/sru?operation=explain
https://psis.theahg.co.za/z3950/sru?operation=searchRetrieve&query=dc.title%3Dafrica&maximumRecords=20&recordSchema=marcxml
```

Read-only anonymous access (no auth). No additional firewall rules needed.

---

## Key differences from Heratio

| Feature | Heratio | PSIS |
|---|---|---|
| Framework | Laravel 12 | Symfony 1.4 |
| DB access | Eloquent/DB facade | Doctrine 1.x PDO |
| Sessions | Laravel session | sfUser attributes |
| DI | __construct via SP | lazy `new Classname()` |
| Logging | `Log::warning()` | `sfContext::getInstance()->getLogger()` |
| HTTP response | `response()->view()` | `renderText()` + `setLayout(false)` |
| Routes/AC | `AppServiceProvider` | `module.yml` |

All business logic (MARC parsing, CQL→PQF, bib-1 attribute map, SRU schema rendering) is identical between the two ports. Mirror any fix in both trees.
