<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace AhgLabel\Models;

use Illuminate\Database\Eloquent\Model;

class LabelTemplate extends Model
{
    protected $table = 'label_template';

    public $timestamps = true;

    protected $guarded = [];

    protected $casts = [
        'columns' => 'integer',
        'rows' => 'integer',
        'label_width_mm' => 'float',
        'label_height_mm' => 'float',
        'margin_mm' => 'float',
        'gutter_mm' => 'float',
        'font_size_pt' => 'integer',
        'show_title' => 'boolean',
        'show_identifier' => 'boolean',
        'show_repository' => 'boolean',
        'show_barcode' => 'boolean',
        'show_qr' => 'boolean',
        'is_default' => 'boolean',
    ];

    public const PAGE_SIZES = ['A4', 'Letter'];
    public const BARCODE_SOURCES = ['identifier', 'accession', 'call_number', 'isbn'];
    public const QR_TARGETS = ['url', 'identifier'];

    /** The default template, or the first available, or null. */
    public static function resolveDefault(): ?self
    {
        return static::query()->where('is_default', 1)->first()
            ?? static::query()->orderBy('id')->first();
    }
}
