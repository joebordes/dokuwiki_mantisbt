# DokuWiki MantisBT Integration

This is a fork of the work done by Christoph Lang and Victor Boctor, detailed here:

* https://www.dokuwiki.org/plugin:mantis
* https://www.mantisbt.org/wiki/doku.php/mantisbt:issue:7075:integration_with_dokuwiki

The idea is to pickup where they seem to have left off and continue their work

## Configuration

Go to the extension configuration settings and set your url, user and password.

## Syntax

Reference an issue in your bug tracker with one of these formats
```
This was implemented in issue ~~issue:191~~

This was implemented in issue {{Mantis>bug>191}}

This was implemented in issue {{Mantis>issue>191}}
```

Get a list of tickets with this directive

```
These are the tickets of project coreBOS  {{Mantis>coreBOS}}
```
