<?php

namespace AhgIntegrity\Services;

/**
 * Backward-compatibility alias. This service was a byte-identical copy of
 * AhgRecordsManage\Services\RetentionService (same integrity_* tables, same
 * logic). The implementation now lives once in ahg-records-manage; this
 * subclass keeps every consumer of AhgIntegrity\Services\RetentionService
 * (IntegrityController, ahg-core's IntegrityRetentionCommand) working
 * unchanged.
 */
class RetentionService extends \AhgRecordsManage\Services\RetentionService
{
}
