Extragroups - Icinga Web 2 module
=================================

This module provides an `UserGroupBackend` implementation for Icinga Web 2. It
allows to assign additional groups based on custom rules.

Installation
------------

Install and enable this module like any other [Icinga Web 2](https://www.icinga.com/products/icinga-web-2/)
module. Extract or clone it to a directory named `extragroups` in one of your
`module_path`'s. This is usually `/usr/share/icingaweb2/modules`.

Then add a dedicated section to your `/etc/icingaweb2/groups.ini`:

```ini
[Extragroups - Rule-based group membership]
backend = "groups"
```

That's it, now you're ready to go!

Configuration Examples
----------------------

### Additional groups for everybody

Rules have to be defined in `/etc/icingaweb2/modules/extragroups/config.ini`.
Let's start with a simple one:

```ini
[Everybody should be a Dummy User]
add_groups = "Dummy Users"
```

To see an effect please create a dedicated role in `/etc/icingaweb2/roles.ini`:

```ini
[Dummy User Demo]
groups = "Dummy Users"
monitoring/filter/objects = "host_name=*dummy*"
```

Log out and in again. Now you're a member of *Dummy Users*, and you should see
only hosts matching the `*dummy*` pattern. Please note that it doesn't matter
whether a group named *Dummy Users* exists.

### Pattern matching based on user names

Let's add more magic to our configuration:

```ini
[All dummies should be Dummy Users]
user_filter = "username=*dummy*"
add_groups = "Dummy Users"
```

Guess what? Same as before, but it affects only users carrying the string
`dummy` in their name.

### Rules based on your environment

Want to put all but some very special users in a special group, but only when
working from remote? Define a rule based on their IP address range:

```ini
[Restrict users when connected via VPN]
env_filter = "REMOTE_ADDR=192.0.2.*"
user_filter = "username!=tom&username!=admin"
add_groups = "Restricted Users"
```

Like `REMOTE_ADDR` all environment variables published by your web server can be
used. Let's imagine you want to show only very important hosts and services when
logged in via [Nagstamon](https://nagstamon.ifw-dresden.de):

```ini
[Filter objects for Nagstamon]
env_filter = "HTTP_USER_AGENT=Nagstamon*"
user_filter = "group=Network Admin"
add_groups = "VIP objects filter"
```

**WARNING**: Please note that User Agents can easily be spoofed, so this example
should only be used for convenience and not for security reasons.

### Assign multiple groups

Want to assign more than one group without duplicating your rules? `add_groups`
allows for a comma-separated list:

```ini
[Restrict users when connected via VPN]
; ..
add_groups = "Restricted Users, Special Dashboards, Businessprocess Users"
```

You like this, but want your group list to be more dynamic? Given that some
webserver extension provides this list, you can access every environment
variable via `{VARIABLE_NAME}`:

```ini
[Apply groups from SSO module]
add_groups = "{HTTP_AUTHZ_GROUPS}"
```

Also in groups fetched from your environment, the comma (`,`) works as a group
name separator. And you can of course combine placeholders with other strings:

```ini
[A bunch of groups]
add_groups = "Special Group, {REMOTE_GROUPS}, {HTTP_AUTHZ_GROUPS}"
```

### Rules based on group membership

Your `user_filter` allows to filter based on group membership. Let's imagine
that every user should get a list of additional groups via `HTTP_AUTHZ_GROUPS`.
Well, everybody but the `guest` and the `admin` user.

When using Nagstamon while being connected via VPN, we want them to be in the
*Nagstamon Remote* group.

When working with Nagstamon from all other places, only members of the *Linux
Users* and/or the *Unix Users* group should get the *Nagstamon Local* group.
All, but the `admin` user:

```ini
[Apply groups from SSO module]
add_groups = "{HTTP_AUTHZ_GROUPS}"
user_filter = "user_name!=guest&username!=admin"

[Nagstamon via VPN]
env_filter = "REMOTE_ADDR=192.0.2.*&HTTP_USER_AGENT=Nagstamon*"
add_groups = "Nagstamon Remote"

[Nagstamon, but not via VPN]
env_filter = "REMOTE_ADDR!=192.0.2.*&HTTP_USER_AGENT=Nagstamon*"
user_filter = "username!=admin&(group=Linux Users|group=Unix Users)"
add_groups = "Nagstamon Local"
```

Please note that the `group` property filter sees only groups assigned via
former rules. Groups memberships provided by other group backends are not
accessible.

That's it for now, have fun!
