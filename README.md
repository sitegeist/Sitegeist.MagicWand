# Sitegeist.MagicWand

## Important Note:

In the current release v1.0.4, we recognized an issue with the standard handling of parameters in Flow CLI. The following format currently does not work:

```
./flow clone:preset presetname
```

In order to make MagicWand work as expected, you'll instead have to use:

```
./flow clone:preset --preset-name presetname
```

v1.0.4 introduces the possibility to replace the standard flow command on remote systems, which sometimes becomes necessary in setups with multiple php versions (see #4). Also it fixes a issue with SQL statements, that were not properly escaped (see #3) If you don't require these features, you can decide to just skip this version until the next release.

We're on it folks ;)

### Tools that make the Flow/Neos development easier

This package is intended to be used on development systems and should **NEVER** be
installed on production servers. **Please add this package to the require-dev
section of your composer.json**.

### Authors & Sponsors

* Wilhelm Behncke - behncke@sitegeist.de
* Martin Ficzel - ficzel@sitegeist.de

*The development and the public-releases of this package is generously sponsored by our employer http://www.sitegeist.de.*

## Easy and fast cloning of Flow and Neos Installations

The CLI commands `clone:list`, `clone:preset` and `clone:remotehost` help to
clone a remote Flow/Neos setup into the local Flow/Neos installation that executes the command.

**Attention: These commands will empty the local database and resources of your local Flow installation.
The data is replaced with the information from the remote host. Make sure you understand that before actually
using the commands.**

### CLI-Examples
```
# show all available presets
./flow clone:list

# clone from remote host with the information stored in the master preset
./flow clone:preset master

# clone remote host with the information stored in the master preset
./flow clone:remotehost --host=host --user=user --port=port --path=path --context=context
```

### Settings.yaml

The presets that are defined in the configuration path. `Sitegeist.MagicWand.clonePresets`

```yaml
Sitegeist:
  MagicWand:
    flowCommand: './flow'
    clonePresets: []
#      # the name of the preset for referencing on the clone:preset command
#      master:
#        # hostname or ip of the server to clone from
#        host: ~
#        # ssh username
#        user: ~
#        # ssh port
#        port: ~
#        # path on the remote server
#        path: ~
#        # flow-context on the remote server  
#        context: Production
#        # commands to execute after cloning      
#        # the flow cli command on the remote server
#        # default is the main flowCommand-Setting
#        flowCommand: ~ 
#        postClone:
#         - './flow help'
```

The settings should be added to the global `Settings.yaml` of the project, so that every
developer with SSH-access to the remote server can easily clone the setup.

## Quick backup and restore mechanisms for persistent data

Sometimes it's useful to quickly backup an integral persistent state of an application, to then perform some risky
change operations and restore the data in case of failure. The `stash:create`,`stash:restore`,`stash:list` and
`stash:clear` commands of this package allow for a flawless backup-try-restore workflow.

**Attention: These commands will empty the database and resources of your local Flow installation.
The data is replaced with the information from the stash. Make sure you understand that before actually using
the commands.**

### CLI-Examples
```
# Create a backup of the entire database and the directory `Data/Persistent` ("stash entry") under the given name
./flow stash:create --name=name

# Lists all named stash entries
./flow stash:list

# Restores a stash entry
./flow stash:restore --name=name

# Removes all stash entries
./flow stash:clear
```
**Note:** Use this command on a regular basis, because your stash tends to grow **very** large.

## Installation

Sitegeist.Magicwand is available via packagist. Just add `"sitegeist/magicwand" : "~1.0"` to the require-dev section of the composer.json or run `composer require --dev sitegeist/magicwand`. We use semantic-versioning so every breaking change will increase the major-version number.

## Contribution

We will gladly accept contributions especially to improve the rsync, and ssh-options for a specific preset. Please send us pull requests.

### We will NOT add the following features to the main-repository

* Windows support: We rely on a unix-shell and a filesystem that is capable of hard-links.
* SSH with username/password: We consider this unsafe and recommend the use of public- and private-keys.
