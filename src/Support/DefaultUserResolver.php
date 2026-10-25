<?php

namespace Postiefs\Sso\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Postiefs\Sso\Contracts\UserResolver;
use Postiefs\Sso\Data\SsoIdentity;

/**
 * Finds the user by email (case insensitive) and creates one the first time, when `postiefs-sso.user.create_missing` allows it.
 *
 * Extend it to change the rules:
 *  - `isAllowed()`: who may sign in (an inactive user, a blocked domain)
 *  - `find()`: how a user is found
 *  - `create()`: how a user is made
 */
class DefaultUserResolver implements UserResolver
{
    /** @var array<string, array<int, string>> */
    private static array $columns = [];

    public function resolve(SsoIdentity $identity): ?Authenticatable
    {
        $user = $this->find($identity) ?? $this->create($identity);

        if ($user === null || ! $this->isAllowed($user, $identity)) {
            return null;
        }

        if (! $user->wasRecentlyCreated) {
            $this->sync($user, $identity);
        }

        return $user;
    }

    /**
     * Who may sign in. A user that is not allowed sees a 403.
     */
    protected function isAllowed(Model&Authenticatable $user, SsoIdentity $identity): bool
    {
        return true;
    }

    protected function find(SsoIdentity $identity): (Model&Authenticatable)|null
    {
        $column = (string) config('postiefs-sso.user.match_on', 'email');
        $value = $identity->claim($column === 'sso_sub' ? 'sub' : $column);

        /** @var class-string<Model&Authenticatable> $model */
        $model = config('postiefs-sso.user.model');
        $grammar = (new $model)->getConnection()->getQueryGrammar();

        return $model::query()
            ->whereRaw('LOWER('.$grammar->wrap($column).') = ?', [strtolower((string) $value)])
            ->first();
    }

    protected function create(SsoIdentity $identity): (Model&Authenticatable)|null
    {
        if (! config('postiefs-sso.user.create_missing', true)) {
            return null;
        }

        /** @var class-string<Model&Authenticatable> $model */
        $model = config('postiefs-sso.user.model');
        $user = new $model;

        // The email is the key a user is found by, so it is always set.
        $user->forceFill(['email' => $identity->email]);

        // A user made by the portal never uses a local password; a random one satisfies a NOT NULL column.
        if ($this->hasColumn($user, 'password')) {
            $user->forceFill(['password' => Hash::make(Str::random(64))]);
        }

        $user->forceFill((array) config('postiefs-sso.user.defaults', []));
        $this->fill($user, (array) config('postiefs-sso.user.attributes', []), $identity);
        $user->save();

        return $user;
    }

    private function sync(Model $user, SsoIdentity $identity): void
    {
        $columns = (array) config('postiefs-sso.user.sync', []);

        if ($columns === []) {
            return;
        }

        $this->fill($user, $columns, $identity);

        if ($user->isDirty()) {
            $user->save();
        }
    }

    /**
     * @param  array<string, string>  $map  column => claim
     */
    private function fill(Model $user, array $map, SsoIdentity $identity): void
    {
        foreach ($map as $column => $claim) {
            if (! $this->hasColumn($user, $column)) {
                continue;
            }

            $value = $identity->claim($claim);

            if (is_array($value) && ! $user->hasCast($column)) {
                $value = json_encode($value);
            }

            $user->forceFill([$column => $value]);
        }
    }

    private function hasColumn(Model $user, string $column): bool
    {
        $table = $user->getTable();

        self::$columns[$table] ??= array_map('strtolower', Schema::connection($user->getConnectionName())->getColumnListing($table));

        return in_array(strtolower($column), self::$columns[$table], true);
    }
}
