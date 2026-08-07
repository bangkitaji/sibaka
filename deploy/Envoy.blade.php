{{-- Laravel Envoy Deployment Script --}}
{{-- Zero-downtime deployment with symlink-based releases --}}
{{-- Usage: envoy run deploy --}}

@servers(['production' => 'deploy@sibaka.example.com'])

@setup
    $repository = 'git@github.com:org/sibaka.git';
    $branch = $branch ?? 'main';
    $app_dir = '/var/www/sibaka';
    $releases_dir = $app_dir . '/releases';
    $storage_dir = $app_dir . '/storage';
    $release = date('YmdHis');
    $new_release_dir = $releases_dir . '/' . $release;
    $keep_releases = 5;
@endsetup

@story('deploy')
    clone_repository
    run_composer
    build_frontend
    update_symlinks
    run_migrations
    optimize
    reload_services
    cleanup
    verify
@endstory

@story('rollback')
    rollback_release
    reload_services
@endstory

@task('clone_repository')
    echo "==> Cloning repository (branch: {{ $branch }})..."
    [ -d {{ $releases_dir }} ] || mkdir -p {{ $releases_dir }}
    git clone --depth 1 --branch {{ $branch }} {{ $repository }} {{ $new_release_dir }}
    echo "Release directory: {{ $new_release_dir }}"
@endtask

@task('run_composer')
    echo "==> Installing Composer dependencies..."
    cd {{ $new_release_dir }}
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
@endtask

@task('build_frontend')
    echo "==> Building frontend assets..."
    cd {{ $new_release_dir }}
    npm ci --production=false
    npm run build
    rm -rf node_modules
@endtask

@task('update_symlinks')
    echo "==> Updating symlinks..."

    {{-- Ensure shared storage exists --}}
    [ -d {{ $storage_dir }} ] || mkdir -p {{ $storage_dir }}
    [ -d {{ $storage_dir }}/app/public ] || mkdir -p {{ $storage_dir }}/app/public
    [ -d {{ $storage_dir }}/framework/cache ] || mkdir -p {{ $storage_dir }}/framework/cache
    [ -d {{ $storage_dir }}/framework/sessions ] || mkdir -p {{ $storage_dir }}/framework/sessions
    [ -d {{ $storage_dir }}/framework/views ] || mkdir -p {{ $storage_dir }}/framework/views
    [ -d {{ $storage_dir }}/logs ] || mkdir -p {{ $storage_dir }}/logs

    {{-- Remove release storage dir and link shared storage --}}
    rm -rf {{ $new_release_dir }}/storage
    ln -nfs {{ $storage_dir }} {{ $new_release_dir }}/storage

    {{-- Link shared .env --}}
    ln -nfs {{ $app_dir }}/.env {{ $new_release_dir }}/.env

    {{-- Atomic symlink swap (zero-downtime) --}}
    ln -nfs {{ $new_release_dir }} {{ $app_dir }}/current.tmp
    mv -Tf {{ $app_dir }}/current.tmp {{ $app_dir }}/current

    echo "Symlink updated to {{ $new_release_dir }}"
@endtask

@task('run_migrations')
    echo "==> Running database migrations..."
    cd {{ $app_dir }}/current
    php artisan migrate --force
@endtask

@task('optimize')
    echo "==> Optimizing application..."
    cd {{ $app_dir }}/current
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    php artisan storage:link
@endtask

@task('reload_services')
    echo "==> Reloading services..."
    sudo systemctl reload php8.3-fpm
    sudo supervisorctl restart sibaka-queue-default:*
    sudo supervisorctl restart sibaka-queue-notifications:*
    {{-- Scheduler will pick up the new release on next tick --}}
    echo "Services reloaded."
@endtask

@task('cleanup')
    echo "==> Cleaning up old releases (keeping {{ $keep_releases }})..."
    cd {{ $releases_dir }}
    ls -dt */ | tail -n +{{ $keep_releases + 1 }} | xargs -r rm -rf
    echo "Cleanup complete."
@endtask

@task('verify')
    echo "==> Verifying deployment..."
    cd {{ $app_dir }}/current
    php artisan --version
    curl -sf -o /dev/null -w "%{http_code}" https://sibaka.example.com/
    echo ""
    echo "Deployment successful! Release: {{ $release }}"
@endtask

@task('rollback_release')
    echo "==> Rolling back to previous release..."
    cd {{ $releases_dir }}
    PREVIOUS=$(ls -dt */ | head -2 | tail -1 | tr -d '/')
    if [ -z "$PREVIOUS" ]; then
        echo "ERROR: No previous release found."
        exit 1
    fi
    ln -nfs {{ $releases_dir }}/$PREVIOUS {{ $app_dir }}/current.tmp
    mv -Tf {{ $app_dir }}/current.tmp {{ $app_dir }}/current
    echo "Rolled back to release: $PREVIOUS"
@endtask
