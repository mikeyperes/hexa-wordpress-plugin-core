# Updater Namespace

Namespace:

```text
Hexa\PluginCore\Updater
```

Folder:

```text
src/Updater/
```

## Purpose

The updater namespace standardizes GitHub/update configuration.

The updater core must not hard-code one plugin's repository. It reads repository and plugin identity from the host `PluginContext` or an explicit `UpdaterConfig`.

## GitHub Slug Rule

GitHub repositories use:

```text
owner/repository
```

Do not include a protocol, branch, or `.git` suffix in the repository value.

Good:

```text
mikeyperes/hws-base-tools
```

Bad:

```text
https://github.com/mikeyperes/hws-base-tools
hws-base-tools-main
mikeyperes/hws-base-tools.git
```

