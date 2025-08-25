<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <div class="absolute top-0 left-0 right-0" style="height: 30px; -webkit-app-region: drag;">
            <!-- Your Custom Title Content -->
        </div>

        {{ $slot }}
    </body>
</html>
