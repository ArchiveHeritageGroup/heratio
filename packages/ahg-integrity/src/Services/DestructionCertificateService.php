<?php

namespace AhgIntegrity\Services;

/**
 * Backward-compatibility alias. This service was a byte-identical copy of
 * AhgRecordsManage\Services\DestructionCertificateService. The
 * implementation now lives once in ahg-records-manage; this subclass keeps
 * every consumer of AhgIntegrity\Services\DestructionCertificateService
 * working unchanged.
 */
class DestructionCertificateService extends \AhgRecordsManage\Services\DestructionCertificateService
{
}
