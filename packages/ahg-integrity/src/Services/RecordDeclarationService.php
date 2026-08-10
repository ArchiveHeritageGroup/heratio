<?php

namespace AhgIntegrity\Services;

/**
 * Backward-compatibility alias. This service was a byte-identical copy of
 * AhgRecordsManage\Services\RecordDeclarationService. The implementation now
 * lives once in ahg-records-manage; this subclass keeps every consumer of
 * AhgIntegrity\Services\RecordDeclarationService working unchanged.
 */
class RecordDeclarationService extends \AhgRecordsManage\Services\RecordDeclarationService
{
}
