<?php

namespace AhgIntegrity\Services;

/**
 * Backward-compatibility alias. This service was a byte-identical copy of
 * AhgRecordsManage\Services\VitalRecordService. The implementation now lives
 * once in ahg-records-manage; this subclass keeps every consumer of
 * AhgIntegrity\Services\VitalRecordService working unchanged.
 */
class VitalRecordService extends \AhgRecordsManage\Services\VitalRecordService
{
}
