<?php

namespace Hexa\PluginCore\Updater;

final class UpdaterConfig {
    public function __construct(
        public readonly string $github_repo,
        public readonly string $plugin_basename,
        public readonly string $plugin_slug,
        public readonly string $version,
        public readonly string $branch = 'main'
    ) {
    }

    public function github_api_url(): string {
        return 'https://api.github.com/repos/' . $this->github_repo;
    }

    public function zip_url(): string {
        return 'https://github.com/' . $this->github_repo . '/archive/' . $this->branch . '.zip';
    }
}

