<x-app-layout>
    <x-slot name="header">
        <h2 class="font-pixel text-sm text-dex-accent">
            Profil
        </h2>
    </x-slot>

    <div class="max-w-3xl space-y-6">
        <div class="pixel-panel p-4 sm:p-8">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="pixel-panel p-4 sm:p-8">
            @include('profile.partials.update-password-form')
        </div>

        <div class="pixel-panel border-dex-danger/60 p-4 sm:p-8">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-app-layout>
