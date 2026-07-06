<?php

namespace GridX\Contracts;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

interface Policy
{
    /**
     * A policy may be given various permissions.
     */
    public function permissions(): BelongsToMany;

    /**
     * Find a policy by its name and guard name.
     *
     * @param string|null $guardName
     *
     * @return \Fleebase\Policy
     *
     * @throws \GridX\Exceptions\PolicyDoesNotExist
     */
    public static function findByName(string $name, $guardName): self;

    /**
     * Find a policy by its id and guard name.
     *
     * @param string|null $guardName
     *
     * @return \Fleebase\Policy
     *
     * @throws \GridX\Exceptions\PolicyDoesNotExist
     */
    public static function findByIdentifier(string $id, $guardName): self;

    /**
     * Find or create a policy by its name and guard name.
     *
     * @param string|null $guardName
     *
     * @return \Fleebase\Policy
     */
    public static function findOrCreate(string $name, $guardName): self;

    /**
     * Determine if the user may perform the given permission.
     *
     * @param string|\Spatie\Permission\Contracts\Permission $permission
     */
    public function hasPermissionTo($permission): bool;
}
