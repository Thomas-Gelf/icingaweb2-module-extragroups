<?php

namespace Icinga\Module\Extragroups;

use Exception;
use Icinga\Application\Config;
use Icinga\Application\Logger;
use Icinga\Authentication\UserGroup\UserGroupBackendInterface;
use Icinga\Data\Filter\Filter;
use Icinga\User;

class UserGroupBackend implements UserGroupBackendInterface
{
    private $name;

    /**
     * Returns group list according configured rules
     *
     * return array
     * @param User $user
     * @return array
     */
    public function getMemberships(User $user)
    {
        $username = $user->getUsername();
        $groups = array();

        try {
            $this->eventuallyAddExtraGroups($username, $groups);
        } catch (Exception $e) {
            Logger::error(
                'Extragroups failed to check for extra groups: %s',
                $e->getMessage()
            );
        }

        return $groups;
    }

    protected function eventuallyAddExtraGroups($username, & $groups)
    {
        foreach (Config::module('extragroups') as $title => $section) {
            $addGroup = $section->get('add_groups');
            if (! $addGroup) {
                Logger::error(
                    'Extragroups ignores "%s" in config.ini, it has no "add_groups" setting',
                    $title
                );
                continue;
            }

            if ($envFilter = $section->get('env_filter')) {
                if (! Filter::fromQueryString($envFilter)->matches((object) $_SERVER)) {
                    Logger::debug(
                        "Extragroups env_filter '%s' doesn't match",
                        $envFilter
                    );
                    continue;
                }
            }

            if ($userFilter = $section->get('user_filter')) {
                $user = (object) array(
                    'username' => $username,
                    'group'    => $groups,
                );

                if (! Filter::fromQueryString($userFilter)->matches($user)) {
                    Logger::debug(
                        "Extragroups user_filter '%s' doesn't match %s",
                        $userFilter,
                        json_encode($user)
                    );
                    continue;
                }
            }

            $newGroups = $this->splitCommaSeparated($this->fillPlaceHolders($addGroup));
            Logger::info(
                "Extragroups rule '%s' adds '%s' for %s",
                $title,
                implode(', ', $newGroups),
                $username
            );
            foreach ($newGroups as $groupName) {
                $groups[] = $groupName;
            }
        }
    }

    protected function splitCommaSeparated($string)
    {
        return preg_split('/\s*,\s*/', $string, -1, PREG_SPLIT_NO_EMPTY);
    }

    protected function fillPlaceHolders($string)
    {
        return preg_replace_callback(
            '/\{([a-zA-Z0-9_]+)\}/',
            function ($matches) {
                if (array_key_exists($matches[1], $_SERVER)) {
                    return $_SERVER[$matches[1]];
                } else {
                    return $matches[1];
                }
            },
            $string
        );
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getUserBackendName($username)
    {
        return null;
    }
}
