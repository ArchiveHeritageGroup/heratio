<?php

/**
 * EmbargoNotificationService - Service for Heratio
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plansailingisystems
 *
 * This file is part of Heratio.
 *
 * Heratio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Heratio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Heratio. If not, see <https://www.gnu.org/licenses/>.
 */



namespace AhgExtendedRights\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * EmbargoNotificationService - Handles email notifications for embargo events.
 *
 * Features:
 * - Send expiry warning notifications (30/7/1 days before)
 * - Send lifted notifications when embargo is released
 * - Send access granted notifications when exceptions are granted
 * - Log all notifications for audit purposes
 * - Get notification recipients from embargo, donor, or repository contacts
 *
 * Migrated from AtoM ahgExtendedRightsPlugin EmbargoNotificationService.
 */
class EmbargoNotificationService
{
    protected string $culture;

    public function __construct()
    {
        $this->culture = app()->getLocale() ?: 'en';
    }

    /**
     * Send expiry warning notification.
     *
     * @param object $embargo       The embargo record
     * @param int    $daysRemaining Days until expiry
     *
     * @return bool Success status
     */
    public function sendExpiryNotification(object $embargo, int $daysRemaining): bool
    {
        $recipients = $this->getNotificationRecipients($embargo);

        if (empty($recipients)) {
            $this->logNotification($embargo->id, 'expiry_warning', [], $daysRemaining, false, 'No recipients configured');

            return false;
        }

        $objectInfo = $this->getObjectInfo($embargo->object_id);
        $embargoTypeLabel = $this->getEmbargoTypeLabel($embargo->embargo_type);

        $subject = "Embargo Expiry Warning: {$daysRemaining} days remaining";

        $body = $this->buildExpiryNotificationBody([
            'object_title' => $objectInfo['title'] ?? 'Unknown',
            'object_slug' => $objectInfo['slug'] ?? '',
            'embargo_type' => $embargoTypeLabel,
            'end_date' => $embargo->end_date,
            'days_remaining' => $daysRemaining,
            'reason' => $embargo->reason ?? 'Not specified',
        ]);

        $sent = $this->sendEmail($recipients, $subject, $body);

        $this->logNotification(
            $embargo->id,
            'expiry_warning',
            $recipients,
            $daysRemaining,
            $sent,
            $sent ? null : 'Email delivery failed'
        );

        // Update notification_sent flag to prevent duplicate notifications
        if ($sent && Schema::hasTable('rights_embargo')) {
            DB::table('rights_embargo')
                ->where('id', $embargo->id)
                ->update(['notification_sent' => true]);
        }

        return $sent;
    }

    /**
     * Send notification when embargo is lifted.
     *
     * @param object      $embargo The embargo record
     * @param string|null $reason  Reason for lifting
     *
     * @return bool Success status
     */
    public function sendLiftedNotification(object $embargo, ?string $reason = null): bool
    {
        $recipients = $this->getNotificationRecipients($embargo);

        if (empty($recipients)) {
            $this->logNotification($embargo->id, 'lifted', [], null, false, 'No recipients configured');

            return false;
        }

        $objectInfo = $this->getObjectInfo($embargo->object_id);
        $embargoTypeLabel = $this->getEmbargoTypeLabel($embargo->embargo_type);

        $subject = 'Embargo Lifted: ' . ($objectInfo['title'] ?? 'Record');

        $body = $this->buildLiftedNotificationBody([
            'object_title' => $objectInfo['title'] ?? 'Unknown',
            'object_slug' => $objectInfo['slug'] ?? '',
            'embargo_type' => $embargoTypeLabel,
            'lift_reason' => $reason ?? 'Auto-released after expiry',
            'lifted_at' => date('Y-m-d H:i:s'),
        ]);

        $sent = $this->sendEmail($recipients, $subject, $body);

        $this->logNotification(
            $embargo->id,
            'lifted',
            $recipients,
            null,
            $sent,
            $sent ? null : 'Email delivery failed'
        );

        return $sent;
    }

