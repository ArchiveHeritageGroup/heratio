<?php

/**
 * EmbeddedMetadataExtracted - fired when MetadataExtractionService has finished
 * persisting embedded EXIF / IPTC / XMP / exiftool / ffprobe metadata to the
 * three sidecar tables (digital_object_metadata, dam_iptc_metadata,
 * media_metadata) for a digital_object.
 *
 * Heratio Issue #751. Listeners are free to read those sidecar rows and act
 * on them - the canonical listener is ahg-privacy's ScanEmbeddedMetadataForPii
 * which walks the rows for GPS coordinates, by-line names, and creator
 * contact details that flow into Heratio without ever passing through the
 * free-text PII scanner.
 *
 * Copyright (C) 2026 Johan Pieterse
 * Plain Sailing Information Systems
 * Email: johan@plainsailingisystems.co.za
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

declare(strict_types=1);

namespace AhgMetadataExtraction\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EmbeddedMetadataExtracted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly int $digitalObjectId)
    {
    }
}
