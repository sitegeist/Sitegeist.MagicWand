# Tools that make the Flow/Neos development easier

This package is intended to be used on development systems and should NEVER be
installed on production servers. Please add this package to the require-dev
section of your composer.json.

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

### sitegeist:magicwand:clone:preset / sitegeist:magicwand:clone:remotehost

Use one of the presets described or the given arguments to fetch the content
of the remote host into the current Flow/Neos Setup.

## Quick backup and restore mechanisms for persistent data

Sometimes it's useful to quickly backup an integral persistent state of an application, to then perform some risky
change operations and then restore the data in case of failure. The `stash` commands of this package allow for a
flawless backup-try-restore workflow.

In combination with the `clone` commands, it's even possible to make quick production backups.

### sitegeist:magicwand:stash:push

> Creates a backup of the entire database and the directory `Data/Persistent` ("stash entry") in either a named (with
> the parameter `--name`) or anonymous fashion

**Note:** Anonymous stash entries are managed LIFO-wise. That means, that an anonymous stash entry can only be restored
once and is gone afterwards. Named stash entries can be restored multiple times.

### sitegeist:magicwand:stash:pop

> Restores the latest anonymous stash entry

### sitegeist:magicwand:stash:list

> Lists all named stash entries

### sitegeist:magicwand:stash:restore

> Restores a named stash entry (with the parameter `--name`)

### sitegeist:magicwand:stash:clear

> Removes all stash entries

**Note:** Use this command on a regular basis, because your stash tends to grow **very** large.
