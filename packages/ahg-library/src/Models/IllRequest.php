<?php

namespace AhgLibrary\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class IllRequest extends Model
{
    protected $table = 'library_ill_request';

    protected $fillable = [
        // EDI / extended fields (Phase 2.5)
        'request_type', 'borrowing_protocol', 'material_type',
        'responder_library_id', 'responder_note', 'citation', 'lender_string',
        'edi_message_id', 'needed_by_date', 'shipping_method',
        'max_renewals', 'trading_partner_id',
        'closed_at', 'closed_reason',
        // Phase-1 fields (keep in fillable for compat)
        'ill_number', 'requester_library_id', 'patron_id',
        'type', 'title', 'author', 'isbn', 'issn',
        'volume', 'issue', 'pages', 'edition', 'publication_year',
        'library_name', 'library_symbol', 'requester_note',
        'status', 'due_date', 'renewal_count',
        'cost_amount', 'cost_currency', 'tracking_number',
        'notes', 'opac_suppress', 'staff_note',
    ];

    protected $casts = [
        'cost_amount'    => 'decimal:2',
        'needed_by_date' => 'date',
        'due_date'      => 'date',
        'closed_at'     => 'datetime',
        'opac_suppress' => 'bool',
        'renewal_count' => 'integer',
        'max_renewals'  => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function requesterLibrary()
    {
        return $this->belongsTo(\App\Models\LibraryVendor::class, 'requester_library_id');
    }

    public function responderLibrary()
    {
        return $this->belongsTo(\App\Models\LibraryVendor::class, 'responder_library_id');
    }

    public function tradingPartner()
    {
        return $this->belongsTo(\AhgLibrary\Models\TradingPartner::class, 'trading_partner_id');
    }

    public function patron()
    {
        return $this->belongsTo(\App\Models\LibraryPatron::class, 'patron_id');
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeActive($q): Builder
    {
        $active = ['pending', 'requested', 'shipped', 'received'];
        return $q->whereIn('status', $active);
    }

    public function scopeClosed($q): Builder
    {
        return $q->whereIn('status', ['returned', 'lost', 'cancelled', 'unfulfilled']);
    }

    public function scopeOverdue($q): Builder
    {
        return $q->whereNotNull('due_date')
            ->where('due_date', '<', Carbon::today()->toDateString())
            ->whereNotIn('status', ['returned', 'lost', 'cancelled', 'unfulfilled']);
    }

    // ── Accessors ────────────────────────────────────────────────────────

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->due_date) return false;
        if (in_array($this->status, ['returned', 'lost', 'cancelled', 'unfulfilled'])) {
            return false;
        }
        return Carbon::parse($this->due_date)->isPast();
    }

    public function getDaysUntilNeededAttribute(): ?int
    {
        if (!$this->needed_by_date) return null;
        return (int) Carbon::today()->diffInDays(Carbon::parse($this->needed_by_date), false);
    }

    public function getCanRenewAttribute(): bool
    {
        return $this->status === 'received'
            && (int) $this->renewal_count < (int) ($this->max_renewals ?? 2);
    }

    public function getStatusLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status ?? ''));
    }

    // ── Events ───────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::updating(function (IllRequest $req) {
            $closing = ['cancelled', 'declined', 'lost', 'returned', 'unfulfilled'];
            foreach ($closing as $cs) {
                if ($req->status === $cs && $req->getOriginal('status') !== $cs) {
                    $req->closed_at = Carbon::now();
                    break;
                }
            }
        });
    }
}
