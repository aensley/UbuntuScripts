# UbuntuScripts

Scripts I use on Ubuntu / Debian systems.

## New Machine

First step to perform on a new Ubuntu installation:

```ShellSession
wget -O /tmp/newUbuntu https://raw.githubusercontent.com/aensley/UbuntuScripts/main/newUbuntu && \
chmod +x /tmp/newUbuntu && \
/tmp/newUbuntu
```

## Existing Machine

If you have an existing installation and just want the scripts without any installation/removal of software, run this command:

```ShellSession
wget -qO- https://raw.githubusercontent.com/aensley/UbuntuScripts/main/sbin/updateScripts | bash -
```
