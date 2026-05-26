<?php

/**
 * Version - one logical OCFL version (v1, v2, ...).
 *
 * Per OCFL v1.1 §3.5 a version carries:
 *   - a state map (digest -> [logical paths])
 *   - the user who created it (name, optional address/URI)
 *   - a free-text message
 *   - an RFC 3339 created timestamp
 *
 * The OCFL inventory.json sorts state digests by key (deterministically),
 * and within each digest preserves the original order of logical paths.
 *
 * @copyright  Johan Pieterse / Plain Sailing Information Systems
 * @license    AGPL-3.0-or-later
 */

namespace AhgOcfl\Layout;

use DateTimeImmutable;
use DateTimeInterface;

final class Version
{
    /**
     * @param array<string, array<int, string>> $state digest => logical paths
     */
    public function __construct(
        public readonly string $created,           // RFC 3339 / ISO-8601 with timezone
        public readonly array $state,              // digest => [path, ...]
        public readonly string $message = '',
        public readonly ?string $userName = null,
        public readonly ?string $userAddress = null,
    ) {
    }

    public static function now(
        array $state,
        string $message = '',
        ?string $userName = null,
        ?string $userAddress = null,
    ): self {
        return new self(
            created:     (new DateTimeImmutable('now'))->format(DateTimeInterface::RFC3339),
            state:       $state,
            message:     $message,
            userName:    $userName,
            userAddress: $userAddress,
        );
    }

    /** Build the inventory representation of this version. */
    public function toInventoryArray(): array
    {
        $sortedState = $this->state;
        ksort($sortedState, SORT_STRING);

        $out = [
            'created' => $this->created,
            'message' => $this->message,
            'state'   => $sortedState,
        ];

        $user = [];
        if ($this->userName !== null && $this->userName !== '') {
            $user['name'] = $this->userName;
        }
        if ($this->userAddress !== null && $this->userAddress !== '') {
            $user['address'] = $this->userAddress;
        }
        if ($user !== []) {
            $out['user'] = $user;
        }

        return $out;
    }

    /** Reconstruct from an inventory.json fragment. */
    public static function fromInventoryArray(array $data): self
    {
        $state = $data['state'] ?? [];
        if (! is_array($state)) {
            $state = [];
        }
        return new self(
            created:     (string) ($data['created'] ?? ''),
            state:       $state,
            message:     (string) ($data['message'] ?? ''),
            userName:    isset($data['user']['name']) ? (string) $data['user']['name'] : null,
            userAddress: isset($data['user']['address']) ? (string) $data['user']['address'] : null,
        );
    }
}
