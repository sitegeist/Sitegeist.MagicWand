# Tools that make the Flow/Neos development easier 

This package is intended to be used on development systems and should NEVER be 
installed on production servers. Please add this package to the require-dev 
section of your composer.json.

## Easy and fast cloning of Flow and Neos Installations

The cli commands `clone:list`, `clone:preset` and `clone:remotehost` help to 
clone a remote Flow/Neos setup into the Flow/Neos where the command is executed.

### sitegeist:magicwand:clone:list 

Show the presets that are defined in the configuration path. `Sitegeist.MagicWand.clonePresets` 

````
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
