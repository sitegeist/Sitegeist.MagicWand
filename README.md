# Tools that make the Flow/Neos development easier

This package is intended to be used on development systems and should NEVER be
installed on production servers. *Please add this package to the require-dev
section of your composer.json*.

*Disclaimer: This package will drop the database and resources of the flow setup it is executed. The data is replaced
with the informations from the remote host. Make shure you understand und want that before actual using the commands.*

## Easy and fast cloning of Flow and Neos Installations

The cli commands `clone:list`, `clone:preset` and `clone:remotehost` help to
clone a remote Flow/Neos setup into the Flow/Neos where the command is executed.

### sitegeist:magicwand:clone:list

Show the presets that are defined in the configuration path. `Sitegeist.MagicWand.clonePresets`

```
Sitegeist:
  MagicWand:
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
```

The Settings should be added to the Global Settings.yaml of the project every
developer with ssh-access to the server can easyly clone the setup.

### CLI-Examples
```
# show all available presets
./flow clone:list

# clone from remote host with the information stored in the master preset
./flow clone:preset master

# clone remote host with the information stored in the master preset
./flow clone:remotehost --host=host --user=user --port=port --path=path --context=context
```

## Quick backup and restore mechanisms for persistent data

Sometimes it's useful to quickly backup an integral persistent state of an application, to then perform some risky
change operations and then restore the data in case of failure. The `stash` commands of this package allow for a
flawless backup-try-restore workflow.

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

## Authors

* Wilhelm Behncke - behncke@sitegeist.de
* Martin Ficzel - ficzel@sitegeist.de

The development and the public-releases of this package was generously sponsored by our employer http://www.sitegeist.de.