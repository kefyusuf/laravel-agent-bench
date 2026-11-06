# bench runner: everything the harness needs to materialize and grade scenarios.
# - php 8.3 CLI (pdo_sqlite, sqlite3, mbstring bundled; zip installed for composer)
# - composer 2 (framework deps of scenario workspaces are installed per matrix cell)
# - node 20 (the zero-dependency harness itself) + git (workspaces are git repos)
FROM php:8.3-cli-bookworm

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        git \
        unzip \
        libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

# Composer binary from the official image (kept in sync with composer:2)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node 20 via NodeSource (the harness requires >= 18.17)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# Workspaces are bind-mounted from (possibly Windows-owned) paths: trust them all.
RUN git config --system --add safe.directory '*'

# Persist composer's package cache across runs (named volume in compose)
ENV COMPOSER_CACHE_DIR=/tmp/composer-cache

WORKDIR /app

CMD ["node", "bin/bench.mjs"]