    /**
     * Send access granted notification.
     *
     * @param object $embargo   The embargo record
     * @param object $exception The exception granted
     * @param object $user      The user granted access
     *
     * @return bool Success status
     */
    public function sendAccessGrantedNotification(object $embargo, object $exception, object $user): bool
    {
        $userEmail = $this->getUserEmail($user);

        if (!$userEmail) {
            $this->logNotification($embargo->id, 'access_granted', [], null, false, 'No user email found');

            return false;
        }

        $objectInfo = $this->getObjectInfo($embargo->object_id);

        $subject = 'Access Granted: ' . ($objectInfo['title'] ?? 'Record');

        $body = $this->buildAccessGrantedBody([
            'object_title' => $objectInfo['title'] ?? 'Unknown',
            'object_slug' => $objectInfo['slug'] ?? '',
            'valid_from' => $exception->valid_from ?? 'Immediately',
            'valid_until' => $exception->valid_until ?? 'Indefinitely',
        ]);

        $sent = $this->sendEmail([$userEmail], $subject, $body);

        $this->logNotification(
            $embargo->id,
            'access_granted',
            [$userEmail],
            null,
            $sent,
            $sent ? null : 'Email delivery failed'
        );

        return $sent;
    }

    /**
     * Get notification recipients for an embargo.
     *
     * Priority:
     * 1. Embargo's notify_emails field (comma-separated)
     * 2. Donor contact email (via relation table)
     * 3. Repository contact email (via information_object.repository_id)
     * 4. Creator user email (embargo.created_by)
     *
     * @param object $embargo The embargo record
     *
     * @return array Unique, validated email addresses
     */
    public function getNotificationRecipients(object $embargo): array
    {
        $recipients = [];

        // 1. Check embargo's notify_emails field
        if (!empty($embargo->notify_emails)) {
            $embargoEmails = array_map('trim', explode(',', $embargo->notify_emails));
            foreach ($embargoEmails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $recipients[] = $email;
                }
            }
        }

        // 2. Check donor contact if object has a donor
        $donorEmail = $this->getDonorContactEmail($embargo->object_id);
        if ($donorEmail) {
            $recipients[] = $donorEmail;
        }

        // 3. Check repository contact
        $repoEmail = $this->getRepositoryContactEmail($embargo->object_id);
        if ($repoEmail) {
            $recipients[] = $repoEmail;
        }

        // 4. Check created_by user email
        if (!empty($embargo->created_by)) {
            $creatorEmail = $this->getUserEmailById((int) $embargo->created_by);
            if ($creatorEmail) {
                $recipients[] = $creatorEmail;
            }
        }

