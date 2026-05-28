<?php

namespace AhgLibrary\Models;

use Illuminate\Database\Eloquent\Model;

class TradingPartner extends Model
{
    protected $table = 'library_trading_partner';

    protected $fillable = [
        'vendor_id', 'edi_partner_code', 'edi_type', 'message_profile',
        'endpoint_type', 'endpoint_config', 'outbound_directory', 'inbound_directory',
        'acknowledgement_required', 'test_mode',
        'last_inbound_at', 'last_outbound_at',
        'last_error_at', 'last_error_message',
        'is_active', 'notes',
    ];

    protected $casts = [
        'endpoint_config'         => 'array',
        'acknowledgement_required' => 'bool',
        'test_mode'               => 'bool',
        'is_active'               => 'bool',
        'last_inbound_at'         => 'datetime',
        'last_outbound_at'        => 'datetime',
        'last_error_at'           => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function vendor()
    {
        return $this->belongsTo(\App\Models\LibraryVendor::class, 'vendor_id');
    }

    public function illRequests()
    {
        return $this->hasMany(\AhgLibrary\Models\IllRequest::class, 'trading_partner_id');
    }

    // ── Accessors ────────────────────────────────────────────────────────

    public function getConfigSummaryAttribute(): string
    {
        $cfg = $this->endpoint_config ?? [];
        return match ($this->endpoint_type) {
            'SFTP'       => 'SFTP — ' . ($cfg['host'] ?? '—') . ':' . ($cfg['port'] ?? 22),
            'AS2'        => 'AS2 — ' . ($cfg['as2_url'] ?? '—'),
            'HTTP_HTTPS' => 'HTTP — ' . ($cfg['url'] ?? '—'),
            'EMAIL'      => 'EMAIL — ' . ($cfg['smtp_host'] ?? '—'),
            'MANUAL'     => 'Manual / batch',
            default      => $this->endpoint_type,
        };
    }

    public function getDisplayNameAttribute(): string
    {
        $vendor = $this->vendor;
        return $vendor
            ? "{$this->edi_partner_code} ({$vendor->name})"
            : $this->edi_partner_code;
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeByType($q, string $type)
    {
        return $q->where('edi_type', $type);
    }
}
