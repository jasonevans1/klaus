<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-zinc-800">
        <div class="mx-auto flex min-h-svh max-w-3xl flex-col gap-12 p-6">
            <header class="flex items-center justify-between">
                <x-app-logo href="{{ route('home') }}" />
                <flux:button variant="ghost" href="{{ route('login') }}">Log in</flux:button>
            </header>

            <main class="flex flex-col gap-12">
                <section class="flex flex-col items-start gap-4">
                    <flux:heading level="1" size="xl">Christmas and birthday gifts, planned together.</flux:heading>
                    <flux:text>Klaus keeps one list for your household, so you never buy the same gift twice.</flux:text>
                    <flux:button variant="primary" href="{{ route('register') }}">Get started</flux:button>
                </section>

                <section class="grid gap-6 sm:grid-cols-3">
                    <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:heading>One shared budget</flux:heading>
                        <flux:text>See what you've spent and what's left for each person.</flux:text>
                    </div>
                    <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:heading>Private gifts</flux:heading>
                        <flux:text>Plan a surprise your partner can't see.</flux:text>
                    </div>
                    <div class="flex flex-col gap-1 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:heading>Birthday reminders</flux:heading>
                        <flux:text>A heads-up before each birthday, so no one is missed.</flux:text>
                    </div>
                </section>
            </main>
        </div>

        @fluxScripts
    </body>
</html>
