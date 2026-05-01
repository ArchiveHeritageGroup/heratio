# Heratio - Shell Execution Policy

**Version:** 1.0
**Date:** 2026-02-28
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## 1. Overview

Shell command execution is a high-risk operation. Improperly escaped arguments can lead to command injection, allowing attackers to execute arbitrary system commands.

Heratio uses `ShellCommandService` as the centralized, safe interface for building shell commands. All code that invokes `exec()`, `shell_exec()`, `system()`, or `passthru()` MUST escape external input.

---

## 2. Service API

### ShellCommandService (AtomFramework\Services\ShellCommandService)

| Method | Description |
|--------|-------------|
| `buildMysqldumpCommand(...)` | Build mysqldump command with `escapeshellarg()` on all credentials |
| `buildMysqlRestoreCommand(...)` | Build mysql restore (supports gzipped input) |
| `execInDir($dir, $cmd, &$output, &$code)` | Validate directory with `realpath()`, then exec |
| `isAllowedService($name)` | Check service name against allowlist |
| `buildSystemctlCommand($action, $service)` | Build systemctl command (action + service allowlist) |
| `escapePostScript($input)` | Strip PostScript injection characters `( ) \` |
| `validateCommand($command)` | Reject shell metacharacters in command names |
| `buildTarCommand(...)` | Build tar with escaped paths, excludes, includes |
| `buildZipCommand(...)` | Build zip with escaped paths |

---

## 3. Rules

### 3.1 Mandatory Escaping

Every external value passed to a shell command MUST use `escapeshellarg()`:

```php
// CORRECT
exec('ls ' . escapeshellarg($userPath));
exec('cd ' . escapeshellarg($dir) . ' && git pull origin main 2>&1');

// WRONG - command injection via $userPath
exec("ls {$userPath}");
exec("cd {$dir} && git pull origin main 2>&1");
```

### 3.2 Never Use addslashes() for Shell Contexts

`addslashes()` is for SQL/string contexts only. It does NOT protect against shell injection:

```php
// WRONG - addslashes does not escape shell metacharacters
$cmd = "mysqldump -p'" . addslashes($password) . "' " . addslashes($database);

// CORRECT - use ShellCommandService
$cmd = ShellCommandService::buildMysqldumpCommand($host, $port, $user, $password, $db, $outFile);
```

### 3.3 Service Name Allowlist

System service operations (restart, stop, status) MUST validate the service name:

```php
// CORRECT - validates against allowlist
if (ShellCommandService::isAllowedService($serviceName)) {
    $cmd = ShellCommandService::buildSystemctlCommand('restart', $serviceName);
    exec($cmd);
}

// WRONG - attacker could inject: "nginx; rm -rf /"
exec("systemctl restart {$serviceName}");
```

### 3.4 Directory Validation

Before `cd`-ing into a directory, validate with `realpath()`:

```php
// CORRECT - validates directory exists and resolves symlinks
ShellCommandService::execInDir($directory, 'git status', $output, $code);

// WRONG - $directory could contain "; malicious_command"
exec("cd {$directory} && git status");
```

---

## 4. Fixed Vulnerabilities (Issue #197)

### 4.1 BackupService - Database Credentials (CRITICAL)
- **File:** `atom-framework/src/Services/BackupService.php`
- **Problem:** `addslashes()` used for shell-context escaping of database host, port, user, password
- **Fix:** Replaced with `ShellCommandService::buildMysqldumpCommand()` and `buildMysqlRestoreCommand()`

### 4.2 ServiceManager - Unescaped Paths (CRITICAL)
- **File:** `atom-framework/packaging/wizard/lib/ServiceManager.php`
- **Problem:** `$atomPath` used directly in `exec()` without escaping; service name not validated
- **Fix:** Added `realpath()` validation, `escapeshellarg()`, and service name allowlist

### 4.3 PluginFetcher - Unescaped Paths (HIGH)
- **File:** `atom-framework/src/Extensions/PluginFetcher.php`
- **Problem:** `$sourcePath`, `$targetPath`, `$repoUrl`, `$tempPath` unescaped in `exec()` calls
- **Fix:** All paths wrapped in `escapeshellarg()`

### 4.4 ExtensionCommand - Unescaped Paths (HIGH)
- **File:** `atom-framework/src/Console/ExtensionCommand.php`
- **Problem:** `$ahgPluginsPath` and `$repoPath` unescaped in `exec()` calls
- **Fix:** All paths wrapped in `escapeshellarg()`

---

## 5. Known Issues in Locked Plugins

The following locked plugins contain shell execution without adequate escaping. These cannot be modified per project policy and require remediation in future releases.

### 5.1 ahgBackupPlugin
- **Risk:** Medium - backup/restore commands use paths that could be manipulated if settings are compromised
- **Location:** Plugin action classes
- **Mitigation:** Settings stored in DB, only admin-accessible

### 5.2 ahgSecurityClearancePlugin
- **Risk:** Low - limited shell usage
- **Mitigation:** Admin-only functionality

### 5.3 ahgPreservationPlugin (if locked)
- **Risk:** Medium - calls external tools (siegfried, clamdscan, tesseract)
- **Location:** Service classes
- **Mitigation:** Input paths are system-generated, not user-supplied

---

## 6. Audit Checklist

When reviewing code that uses `exec()`, `shell_exec()`, `system()`, or `passthru()`:

- [ ] All variables in the command string use `escapeshellarg()` or come from a trusted source (hardcoded constant)
- [ ] No use of `addslashes()` in shell context
- [ ] Directory paths validated with `realpath()` before use
- [ ] Service names validated against an allowlist
- [ ] Command output is not directly reflected to users without escaping
- [ ] Error output is logged, not displayed
- [ ] If building complex commands, use `ShellCommandService` helpers

### Quick Grep Audit

```bash
# Find all exec() calls
grep -rn "exec(" atom-framework/src/ --include="*.php" | grep -v "PDO\|pdo\|->exec"

# Find any remaining addslashes in shell contexts
grep -rn "addslashes" atom-framework/src/ --include="*.php"

# Find unescaped variable interpolation in exec calls
grep -rn 'exec("' atom-framework/src/ --include="*.php"
grep -rn "exec('" atom-framework/src/ --include="*.php" | grep -v escapeshellarg
```
