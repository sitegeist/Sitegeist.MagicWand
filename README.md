# Sitegeist.MagicWand
### Tools that make the Flow/Neos development easier

This package is intended to be used on development systems and should **NEVER** be
installed on production servers. **Please add this package to the require-dev
section of your composer.json**.

### Authors & Sponsors

* Wilhelm Behncke - behncke@sitegeist.de
* Martin Ficzel - ficzel@sitegeist.de
* ... and others

*The development and the public-releases of this package is generously sponsored by our employer https://www.sitegeist.de.*

## Easy and fast cloning of Flow and Neos Installations

The CLI commands `clone:list`, `clone:preset` to help to
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
```

### Settings.yaml

The presets that are defined in the configuration path. `Sitegeist.MagicWand.clonePresets`

```yaml
Sitegeist:
  MagicWand:
    flowCommand: './flow'

    # preset which is used by the clone:default command
    defaultPreset: 'master'

    # available presets
    clonePresets:

       # the name of the preset for referencing on the clone:preset command
      master:
        # hostname or ip of the server to clone from
        host: ~
        # ssh username
        user: ~
        # ssh port
        port: ~
        # ssh options
        sshOptions: ~
        # path on the remote server
        path: ~
        # flow-context on the remote server
        context: Production

        # the flow cli command on the remote server
        # default is the main flowCommand-Setting
        flowCommand: ~

        # commands to execute after cloning like ./flow user:create ...
        postClone: []

        # informations to access the resources of the cloned setup via http
        # if this is configured the rsync of the persistent resources is skipped
        # and instead resources are fetched and imported on the fly once read
        resourceProxy:
          baseUri: http://vour.server.tld
          # define wether or not the remote uses subdivideHashPathSegments
          subdivideHashPathSegment: false
          # curl options
          curlOptions:
            CURLOPT_USERPWD: very:secure
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

## Resource proxies

While cloning the database to your local dev system is manageable even for larger projects, downloading all the assets is often not an option.

For this case the package offers the concept of resource proxies. Once activated, only the resources that are actually used are downloaded just at the moment they are rendered.
This is done by custom implementations of `WritableFileSystemStorage` and `ProxyAwareFileSystemSymlinkTarget` and works out of the box if you use this storage and target in you local development environment.
If you use other local storages, for example a local S3 storage, you can easily build your own proxy aware versions implementing the interfaces `ProxyAwareStorageInterface` and `ProxyAwareTargetInterface`of this package.


## Installation

Sitegeist.Magicwand is available via packagist. Just add `"sitegeist/magicwand" : "~1.0"` to the require-dev section of the composer.json or run `composer require --dev sitegeist/magicwand`. We use semantic-versioning so every breaking change will increase the major-version number.

## Contribution

We will gladly accept contributions especially to improve the rsync, and ssh-options for a specific preset. Please send us pull requests.

### We will NOT add the following features to the main-repository

* Windows support: We rely on a unix-shell and a filesystem that is capable of hard-links.
* SSH with username/password: We consider this unsafe and recommend the use of public- and private-keys.
