<?php

use App\Livewire\Actions\Logout;
use Livewire\Component;

new class extends Component {
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect(route('home'), navigate: true);
    }
};
?>

<div>
    <flux:header class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:brand href="{{ route('home') }}" name="Upchunk" class="max-lg:hidden">
            <x-slot name="logo">
                <div class="flex size-6 items-center justify-center rounded-md bg-accent text-white">
                    <flux:icon.arrow-up-tray class="size-3.5" variant="mini" />
                </div>
            </x-slot>
        </flux:brand>

        @auth
            <flux:navbar class="max-lg:hidden">
                <flux:navbar.item icon="arrow-up-tray" href="{{ route('home') }}" wire:navigate>Upload</flux:navbar.item>
                <flux:navbar.item icon="bolt" href="{{ route('direct') }}" wire:navigate>Direct-to-R2</flux:navbar.item>
                <flux:separator vertical variant="subtle" class="my-2" />
                <flux:navbar.item icon="link" href="{{ route('shares.index') }}" wire:navigate>My shares</flux:navbar.item>
            </flux:navbar>
        @endauth

        <flux:spacer />

        @auth
            <flux:dropdown position="bottom" align="end">
                <flux:button icon-trailing="chevron-down" variant="ghost">{{ auth()->user()->name }}</flux:button>
                <flux:navmenu>
                    <flux:navmenu.item href="{{ route('profile.update') }}" wire:navigate icon="user">Profile</flux:navmenu.item>
                    <flux:navmenu.item wire:click="logout" icon="arrow-right-start-on-rectangle">Logout</flux:navmenu.item>
                </flux:navmenu>
            </flux:dropdown>
        @else
            <div class="flex items-center gap-1">
                <flux:button variant="ghost" size="sm" href="{{ route('login') }}" wire:navigate>Login</flux:button>
                <flux:button variant="primary" size="sm" href="{{ route('register') }}" wire:navigate>Sign up</flux:button>
            </div>
        @endauth
    </flux:header>

    @auth
        <flux:sidebar sticky collapsible="mobile" class="border-r border-zinc-200 bg-zinc-50 lg:hidden dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <flux:sidebar.brand href="{{ route('home') }}" name="Upchunk" />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>
            <flux:sidebar.nav>
                <flux:sidebar.item icon="arrow-up-tray" href="{{ route('home') }}" wire:navigate>Upload</flux:sidebar.item>
                <flux:sidebar.item icon="bolt" href="{{ route('direct') }}" wire:navigate>Direct-to-R2</flux:sidebar.item>
                <flux:sidebar.item icon="link" href="{{ route('shares.index') }}" wire:navigate>My shares</flux:sidebar.item>
            </flux:sidebar.nav>
        </flux:sidebar>
    @endauth

    <flux:main container>
        {{ $slot }}
    </flux:main>
</div>