        return array_values(array_unique(array_filter($recipients)));
    }

    /**
     * Get object information for notifications.
     *
     * @param int $objectId Information object ID
     *
     * @return array{title: string|null, slug: string|null}
     */
    public function getObjectInfo(int $objectId): array
    {
        $result = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as ioi', function ($join) {
                $join->on('io.id', '=', 'ioi.id')
                    ->where('ioi.culture', '=', $this->culture);
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', $objectId)
            ->select(['ioi.title', 'slug.slug'])
            ->first();

        return [
            'title' => $result->title ?? null,
            'slug' => $result->slug ?? null,
        ];
    }

    /**
     * Get donor contact email for an object.
     *
     * Uses the relation table to find donor associations,
     * then retrieves the donor's contact email.
     *
     * @param int $objectId Information object ID
     *
     * @return string|null
     */
    public function getDonorContactEmail(int $objectId): ?string
    {
        // Donor linked via relation table (subject_id = donor, object_id = accession/IO)
        // or via object_rights_holder if that table exists
        if (Schema::hasTable('object_rights_holder')) {
            $email = DB::table('object_rights_holder as orh')
                ->join('donor as d', 'd.id', '=', 'orh.donor_id')
                ->where('orh.object_id', $objectId)
                ->value('d.email');

            if ($email) {
                return $email;
            }
        }

        // Fallback: try contact_information for the donor actor via relation table
        $donorContact = DB::table('relation as r')
            ->join('contact_information as ci', 'ci.actor_id', '=', 'r.subject_id')
            ->where('r.object_id', $objectId)
            ->where('r.type_id', DB::raw('(SELECT id FROM term WHERE taxonomy_id = 3 AND id IN (SELECT id FROM term_i18n WHERE name = \'donor\' AND culture = \'en\') LIMIT 1)'))
            ->whereNotNull('ci.email')
            ->value('ci.email');

        return $donorContact ?: null;
    }

    /**
     * Get repository contact email for an object.
     *
     * Follows information_object.repository_id -> contact_information.
     *
     * @param int $objectId Information object ID
     *
     * @return string|null
     */
    public function getRepositoryContactEmail(int $objectId): ?string
    {
        return DB::table('information_object as io')
            ->join('repository as r', 'r.id', '=', 'io.repository_id')
            ->leftJoin('contact_information as ci', 'ci.actor_id', '=', 'r.id')
            ->where('io.id', $objectId)
            ->whereNotNull('ci.email')
            ->value('ci.email');
    }

    /**
     * Get user email by ID.
     *
     * @param int $userId User ID
     *
     * @return string|null
     */
    public function getUserEmailById(int $userId): ?string
    {
        return DB::table('user')
            ->where('id', $userId)
            ->value('email');
    }

    /**
     * Get human-readable embargo type label from taxonomy.
     *
     * @param string $type Embargo type identifier
     *
     * @return string Human-readable label
     */
    public function getEmbargoTypeLabel(string $type): string
    {
        // Look up the term name from the taxonomy tables
        $label = DB::table('term')
            ->join('term_i18n as ti', function ($join) {
                $join->on('term.id', '=', 'ti.id')
                    ->where('ti.culture', '=', $this->culture);
            })
            ->join('taxonomy', 'taxonomy.id', '=', 'term.taxonomy_id')
            ->join('taxonomy_i18n as taxi', function ($join) {
                $join->on('taxonomy.id', '=', 'taxi.id')
                    ->where('taxi.culture', '=', $this->culture);
            })
            ->where('taxi.name', 'Embargo Type')
            ->where(function ($query) use ($type) {
                $query->where('ti.name', $type)
                    ->orWhere('term.id', $type);
            })
            ->value('ti.name');

        return $label ?? 'Access Restriction';
    }

    /**
     * Send email to recipients.
     *
     * Uses Laravel's Mail facade with raw text content.
     * Falls back to PHP mail() if Mail facade is not configured.
     *
     * @param array  $recipients Email addresses
     * @param string $subject    Email subject
     * @param string $body       Email body (plain text)
     *
     * @return bool Success status
     */
    public function sendEmail(array $recipients, string $subject, string $body): bool
    {
        if (empty($recipients)) {
            return false;
        }

        $fromAddress = config('mail.from.address', 'noreply@heratio.local');
        $fromName = config('mail.from.name', 'Heratio Archive System');

        try {
            // Use Laravel Mail facade if configured
            if (config('mail.default') && config('mail.default') !== 'log') {
                Mail::raw($body, function ($message) use ($recipients, $subject, $fromAddress, $fromName) {
                    $message->to($recipients)
                        ->subject($subject)
                        ->from($fromAddress, $fromName);
                });

                return true;
            }

            // Fallback to PHP mail()
            $success = true;
            $headers = "From: {$fromName} <{$fromAddress}>\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            foreach ($recipients as $recipient) {
                if (!mail($recipient, $subject, $body, $headers)) {
                    Log::warning('EmbargoNotificationService: mail() failed', [
                        'recipient' => $recipient,
                        'subject' => $subject,
                    ]);
                    $success = false;
                }
            }

            return $success;
        } catch (\Exception $e) {
            Log::error('EmbargoNotificationService: Email send failed', [
                'error' => $e->getMessage(),
                'recipients' => $recipients,
                'subject' => $subject,
            ]);

            return false;
        }
    }

    /**
     * Log notification for audit purposes.
     *
     * Attempts to log to embargo_notification_log table first,
     * falls back to embargo_audit table if notification_log does not exist.
     *
     * @param int         $embargoId        Embargo ID
     * @param string      $notificationType Type of notification (expiry_warning, lifted, access_granted)
     * @param array       $recipients       Recipients list
     * @param int|null    $days             Days before expiry (for warnings)
     * @param bool        $sent             Whether email was sent successfully
     * @param string|null $error            Error message if failed
     */
    public function logNotification(
        int $embargoId,
        string $notificationType,
        array $recipients,
        ?int $days,
        bool $sent,
        ?string $error = null
    ): void {
        try {
            // Try embargo_notification_log table first
            if (Schema::hasTable('embargo_notification_log')) {
                DB::table('embargo_notification_log')->insert([
                    'embargo_id' => $embargoId,
                    'notification_type' => $notificationType,
                    'recipients' => json_encode($recipients),
                    'days_before' => $days,
                    'sent' => $sent,
                    'error' => $error,
                    'sent_at' => date('Y-m-d H:i:s'),
                ]);

                return;
            }

            // Fallback: log to embargo_audit table
            if (Schema::hasTable('embargo_audit')) {
                DB::table('embargo_audit')->insert([
                    'embargo_id' => $embargoId,
                    'action' => 'notification_' . $notificationType,
                    'details' => json_encode([
                        'recipients' => $recipients,
                        'days_before' => $days,
                        'sent' => $sent,
                        'error' => $error,
                    ]),
                    'performed_at' => date('Y-m-d H:i:s'),
                ]);

                return;
            }

            // Last resort: Laravel log
            Log::info('EmbargoNotification', [
                'embargo_id' => $embargoId,
                'type' => $notificationType,
                'recipients' => $recipients,
                'days_before' => $days,
                'sent' => $sent,
                'error' => $error,
            ]);
        } catch (\Exception $e) {
            Log::error('EmbargoNotificationService: Failed to log notification', [
                'embargo_id' => $embargoId,
                'type' => $notificationType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get user email from user object.
     *
     * @param object $user User object (may have email property or just id)
     *
     * @return string|null
     */
    protected function getUserEmail(object $user): ?string
    {
        if (isset($user->email)) {
            return $user->email;
        }

        if (isset($user->id)) {
            return $this->getUserEmailById((int) $user->id);
        }

        return null;
    }

    /**
     * Build expiry notification email body.
     *
     * @param array $data Template data
     *
     * @return string Plain text email body
     */
    protected function buildExpiryNotificationBody(array $data): string
    {
        $baseUrl = $this->getBaseUrl();

        return <<<BODY
Embargo Expiry Warning

The following embargo will expire in {$data['days_remaining']} days:

Record: {$data['object_title']}
Embargo Type: {$data['embargo_type']}
End Date: {$data['end_date']}
Reason: {$data['reason']}

Action Required:
- Review the embargo and extend if necessary
- Or allow it to auto-release on the end date

View Record: {$baseUrl}/{$data['object_slug']}
Manage Embargoes: {$baseUrl}/extendedRights/embargoes

This is an automated notification from the Archive system.
BODY;
    }

    /**
     * Build lifted notification email body.
     *
     * @param array $data Template data
     *
     * @return string Plain text email body
     */
    protected function buildLiftedNotificationBody(array $data): string
    {
        $baseUrl = $this->getBaseUrl();

        return <<<BODY
Embargo Lifted

The following embargo has been lifted:

Record: {$data['object_title']}
Previous Embargo Type: {$data['embargo_type']}
Lifted At: {$data['lifted_at']}
Reason: {$data['lift_reason']}

The record is now accessible according to standard access rules.

View Record: {$baseUrl}/{$data['object_slug']}

This is an automated notification from the Archive system.
BODY;
    }

    /**
     * Build access granted notification email body.
     *
     * @param array $data Template data
     *
     * @return string Plain text email body
     */
    protected function buildAccessGrantedBody(array $data): string
    {
        $baseUrl = $this->getBaseUrl();

        return <<<BODY
Access Granted

You have been granted access to an embargoed record:

Record: {$data['object_title']}
Access Valid From: {$data['valid_from']}
Access Valid Until: {$data['valid_until']}

You can now view this record by visiting:
{$baseUrl}/{$data['object_slug']}

This is an automated notification from the Archive system.
BODY;
    }

    /**
     * Get base URL for links in emails.
     *
     * @return string Application base URL
     */
    protected function getBaseUrl(): string
    {
        return rtrim(config('app.url', ''), '/');
    }
}
