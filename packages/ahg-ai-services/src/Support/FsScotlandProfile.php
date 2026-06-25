<?php

/**
 * @author    Johan Pieterse <johan@theahg.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgAiServices\Support;

/**
 * FamilySearch "Scotland Sample Project" indexing profile (FS-Scotland indexer).
 *
 * Encodes the Data Safe field set, the field-by-event-type matrix, and the
 * controlled vocabularies from the project breakdown doc. Jurisdiction-neutral
 * pattern: this is ONE market/collection profile; other FamilySearch projects
 * add their own profile class alongside it.
 *
 * Data is the source of truth (no behaviour here) so it round-trips to the
 * FamilySearch Data Safe export under the exact Index System Names.
 */
final class FsScotlandProfile
{
    public const EVENT_TYPES = ['Birth', 'Baptism', 'Marriage', 'Death', 'Burial'];

    /** Non-record image classifications (Image Type field). */
    public const IMAGE_TYPES = [
        'No Extractable Data Image',
        'Blank Image',
        'Duplicate Image',
        'Unreadable Image',
        'Other Image',
    ];

    /**
     * Data Safe columns in export order: systemName => human label.
     * (Index System Names from the project's Data Safe Specification.)
     */
    public const COLUMNS = [
        'FS_COLLECTION_ID'        => 'Collection ID',
        'FS_PPQ_ID'               => 'PPQ ID',
        'FS_RECORD_NBR'           => 'Record Number',
        'FS_VIS_STATUS'           => 'Vis Status',
        'FS_DIGITAL_FILM_NBR'     => 'DGS Number',
        'FS_LANGUAGE'             => 'Language Code',
        'FS_IMAGE_TYPE'           => 'Image Type',
        'EVENT_TYPE'              => 'Event Type',
        'EVENT_PLACE'             => 'Event Place',
        'FS_IMAGE_NBR'            => 'Image Number',
        'EVENT_DAY_ORIG'          => 'Event Day',
        'EVENT_MONTH_ORIG'        => 'Event Month',
        'EVENT_YEAR_ORIG'         => 'Event Year',
        'PR_NAME_GN_ORIG'         => 'Given Name',
        'PR_NAME_SURN_ORIG'       => 'Surname',
        'PR_SEX_CODE_ORIG'        => 'Sex',
        'PR_AGE_ORIG'             => 'Age',
        'PR_OCCUPATION_ORIG'      => 'Occupation',
        'PR_MARITAL_STATUS_ORIG'  => 'Marital Status',
        'PR_FTHR_NAME_GN_ORIG'    => 'Father Given Name',
        'PR_FTHR_NAME_SURN_ORIG'  => 'Father Surname',
        'PR_MTHR_NAME_GN_ORIG'    => 'Mother Given Name',
        'PR_MTHR_NAME_SURN_ORIG'  => 'Mother Surname',
        'SP_NAME_GN_ORIG'         => 'Spouse Given Name',
        'SP_NAME_SURN_ORIG'       => 'Spouse Surname',
        'SP_AGE_ORIG'             => 'Spouse Age',
        'SP_OCCUPATION_ORIG'      => 'Spouse Occupation',
        'SP_MARITAL_STATUS_ORIG'  => 'Spouse Marital Status',
        'SP_FTHR_NAME_GN_ORIG'    => 'Spouse Father Given Name',
        'SP_FTHR_NAME_SURN_ORIG'  => 'Spouse Father Surname',
        'SP_MTHR_NAME_GN_ORIG'    => 'Spouse Mother Given Name',
        'SP_MTHR_NAME_SURN_ORIG'  => 'Spouse Mother Surname',
        'PR_BIR_DAY_ORIG'         => 'Birth Day',
        'PR_BIR_MONTH_ORIG'       => 'Birth Month',
        'PR_BIR_YEAR_ORIG'        => 'Birth Year',
        'PR_DEA_DAY_ORIG'         => 'Death Day',
        'PR_DEA_MONTH_ORIG'       => 'Death Month',
        'PR_DEA_YEAR_ORIG'        => 'Death Year',
    ];

    /** Fields keyed for EVERY event type. */
    private const ALL_EVENTS = [
        'FS_COLLECTION_ID', 'FS_PPQ_ID', 'FS_RECORD_NBR', 'FS_VIS_STATUS',
        'FS_DIGITAL_FILM_NBR', 'FS_LANGUAGE', 'FS_IMAGE_TYPE', 'EVENT_TYPE',
        'EVENT_PLACE', 'FS_IMAGE_NBR', 'EVENT_DAY_ORIG', 'EVENT_MONTH_ORIG',
        'EVENT_YEAR_ORIG', 'PR_NAME_GN_ORIG', 'PR_NAME_SURN_ORIG',
        'PR_FTHR_NAME_GN_ORIG', 'PR_FTHR_NAME_SURN_ORIG',
        'PR_MTHR_NAME_GN_ORIG', 'PR_MTHR_NAME_SURN_ORIG',
    ];

    /** Extra fields keyed per event type (added to ALL_EVENTS). */
    private const EXTRA_BY_EVENT = [
        'Birth'    => ['PR_SEX_CODE_ORIG'],
        'Baptism'  => ['PR_SEX_CODE_ORIG', 'PR_BIR_DAY_ORIG', 'PR_BIR_MONTH_ORIG', 'PR_BIR_YEAR_ORIG'],
        'Death'    => ['PR_SEX_CODE_ORIG', 'PR_AGE_ORIG', 'PR_OCCUPATION_ORIG', 'PR_MARITAL_STATUS_ORIG', 'SP_NAME_GN_ORIG', 'SP_NAME_SURN_ORIG'],
        'Burial'   => ['PR_SEX_CODE_ORIG', 'PR_AGE_ORIG', 'PR_OCCUPATION_ORIG', 'PR_MARITAL_STATUS_ORIG', 'SP_NAME_GN_ORIG', 'SP_NAME_SURN_ORIG', 'PR_DEA_DAY_ORIG', 'PR_DEA_MONTH_ORIG', 'PR_DEA_YEAR_ORIG'],
        'Marriage' => ['PR_AGE_ORIG', 'PR_OCCUPATION_ORIG', 'PR_MARITAL_STATUS_ORIG', 'SP_NAME_GN_ORIG', 'SP_NAME_SURN_ORIG', 'SP_AGE_ORIG', 'SP_OCCUPATION_ORIG', 'SP_MARITAL_STATUS_ORIG', 'SP_FTHR_NAME_GN_ORIG', 'SP_FTHR_NAME_SURN_ORIG', 'SP_MTHR_NAME_GN_ORIG', 'SP_MTHR_NAME_SURN_ORIG'],
    ];

    /** 3-letter month abbreviations the project keys (index 1..12). */
    public const MONTHS = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
        7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec',
    ];

    /** Marital status single-letter codes. */
    public const MARITAL = ['single' => 'S', 'married' => 'M', 'widowed' => 'W', 'divorced' => 'D'];

    /**
     * System-name fields keyed for an event type, in Data Safe column order.
     *
     * @return list<string>
     */
    public static function fieldsFor(string $eventType): array
    {
        $extra = self::EXTRA_BY_EVENT[$eventType] ?? [];
        $set = array_flip(array_merge(self::ALL_EVENTS, $extra));

        return array_values(array_filter(
            array_keys(self::COLUMNS),
            static fn (string $sys) => isset($set[$sys])
        ));
    }

    public static function isEventType(string $v): bool
    {
        return in_array($v, self::EVENT_TYPES, true);
    }
}
