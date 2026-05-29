<?php

/**
 * @author    Johan Pieterse <johan@plainsailingisystems.co.za>
 * @copyright Plain Sailing Information Systems
 * @license   AGPL-3.0-or-later
 */

namespace AhgLibrary\Policies;

use AhgCore\Services\AclService;
use Illuminate\Database\Eloquent\Model;

/**
 * Role-based authorisation for the library acquisitions resources
 * (heratio#1100). Delegates to the same action gate the rest of Heratio uses
 * (AclService::hasPermission, which grants administrators everything), so the
 * JSON:API, web ACL middleware and Gate/@can all share one source of truth.
 *
 * $user is the acting account (AhgCore\Models\User); only its id is needed.
 */
abstract class LibraryAclPolicy
{
    protected function allows(?object $user, string $action): bool
    {
        $userId = $user?->id !== null ? (int) $user->id : null;

        return AclService::hasPermission($userId, $action);
    }

    public function viewAny(?object $user): bool
    {
        return $this->allows($user, 'read');
    }

    public function view(?object $user, Model $model): bool
    {
        return $this->allows($user, 'read');
    }

    public function create(?object $user): bool
    {
        return $this->allows($user, 'create');
    }

    public function update(?object $user, Model $model): bool
    {
        return $this->allows($user, 'update');
    }

    public function delete(?object $user, Model $model): bool
    {
        return $this->allows($user, 'delete');
    }
}
